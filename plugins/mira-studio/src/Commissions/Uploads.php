<?php
/**
 * Reference images attached to commission requests.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Commissions;

defined( 'ABSPATH' ) || exit;

/**
 * Validation, private storage and admin-only download of reference images.
 *
 * - Validation: PHP upload error, count, size, real MIME type sniffed from the bytes
 *   (`wp_check_filetype_and_ext()` uses finfo/getimagesize), matching extension,
 *   decodable image with sane dimensions.
 * - Storage: files are re-encoded with the WordPress image editor (drops metadata and
 *   anything appended to the image), renamed to a random name and stored outside the
 *   media library in `uploads/mira-commissions/`, which is closed to the web by
 *   `.htaccess` (Apache) and an empty index. Nginx users must deny the folder too.
 * - Download: only through `admin-post.php?action=mira_commission_file`, with a nonce and
 *   the `edit_post` capability on the request, sent as an attachment with `nosniff`.
 */
final class Uploads {

	public const MAX_FILES = 3;
	public const MAX_BYTES = 5 * MB_IN_BYTES;
	public const MAX_PIXEL = 8000;
	public const MIMES     = array(
		'jpg|jpeg' => 'image/jpeg',
		'png'      => 'image/png',
		'webp'     => 'image/webp',
	);

	private const DIR = 'mira-commissions';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_post_mira_commission_file', array( $this, 'download' ) );
		add_action( 'before_delete_post', array( $this, 'delete_with_request' ) );
	}

	/**
	 * Deletes the private files with their request (admin, WP-CLI, cron).
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function delete_with_request( int $post_id ): void {
		if ( PostType::POST_TYPE === get_post_type( $post_id ) ) {
			self::delete( $post_id );
		}
	}

	/**
	 * Normalised list of uploaded files from a multiple file input.
	 *
	 * @param string $field Input name.
	 * @return array<int, array{name:string, tmp_name:string, size:int, error:int}>
	 */
	public static function from_request( string $field ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The form handler verifies the nonce before calling this.
		if ( empty( $_FILES[ $field ] ) || ! is_array( $_FILES[ $field ]['name'] ?? null ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Verified by the caller; every value is validated below.
		$raw   = $_FILES[ $field ];
		$files = array();

		foreach ( array_keys( $raw['name'] ) as $i ) {
			if ( UPLOAD_ERR_NO_FILE === (int) $raw['error'][ $i ] ) {
				continue;
			}
			$files[] = array(
				'name'     => sanitize_file_name( (string) $raw['name'][ $i ] ),
				'tmp_name' => (string) $raw['tmp_name'][ $i ],
				'size'     => (int) $raw['size'][ $i ],
				'error'    => (int) $raw['error'][ $i ],
			);
		}

		return $files;
	}

	/**
	 * Validates the files. Returns an error message, or an empty string when valid.
	 *
	 * @param array<int, array{name:string, tmp_name:string, size:int, error:int}> $files Files.
	 * @return string
	 */
	public static function validate( array $files ): string {
		if ( count( $files ) > self::MAX_FILES ) {
			/* translators: %d: maximum number of files. */
			return sprintf( __( 'Please attach %d images at most.', 'mira-studio' ), self::MAX_FILES );
		}

		foreach ( $files as $file ) {
			if ( UPLOAD_ERR_INI_SIZE === $file['error'] || UPLOAD_ERR_FORM_SIZE === $file['error'] || $file['size'] > self::MAX_BYTES ) {
				/* translators: 1: file name, 2: maximum size in MB. */
				return sprintf( __( '%1$s is too large (%2$d MB maximum).', 'mira-studio' ), $file['name'], self::MAX_BYTES / MB_IN_BYTES );
			}

			if ( UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
				/* translators: %s: file name. */
				return sprintf( __( '%s could not be uploaded, please try again.', 'mira-studio' ), $file['name'] );
			}

			// Real type from the file content, and an extension that matches it.
			$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::MIMES );
			$info  = @getimagesize( $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid images are expected input.

			if ( ! $check['ext'] || ! $check['type'] || ! in_array( $check['type'], self::MIMES, true ) || ! $info || $info['mime'] !== $check['type'] ) {
				/* translators: %s: file name. */
				return sprintf( __( '%s is not a JPEG, PNG or WebP image.', 'mira-studio' ), $file['name'] );
			}

			if ( $info[0] > self::MAX_PIXEL || $info[1] > self::MAX_PIXEL ) {
				/* translators: 1: file name, 2: maximum pixels. */
				return sprintf( __( '%1$s is too big: %2$d pixels per side at most.', 'mira-studio' ), $file['name'], self::MAX_PIXEL );
			}
		}

		return '';
	}

	/**
	 * Stores validated files for a request.
	 *
	 * @param int                                                                  $request_id Request ID.
	 * @param array<int, array{name:string, tmp_name:string, size:int, error:int}> $files      Validated files.
	 * @return array<int, array{path:string, name:string, type:string}> Stored files (path relative to the private folder).
	 */
	public static function store( int $request_id, array $files ): array {
		$base = self::base_dir();
		$dir  = $request_id . '-' . wp_generate_password( 16, false );
		wp_mkdir_p( $base . '/' . $dir );
		self::protect( $base . '/' . $dir );

		$stored = array();
		foreach ( $files as $file ) {
			$type   = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::MIMES )['type'];
			$ext    = array(
				'image/jpeg' => 'jpg',
				'image/png'  => 'png',
				'image/webp' => 'webp',
			)[ $type ] ?? '';
			$target = $dir . '/' . wp_generate_password( 24, false ) . '.' . $ext;
			$editor = wp_get_image_editor( $file['tmp_name'] );

			if ( ! $ext || is_wp_error( $editor ) ) {
				continue;
			}

			// Re-encode: the saved file only contains pixels decoded by the image library.
			$editor->resize( 2400, 2400 );
			$saved = $editor->save( $base . '/' . $target, $type );

			if ( ! is_wp_error( $saved ) ) {
				$stored[] = array(
					'path' => $target,
					'name' => $file['name'],
					'type' => $type,
				);
			}
		}

		return $stored;
	}

	/**
	 * Deletes the files of a request.
	 *
	 * @param int $request_id Request ID.
	 * @return void
	 */
	public static function delete( int $request_id ): void {
		$files = get_post_meta( $request_id, PostType::META_FILES, true );

		foreach ( is_array( $files ) ? $files : array() as $file ) {
			$path = self::resolve( (string) $file['path'] );
			if ( $path ) {
				wp_delete_file( $path );
				$folder = dirname( $path );
				foreach ( array( '.htaccess', 'index.php' ) as $guard ) {
					wp_delete_file( $folder . '/' . $guard );
				}
				@rmdir( $folder ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Only removes the folder once empty.
			}
		}
	}

	/**
	 * Admin download URL of a stored file.
	 *
	 * @param int $request_id Request ID.
	 * @param int $index      File index.
	 * @return string
	 */
	public static function url( int $request_id, int $index ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'mira_commission_file',
					'request' => $request_id,
					'file'    => $index,
				),
				admin_url( 'admin-post.php' )
			),
			'mira_commission_file_' . $request_id
		);
	}

	/**
	 * Streams a stored file to an authorised user.
	 *
	 * @return void
	 */
	public function download(): void {
		$request_id = isset( $_GET['request'] ) ? absint( $_GET['request'] ) : 0;
		$index      = isset( $_GET['file'] ) ? absint( $_GET['file'] ) : 0;

		check_admin_referer( 'mira_commission_file_' . $request_id );

		if ( PostType::POST_TYPE !== get_post_type( $request_id ) || ! current_user_can( 'edit_post', $request_id ) ) {
			wp_die( esc_html__( 'You are not allowed to see this file.', 'mira-studio' ), 403 );
		}

		$files = get_post_meta( $request_id, PostType::META_FILES, true );
		$file  = is_array( $files ) ? ( $files[ $index ] ?? null ) : null;
		$path  = $file ? self::resolve( (string) $file['path'] ) : '';

		if ( ! $path || ! in_array( $file['type'], self::MIMES, true ) ) {
			wp_die( esc_html__( 'File not found.', 'mira-studio' ), 404 );
		}

		nocache_headers();
		header( 'Content-Type: ' . $file['type'] );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $file['name'] ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'" );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming a private file.
		exit;
	}

	/**
	 * Absolute path of a stored file, refusing anything outside the private folder.
	 *
	 * @param string $relative Relative path.
	 * @return string Empty when invalid.
	 */
	private static function resolve( string $relative ): string {
		if ( ! preg_match( '#^\d+-[A-Za-z0-9]{16}/[A-Za-z0-9]{24}\.(jpg|png|webp)$#', $relative ) ) {
			return '';
		}

		$path = self::base_dir() . '/' . $relative;

		return is_file( $path ) ? $path : '';
	}

	/**
	 * Private folder, created and protected on first use.
	 *
	 * @return string
	 */
	private static function base_dir(): string {
		$dir = wp_upload_dir( null, false )['basedir'] . '/' . self::DIR;

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		self::protect( $dir );

		return $dir;
	}

	/**
	 * Denies web access to a folder (Apache 2.2 and 2.4) and hides its listing.
	 *
	 * @param string $dir Folder.
	 * @return void
	 */
	private static function protect( string $dir ): void {
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Tiny guard file.
			file_put_contents( $dir . '/.htaccess', "# Atelier Mira: private commission references.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n" );
		}
		if ( ! file_exists( $dir . '/index.php' ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Tiny guard file.
			file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
		}
	}
}
