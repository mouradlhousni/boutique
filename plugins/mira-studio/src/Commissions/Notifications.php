<?php
/**
 * Commission emails.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Commissions;

defined( 'ABSPATH' ) || exit;

/**
 * Plain-text notifications: the studio is told about every new request, the client gets
 * a confirmation, then a message when the request is quoted, accepted or declined.
 * Never sends anything in demo mode.
 */
final class Notifications {

	/**
	 * New request: studio notification and client confirmation.
	 *
	 * @param int $request_id Request ID.
	 * @return void
	 */
	public static function request_received( int $request_id ): void {
		if ( mira_is_demo_mode() ) {
			return;
		}

		$data   = self::data( $request_id );
		$budget = PostType::budgets()[ $data['budget'] ] ?? $data['budget'];
		$files  = get_post_meta( $request_id, PostType::META_FILES, true );

		$lines = array(
			/* translators: %s: client name. */
			sprintf( __( 'New commission request from %s.', 'mira-studio' ), $data['name'] ),
			'',
			/* translators: %s: email address. */
			sprintf( __( 'Email: %s', 'mira-studio' ), $data['email'] ),
			/* translators: %s: budget range. */
			sprintf( __( 'Budget: %s', 'mira-studio' ), $budget ),
			/* translators: %s: deadline. */
			sprintf( __( 'Deadline: %s', 'mira-studio' ), $data['deadline'] ? $data['deadline'] : __( 'flexible', 'mira-studio' ) ),
			/* translators: %d: number of images. */
			sprintf( __( 'Reference images: %d', 'mira-studio' ), is_array( $files ) ? count( $files ) : 0 ),
			'',
			$data['project'],
			'',
			/* translators: %s: admin URL. */
			sprintf( __( 'Review it: %s', 'mira-studio' ), admin_url( 'post.php?post=' . $request_id . '&action=edit' ) ),
		);

		wp_mail(
			self::studio_email(),
			/* translators: %s: client name. */
			sprintf( __( '[Atelier Mira] New commission request from %s', 'mira-studio' ), $data['name'] ),
			implode( "\n", $lines ),
			array( 'Reply-To: ' . self::address( $data['name'], $data['email'] ) )
		);

		wp_mail(
			$data['email'],
			__( 'Your commission request – Atelier Mira', 'mira-studio' ),
			implode(
				"\n",
				array(
					/* translators: %s: client first name. */
					sprintf( __( 'Hello %s,', 'mira-studio' ), $data['name'] ),
					'',
					__( 'Thank you for your commission request: it is safely on my desk. I read every request myself and will answer within a week, usually with a few questions and a first estimate.', 'mira-studio' ),
					'',
					__( 'Your message:', 'mira-studio' ),
					$data['project'],
					'',
					__( 'Mira – Atelier Mira', 'mira-studio' ),
				)
			)
		);
	}

	/**
	 * Status change: tells the client.
	 *
	 * @param int    $request_id Request ID.
	 * @param string $status     New status.
	 * @return void
	 */
	public static function status_changed( int $request_id, string $status ): void {
		if ( mira_is_demo_mode() ) {
			return;
		}

		$data  = self::data( $request_id );
		$quote = (int) get_post_meta( $request_id, PostType::META_QUOTE, true );

		switch ( $status ) {
			case 'mira-quoted':
				$subject = __( 'Your commission: estimate', 'mira-studio' );
				$body    = $quote
					/* translators: %s: quoted amount. */
					? sprintf( __( 'I would love to work on your project. My estimate is €%s, including two rounds of sketches. Reply to this email to accept or ask questions.', 'mira-studio' ), number_format_i18n( $quote ) )
					: __( 'I would love to work on your project. My estimate will follow in a separate email with the details.', 'mira-studio' );
				break;
			case 'mira-accepted':
				$subject = __( 'Your commission: let us begin', 'mira-studio' );
				$body    = __( 'Wonderful, your commission is confirmed! I will send the first sketches within two weeks.', 'mira-studio' );
				break;
			case 'mira-declined':
				$subject = __( 'Your commission request', 'mira-studio' );
				$body    = __( 'Thank you again for thinking of me. Unfortunately I cannot take on your project at the moment. The prints in the shop might be a good alternative in the meantime.', 'mira-studio' );
				break;
			default:
				return;
		}

		wp_mail(
			$data['email'],
			$subject . ' – Atelier Mira',
			/* translators: %s: client name. */
			sprintf( __( 'Hello %s,', 'mira-studio' ), $data['name'] ) . "\n\n" . $body . "\n\n" . __( 'Mira – Atelier Mira', 'mira-studio' ),
			array( 'Reply-To: ' . self::address( 'Atelier Mira', self::studio_email() ) )
		);
	}

	/**
	 * Request data.
	 *
	 * @param int $request_id Request ID.
	 * @return array{name:string, email:string, project:string, budget:string, deadline:string}
	 */
	private static function data( int $request_id ): array {
		return array(
			'name'     => (string) get_post_meta( $request_id, PostType::META_NAME, true ),
			'email'    => (string) get_post_meta( $request_id, PostType::META_EMAIL, true ),
			'project'  => (string) get_post_meta( $request_id, PostType::META_PROJECT, true ),
			'budget'   => (string) get_post_meta( $request_id, PostType::META_BUDGET, true ),
			'deadline' => (string) get_post_meta( $request_id, PostType::META_DEADLINE, true ),
		);
	}

	/**
	 * Address receiving the studio notifications.
	 *
	 * @return string
	 */
	private static function studio_email(): string {
		/**
		 * Filters the email address notified of new commission requests.
		 *
		 * @param string $email Address (site admin email by default).
		 */
		return (string) apply_filters( 'mira_commission_email', get_option( 'admin_email' ) );
	}

	/**
	 * "Name <email>" header value, stripped of anything that could inject headers.
	 *
	 * @param string $name  Name.
	 * @param string $email Email.
	 * @return string
	 */
	private static function address( string $name, string $email ): string {
		return str_replace( array( "\r", "\n", '"', '<', '>' ), '', $name ) . ' <' . sanitize_email( $email ) . '>';
	}
}
