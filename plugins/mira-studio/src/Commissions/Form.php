<?php
/**
 * Public commission request form.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Commissions;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the form (`[mira_commission_form]` or `mira_commission_form()`). The form posts
 * to its own page and is handled on `template_redirect`, before any output:
 *
 * - with JavaScript, it is posted with `fetch()` and gets a JSON answer;
 * - without it, errors are shown by rendering the page again with the posted values
 *   (nothing is stored), and a success redirects to `?mira_sent=…` (Post/Redirect/Get).
 *
 * Protection: nonce, honeypot field, minimum fill time (signed timestamp), rate limit
 * per client, server-side validation of every field and of the uploaded images.
 *
 * Demo mode: everything is validated as usual, then the handler answers with the demo
 * message: no request is created, no file is kept (uploads are refused), no email is sent.
 */
final class Form {

	public const ACTION = 'mira_commission';

	private const NONCE      = 'mira_commission_form';
	private const HONEYPOT   = 'mira_website';
	private const MIN_SECOND = 3;
	private const FILES      = 'mira_references';

	/**
	 * Result of a submission handled during this request (classic POST with errors).
	 *
	 * @var array{message?:string, errors?:array<string,string>, values?:array<string,mixed>}
	 */
	private static array $result = array();

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_shortcode( 'mira_commission_form', array( $this, 'shortcode' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle' ), 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Handles a POST of the form, if this request is one.
	 *
	 * @return void
	 */
	public function maybe_handle(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only routing here: handle() verifies the nonce.
		if ( isset( $_SERVER['REQUEST_METHOD'], $_POST['action'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && self::ACTION === $_POST['action'] ) {
			$this->handle();
		}
	}

	/**
	 * Registers the progressive enhancement script (enqueued when the form renders).
	 *
	 * @return void
	 */
	public function register_assets(): void {
		wp_register_script(
			'mira-commission-form',
			MIRA_STUDIO_URL . 'assets/js/commission-form.js',
			array(),
			MIRA_STUDIO_VERSION,
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		wp_localize_script(
			'mira-commission-form',
			'miraCommission',
			array(
				'maxFiles' => Uploads::MAX_FILES,
				'maxBytes' => Uploads::MAX_BYTES,
				'types'    => array_values( Uploads::MIMES ),
				'i18n'     => array(
					'sending'  => __( 'Sending…', 'mira-studio' ),
					'error'    => __( 'Something went wrong. Please try again.', 'mira-studio' ),
					/* translators: %d: maximum number of files. */
					'tooMany'  => sprintf( __( 'Please attach %d images at most.', 'mira-studio' ), Uploads::MAX_FILES ),
					/* translators: %s: file name. */
					'tooBig'   => sprintf( __( '%%s is too large (%d MB maximum).', 'mira-studio' ), Uploads::MAX_BYTES / MB_IN_BYTES ),
					/* translators: %s: file name. */
					'badType'  => __( '%s is not a JPEG, PNG or WebP image.', 'mira-studio' ),
					'fixError' => __( 'Please check the highlighted fields.', 'mira-studio' ),
				),
			)
		);
	}

	/**
	 * Shortcode callback.
	 *
	 * @return string
	 */
	public function shortcode(): string {
		ob_start();
		$this->render();

		return (string) ob_get_clean();
	}

	/**
	 * Prints the form.
	 *
	 * @return void
	 */
	public function render(): void {
		$sent   = isset( $_GET['mira_sent'] ) ? sanitize_key( wp_unslash( $_GET['mira_sent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects a static message.
		$values = self::$result['values'] ?? array();
		$errors = self::$result['errors'] ?? array();
		$demo   = mira_is_demo_mode();
		$notice = '';

		if ( ! empty( self::$result['message'] ) ) {
			$notice = self::$result['message'];
		} elseif ( 'demo' === $sent ) {
			$notice = mira_demo_message();
		} elseif ( 'ok' === $sent ) {
			$notice = $this->success_message();
		}

		wp_enqueue_script( 'mira-commission-form' );

		$field_error = static function ( string $field ) use ( $errors ): void {
			if ( ! empty( $errors[ $field ] ) ) {
				printf( '<p class="mira-field__error" id="mira-%1$s-error">%2$s</p>', esc_attr( $field ), esc_html( $errors[ $field ] ) );
			}
		};
		$invalid     = static fn( string $field ): string => empty( $errors[ $field ] ) ? '' : sprintf( ' aria-invalid="true" aria-describedby="mira-%s-error"', esc_attr( $field ) );
		?>
		<form id="commission-form" class="mira-commission" method="post" action="<?php echo esc_url( remove_query_arg( 'mira_sent' ) ); ?>" enctype="multipart/form-data" novalidate data-mira-commission>
			<div class="mira-commission__status" role="status" aria-live="polite" tabindex="-1" data-mira-status>
				<?php if ( '' !== $notice ) : ?>
					<p class="mira-notice mira-notice--<?php echo esc_attr( $errors ? 'error' : 'success' ); ?>"><?php echo esc_html( $notice ); ?></p>
				<?php endif; ?>
			</div>

			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
			<input type="hidden" name="mira_started" value="<?php echo esc_attr( $this->sign_time( time() ) ); ?>">
			<?php wp_nonce_field( self::NONCE, '_mira_nonce', false ); ?>

			<div class="mira-field mira-field--honeypot" aria-hidden="true">
				<label for="mira-website"><?php esc_html_e( 'Leave this field empty', 'mira-studio' ); ?></label>
				<input type="text" id="mira-website" name="<?php echo esc_attr( self::HONEYPOT ); ?>" tabindex="-1" autocomplete="off">
			</div>

			<div class="mira-commission__row">
				<p class="mira-field">
					<label for="mira-name"><?php esc_html_e( 'Your name', 'mira-studio' ); ?> <span class="mira-required" aria-hidden="true">*</span></label>
					<input type="text" id="mira-name" name="mira_name" required maxlength="100" autocomplete="name" value="<?php echo esc_attr( $values['name'] ?? '' ); ?>"<?php echo $invalid( 'name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>>
					<?php $field_error( 'name' ); ?>
				</p>
				<p class="mira-field">
					<label for="mira-email"><?php esc_html_e( 'Email', 'mira-studio' ); ?> <span class="mira-required" aria-hidden="true">*</span></label>
					<input type="email" id="mira-email" name="mira_email" required maxlength="190" autocomplete="email" value="<?php echo esc_attr( $values['email'] ?? '' ); ?>"<?php echo $invalid( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>>
					<?php $field_error( 'email' ); ?>
				</p>
			</div>

			<p class="mira-field">
				<label for="mira-project"><?php esc_html_e( 'Tell me about your project', 'mira-studio' ); ?> <span class="mira-required" aria-hidden="true">*</span></label>
				<span class="mira-field__hint" id="mira-project-hint"><?php esc_html_e( 'Subject, size, where it will live, colours you love… (30 to 3,000 characters)', 'mira-studio' ); ?></span>
				<textarea id="mira-project" name="mira_project" rows="6" required minlength="30" maxlength="3000" aria-describedby="mira-project-hint"<?php echo $invalid( 'project' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>><?php echo esc_textarea( $values['project'] ?? '' ); ?></textarea>
				<?php $field_error( 'project' ); ?>
			</p>

			<div class="mira-commission__row">
				<p class="mira-field">
					<label for="mira-budget"><?php esc_html_e( 'Budget', 'mira-studio' ); ?> <span class="mira-required" aria-hidden="true">*</span></label>
					<select id="mira-budget" name="mira_budget" required<?php echo $invalid( 'budget' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>>
						<option value=""><?php esc_html_e( 'Choose a range', 'mira-studio' ); ?></option>
						<?php foreach ( PostType::budgets() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['budget'] ?? '', $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php $field_error( 'budget' ); ?>
				</p>
				<p class="mira-field">
					<label for="mira-deadline"><?php esc_html_e( 'Ideal deadline', 'mira-studio' ); ?></label>
					<input type="date" id="mira-deadline" name="mira_deadline" min="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+14 days' ) ) ); ?>" max="<?php echo esc_attr( gmdate( 'Y-m-d', strtotime( '+2 years' ) ) ); ?>" value="<?php echo esc_attr( $values['deadline'] ?? '' ); ?>"<?php echo $invalid( 'deadline' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>>
					<?php $field_error( 'deadline' ); ?>
				</p>
			</div>

			<div class="mira-field mira-field--files">
				<label for="mira-references"><?php esc_html_e( 'Reference images', 'mira-studio' ); ?></label>
				<span class="mira-field__hint" id="mira-references-hint">
					<?php
					if ( $demo ) {
						esc_html_e( 'Uploads are disabled on this demo site.', 'mira-studio' );
					} else {
						/* translators: 1: maximum number of files, 2: maximum size in MB. */
						echo esc_html( sprintf( __( 'Optional. Up to %1$d JPEG, PNG or WebP images, %2$d MB each.', 'mira-studio' ), Uploads::MAX_FILES, Uploads::MAX_BYTES / MB_IN_BYTES ) );
					}
					?>
				</span>
				<input type="file" id="mira-references" name="<?php echo esc_attr( self::FILES ); ?>[]" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="mira-references-hint"<?php echo $demo ? ' disabled' : ''; ?><?php echo $invalid( 'files' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>>
				<ul class="mira-field__previews" data-mira-previews></ul>
				<?php $field_error( 'files' ); ?>
			</div>

			<p class="mira-field mira-field--checkbox">
				<input type="checkbox" id="mira-consent" name="mira_consent" value="1" required <?php checked( ! empty( $values['consent'] ) ); ?><?php echo $invalid( 'consent' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>>
				<label for="mira-consent"><?php esc_html_e( 'I agree that Atelier Mira keeps these details to answer my request (deleted after 12 months).', 'mira-studio' ); ?> <span class="mira-required" aria-hidden="true">*</span></label>
				<?php $field_error( 'consent' ); ?>
			</p>

			<p class="mira-commission__submit">
				<button type="submit" class="mira-button"><?php esc_html_e( 'Send my request', 'mira-studio' ); ?></button>
				<span class="mira-commission__legend"><span aria-hidden="true">*</span> <?php esc_html_e( 'required', 'mira-studio' ); ?></span>
			</p>
		</form>
		<?php
	}

	/**
	 * Handles a submission. Every answer either exits (JSON, redirect) or returns
	 * with the errors kept for the page render.
	 *
	 * @return void
	 */
	private function handle(): void {
		$ajax = isset( $_SERVER['HTTP_X_MIRA_AJAX'] );

		if ( ! isset( $_POST['_mira_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_mira_nonce'] ) ), self::NONCE ) ) {
			$this->respond( $ajax, false, __( 'Your session has expired. Please reload the page and try again.', 'mira-studio' ), array(), array(), 403 );
			return;
		}

		// Bots: honeypot filled or form sent too fast. Answer like a success, store nothing.
		$started = isset( $_POST['mira_started'] ) ? $this->verify_time( sanitize_text_field( wp_unslash( $_POST['mira_started'] ) ) ) : 0;
		if ( ! empty( $_POST[ self::HONEYPOT ] ) || ! $started || time() - $started < self::MIN_SECOND ) {
			$this->respond( $ajax, true, mira_is_demo_mode() ? mira_demo_message() : $this->success_message(), array(), array(), 200, mira_is_demo_mode() ? 'demo' : 'ok' );
			return;
		}

		if ( ! mira_is_demo_mode() ) {
			$wait = RateLimiter::hit( 'commission', 8, 15 * MINUTE_IN_SECONDS );
			if ( $wait ) {
				/* translators: %d: minutes to wait. */
				$this->respond( $ajax, false, sprintf( _n( 'Too many requests. Please try again in %d minute.', 'Too many requests. Please try again in %d minutes.', (int) ceil( $wait / 60 ), 'mira-studio' ), (int) ceil( $wait / 60 ) ), array(), array(), 429 );
				return;
			}
		}

		$values = array(
			'name'     => isset( $_POST['mira_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mira_name'] ) ) : '',
			'email'    => isset( $_POST['mira_email'] ) ? sanitize_email( wp_unslash( $_POST['mira_email'] ) ) : '',
			'project'  => isset( $_POST['mira_project'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mira_project'] ) ) : '',
			'budget'   => isset( $_POST['mira_budget'] ) ? sanitize_key( wp_unslash( $_POST['mira_budget'] ) ) : '',
			'deadline' => isset( $_POST['mira_deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['mira_deadline'] ) ) : '',
			'consent'  => ! empty( $_POST['mira_consent'] ),
		);
		$files  = Uploads::from_request( self::FILES );
		$errors = $this->validate( $values, $files );

		if ( $errors ) {
			$this->respond( $ajax, false, __( 'Please check the highlighted fields.', 'mira-studio' ), $errors, $values, 422 );
			return;
		}

		if ( mira_is_demo_mode() ) {
			$this->respond( $ajax, true, mira_demo_message(), array(), array(), 200, 'demo' );
			return;
		}

		$request_id = $this->create( $values, $files );
		if ( ! $request_id ) {
			$this->respond( $ajax, false, __( 'Your request could not be saved. Please try again later.', 'mira-studio' ), array(), $values, 500 );
			return;
		}

		Notifications::request_received( $request_id );

		$this->respond( $ajax, true, $this->success_message(), array(), array(), 200, 'ok' );
	}

	/**
	 * Validates the fields. Returns errors keyed by field.
	 *
	 * @param array<string, mixed>                                                 $values Values.
	 * @param array<int, array{name:string, tmp_name:string, size:int, error:int}> $files  Uploaded files.
	 * @return array<string, string>
	 */
	private function validate( array $values, array $files ): array {
		$errors = array();
		$length = mb_strlen( $values['project'] );

		if ( '' === $values['name'] || mb_strlen( $values['name'] ) > 100 ) {
			$errors['name'] = __( 'Please enter your name.', 'mira-studio' );
		}
		if ( ! is_email( $values['email'] ) ) {
			$errors['email'] = __( 'Please enter a valid email address.', 'mira-studio' );
		}
		if ( $length < 30 || $length > 3000 ) {
			$errors['project'] = __( 'Please describe your project in 30 to 3,000 characters.', 'mira-studio' );
		}
		if ( ! array_key_exists( $values['budget'], PostType::budgets() ) ) {
			$errors['budget'] = __( 'Please choose a budget range.', 'mira-studio' );
		}
		if ( '' !== $values['deadline'] ) {
			$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $values['deadline'], wp_timezone() );
			$min  = new \DateTimeImmutable( 'today +14 days', wp_timezone() );
			$max  = new \DateTimeImmutable( 'today +2 years', wp_timezone() );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $values['deadline'] || $date < $min || $date > $max ) {
				$errors['deadline'] = __( 'Please choose a date at least two weeks from now.', 'mira-studio' );
			}
		}
		if ( ! $values['consent'] ) {
			$errors['consent'] = __( 'Please accept so I can answer your request.', 'mira-studio' );
		}

		if ( $files && mira_is_demo_mode() ) {
			$errors['files'] = __( 'Uploads are disabled on this demo site: no file is accepted.', 'mira-studio' );
		} elseif ( $files ) {
			$message = Uploads::validate( $files );
			if ( '' !== $message ) {
				$errors['files'] = $message;
			}
		}

		return $errors;
	}

	/**
	 * Creates the request and stores its files.
	 *
	 * @param array<string, mixed>                                                 $values Valid values.
	 * @param array<int, array{name:string, tmp_name:string, size:int, error:int}> $files  Valid files.
	 * @return int Request ID, 0 on failure.
	 */
	private function create( array $values, array $files ): int {
		$request_id = wp_insert_post(
			array(
				'post_type'   => PostType::POST_TYPE,
				'post_status' => 'mira-new',
				/* translators: %s: client name. */
				'post_title'  => sprintf( __( 'Commission from %s', 'mira-studio' ), $values['name'] ),
				'meta_input'  => array(
					PostType::META_NAME     => $values['name'],
					PostType::META_EMAIL    => $values['email'],
					PostType::META_PROJECT  => $values['project'],
					PostType::META_BUDGET   => $values['budget'],
					PostType::META_DEADLINE => $values['deadline'],
				),
			),
			true
		);

		if ( is_wp_error( $request_id ) ) {
			return 0;
		}

		if ( $files ) {
			update_post_meta( $request_id, PostType::META_FILES, Uploads::store( $request_id, $files ) );
		}

		return (int) $request_id;
	}

	/**
	 * Answers the submission. JSON for fetch(); for the classic POST, a redirect on success
	 * (never returns), or the errors kept for the page that is about to render.
	 *
	 * @param bool                  $ajax    JSON answer.
	 * @param bool                  $ok      Success.
	 * @param string                $message Message.
	 * @param array<string, string> $errors  Field errors.
	 * @param array<string, mixed>  $values  Input to restore.
	 * @param int                   $status  HTTP status.
	 * @param string                $sent    Success key for the redirect (ok|demo).
	 * @return void
	 */
	private function respond( bool $ajax, bool $ok, string $message, array $errors = array(), array $values = array(), int $status = 200, string $sent = 'ok' ): void {
		if ( $ajax ) {
			wp_send_json(
				array(
					'ok'      => $ok,
					'message' => $message,
					'errors'  => (object) $errors,
				),
				$status
			);
		}

		if ( $ok ) {
			wp_safe_redirect( add_query_arg( 'mira_sent', $sent, remove_query_arg( 'mira_sent' ) ) . '#commission-form', 303 );
			exit;
		}

		status_header( $status );
		self::$result = array(
			'message' => $message,
			'errors'  => $errors ? $errors : array( 'form' => $message ),
			'values'  => $values,
		);
	}

	/**
	 * Success message.
	 *
	 * @return string
	 */
	private function success_message(): string {
		return __( 'Thank you! Your request is on my desk: you will receive a confirmation email, and an answer within a week.', 'mira-studio' );
	}

	/**
	 * Signed timestamp (form start time).
	 *
	 * @param int $time Timestamp.
	 * @return string
	 */
	private function sign_time( int $time ): string {
		return $time . '.' . substr( hash_hmac( 'sha256', (string) $time, wp_salt( 'nonce' ) ), 0, 16 );
	}

	/**
	 * Verifies a signed timestamp.
	 *
	 * @param string $value Signed value.
	 * @return int Timestamp, 0 when invalid.
	 */
	private function verify_time( string $value ): int {
		$parts = explode( '.', $value );

		return 2 === count( $parts ) && hash_equals( $this->sign_time( (int) $parts[0] ), $value ) ? (int) $parts[0] : 0;
	}
}
