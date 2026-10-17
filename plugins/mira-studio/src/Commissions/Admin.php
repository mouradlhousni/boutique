<?php
/**
 * Commission requests admin.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Commissions;

defined( 'ABSPATH' ) || exit;

/**
 * List columns, request details, workflow status box (New → Quoted → Accepted/Declined),
 * client emails on status changes and a "new requests" counter in the menu.
 *
 * In demo mode the screens work, but status, quote and note changes are not saved.
 */
final class Admin {

	private const NONCE = 'mira_commission_status';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes_' . PostType::POST_TYPE, array( $this, 'meta_boxes' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'apply_status' ), 10, 2 );
		add_action( 'save_post_' . PostType::POST_TYPE, array( $this, 'save' ) );
		add_action( 'transition_post_status', array( $this, 'notify' ), 10, 3 );
		add_filter( 'redirect_post_location', array( $this, 'demo_redirect' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'demo_notice' ) );
		add_filter( 'manage_' . PostType::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . PostType::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'menu_count' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/**
	 * Admin styles on the request screens.
	 *
	 * @return void
	 */
	public function assets(): void {
		$screen = get_current_screen();

		if ( $screen && PostType::POST_TYPE === $screen->post_type ) {
			wp_enqueue_style( 'mira-admin', MIRA_STUDIO_URL . 'assets/css/admin.css', array(), MIRA_STUDIO_VERSION );
		}
	}

	/**
	 * Replaces the publish box with the request details and the status box.
	 *
	 * @return void
	 */
	public function meta_boxes(): void {
		remove_meta_box( 'submitdiv', PostType::POST_TYPE, 'side' );
		remove_meta_box( 'slugdiv', PostType::POST_TYPE, 'normal' );
		add_meta_box( 'mira-commission-request', __( 'Request', 'mira-studio' ), array( $this, 'render_request' ), PostType::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'mira-commission-status', __( 'Status', 'mira-studio' ), array( $this, 'render_status' ), PostType::POST_TYPE, 'side', 'high' );
	}

	/**
	 * Request details and reference images.
	 *
	 * @param \WP_Post $post Request.
	 * @return void
	 */
	public function render_request( \WP_Post $post ): void {
		$email    = (string) get_post_meta( $post->ID, PostType::META_EMAIL, true );
		$budget   = (string) get_post_meta( $post->ID, PostType::META_BUDGET, true );
		$deadline = (string) get_post_meta( $post->ID, PostType::META_DEADLINE, true );
		$files    = get_post_meta( $post->ID, PostType::META_FILES, true );
		?>
		<dl class="mira-request">
			<dt><?php esc_html_e( 'Client', 'mira-studio' ); ?></dt>
			<dd><?php echo esc_html( (string) get_post_meta( $post->ID, PostType::META_NAME, true ) ); ?> – <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></dd>
			<dt><?php esc_html_e( 'Received', 'mira-studio' ); ?></dt>
			<dd><?php echo esc_html( get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $post ) ); ?></dd>
			<dt><?php esc_html_e( 'Budget', 'mira-studio' ); ?></dt>
			<dd><?php echo esc_html( PostType::budgets()[ $budget ] ?? '—' ); ?></dd>
			<dt><?php esc_html_e( 'Deadline', 'mira-studio' ); ?></dt>
			<dd><?php echo esc_html( $deadline ? date_i18n( get_option( 'date_format' ), strtotime( $deadline ) ) : __( 'Flexible', 'mira-studio' ) ); ?></dd>
			<dt><?php esc_html_e( 'Project', 'mira-studio' ); ?></dt>
			<dd class="mira-request__project"><?php echo wp_kses_post( wpautop( esc_html( (string) get_post_meta( $post->ID, PostType::META_PROJECT, true ) ) ) ); ?></dd>
			<dt><?php esc_html_e( 'Reference images', 'mira-studio' ); ?></dt>
			<dd>
				<?php if ( is_array( $files ) && $files ) : ?>
					<ul class="mira-request__files">
						<?php foreach ( $files as $index => $file ) : ?>
							<li>
								<a href="<?php echo esc_url( Uploads::url( $post->ID, (int) $index ) ); ?>" target="_blank" rel="noopener">
									<img src="<?php echo esc_url( Uploads::url( $post->ID, (int) $index ) ); ?>" alt="" loading="lazy" width="120" height="120">
									<span><?php echo esc_html( $file['name'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<?php esc_html_e( 'None', 'mira-studio' ); ?>
				<?php endif; ?>
			</dd>
		</dl>
		<?php
	}

	/**
	 * Workflow status box.
	 *
	 * @param \WP_Post $post Request.
	 * @return void
	 */
	public function render_status( \WP_Post $post ): void {
		$quote = (int) get_post_meta( $post->ID, PostType::META_QUOTE, true );

		wp_nonce_field( self::NONCE, '_mira_status_nonce' );
		?>
		<p>
			<label for="mira-status"><strong><?php esc_html_e( 'Status', 'mira-studio' ); ?></strong></label>
			<select id="mira-status" name="mira_status" class="widefat">
				<?php foreach ( PostType::statuses() as $status => $label ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $post->post_status, $status ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="mira-quote"><strong><?php esc_html_e( 'Quote (€)', 'mira-studio' ); ?></strong></label>
			<input type="number" id="mira-quote" name="mira_quote" class="widefat" min="0" step="10" value="<?php echo $quote ? esc_attr( (string) $quote ) : ''; ?>">
		</p>
		<p>
			<label for="mira-note"><strong><?php esc_html_e( 'Private note', 'mira-studio' ); ?></strong></label>
			<textarea id="mira-note" name="mira_note" class="widefat" rows="4"><?php echo esc_textarea( (string) get_post_meta( $post->ID, PostType::META_NOTE, true ) ); ?></textarea>
		</p>
		<p class="description"><?php esc_html_e( 'The client is emailed when the request becomes Quoted, Accepted or Declined.', 'mira-studio' ); ?></p>
		<p><button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Update request', 'mira-studio' ); ?></button></p>
		<?php
	}

	/**
	 * Applies the chosen workflow status when the request is saved from its screen.
	 *
	 * @param array<string, mixed> $data    Slashed post data about to be saved.
	 * @param array<string, mixed> $postarr Raw post array.
	 * @return array<string, mixed>
	 */
	public function apply_status( array $data, array $postarr ): array {
		if ( PostType::POST_TYPE !== $data['post_type'] || ! $this->verified( (int) ( $postarr['ID'] ?? 0 ) ) ) {
			return $data;
		}

		$current = get_post_status( (int) $postarr['ID'] );
		$status  = isset( $_POST['mira_status'] ) ? sanitize_key( wp_unslash( $_POST['mira_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in verified().

		if ( mira_is_demo_mode() || ! array_key_exists( $status, PostType::statuses() ) ) {
			// Keep the stored status (demo mode, or a core save without our field).
			$data['post_status'] = $current && array_key_exists( $current, PostType::statuses() ) ? $current : 'mira-new';
			return $data;
		}

		$data['post_status'] = $status;

		return $data;
	}

	/**
	 * Saves the quote and the private note.
	 *
	 * @param int $post_id Request ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( mira_is_demo_mode() || ! $this->verified( $post_id ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in verified().
		$quote = isset( $_POST['mira_quote'] ) ? absint( wp_unslash( $_POST['mira_quote'] ) ) : 0;
		$note  = isset( $_POST['mira_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['mira_note'] ) ) : '';
		// phpcs:enable

		if ( $quote ) {
			update_post_meta( $post_id, PostType::META_QUOTE, $quote );
		} else {
			delete_post_meta( $post_id, PostType::META_QUOTE );
		}
		update_post_meta( $post_id, PostType::META_NOTE, $note );
	}

	/**
	 * Emails the client when the workflow moves on (Quoted, Accepted, Declined).
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Request.
	 * @return void
	 */
	public function notify( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( PostType::POST_TYPE !== $post->post_type || $new_status === $old_status || 'mira-new' === $new_status || ! array_key_exists( $new_status, PostType::statuses() ) ) {
			return;
		}

		// The quote field is saved on save_post, after this hook: store it first.
		$this->save( $post->ID );
		Notifications::status_changed( $post->ID, $new_status );
	}

	/**
	 * Flags demo mode saves, to explain why nothing changed.
	 *
	 * @param string $location Redirect URL.
	 * @param int    $post_id  Post ID.
	 * @return string
	 */
	public function demo_redirect( string $location, int $post_id ): string {
		if ( mira_is_demo_mode() && PostType::POST_TYPE === get_post_type( $post_id ) ) {
			return add_query_arg( 'mira_demo', 1, remove_query_arg( 'message', $location ) );
		}

		return $location;
	}

	/**
	 * Demo mode notice after a save.
	 *
	 * @return void
	 */
	public function demo_notice(): void {
		if ( isset( $_GET['mira_demo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
			printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( mira_demo_message() ) );
		}
	}

	/**
	 * List columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		return array(
			'cb'            => $columns['cb'],
			'title'         => __( 'Request', 'mira-studio' ),
			'mira_status'   => __( 'Status', 'mira-studio' ),
			'mira_budget'   => __( 'Budget', 'mira-studio' ),
			'mira_deadline' => __( 'Deadline', 'mira-studio' ),
			'mira_files'    => __( 'Images', 'mira-studio' ),
			'date'          => __( 'Received', 'mira-studio' ),
		);
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Request ID.
	 * @return void
	 */
	public function column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'mira_status':
				$status = (string) get_post_status( $post_id );
				printf( '<span class="mira-status mira-status--%s">%s</span>', esc_attr( $status ), esc_html( PostType::statuses()[ $status ] ?? $status ) );
				break;
			case 'mira_budget':
				echo esc_html( PostType::budgets()[ (string) get_post_meta( $post_id, PostType::META_BUDGET, true ) ] ?? '—' );
				break;
			case 'mira_deadline':
				$deadline = (string) get_post_meta( $post_id, PostType::META_DEADLINE, true );
				echo esc_html( $deadline ? date_i18n( get_option( 'date_format' ), strtotime( $deadline ) ) : '—' );
				break;
			case 'mira_files':
				$files = get_post_meta( $post_id, PostType::META_FILES, true );
				echo esc_html( (string) ( is_array( $files ) ? count( $files ) : 0 ) );
				break;
		}
	}

	/**
	 * Only "Edit" and "Trash" make sense for a request.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public function row_actions( array $actions, \WP_Post $post ): array {
		if ( PostType::POST_TYPE === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'], $actions['view'] );
		}

		return $actions;
	}

	/**
	 * "New requests" bubble on the Commissions menu.
	 *
	 * @return void
	 */
	public function menu_count(): void {
		global $menu;

		$count = (int) ( wp_count_posts( PostType::POST_TYPE )->{'mira-new'} ?? 0 );

		if ( ! $count || ! is_array( $menu ) ) {
			return;
		}

		foreach ( $menu as $index => $item ) {
			if ( 'edit.php?post_type=' . PostType::POST_TYPE === ( $item[2] ?? '' ) ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Standard way to add a menu bubble.
				$menu[ $index ][0] .= sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $count );
				break;
			}
		}
	}

	/**
	 * Whether the current save comes from our status box, with a valid nonce and capability.
	 *
	 * @param int $post_id Request ID.
	 * @return bool
	 */
	private function verified( int $post_id ): bool {
		return $post_id
			&& isset( $_POST['_mira_status_nonce'] )
			&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_mira_status_nonce'] ) ), self::NONCE )
			&& current_user_can( 'edit_post', $post_id )
			&& ! ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE );
	}
}
