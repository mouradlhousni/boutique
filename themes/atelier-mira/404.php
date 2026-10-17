<?php
/**
 * Not found.
 *
 * @package AtelierMira\Theme
 */

get_header();
?>
<div class="page-wrap not-found">
	<p class="eyebrow"><?php esc_html_e( 'Error 404', 'atelier-mira' ); ?></p>
	<h1 class="page-title"><?php esc_html_e( 'This wall is empty', 'atelier-mira' ); ?></h1>
	<p class="lede"><?php esc_html_e( 'The page you are looking for was moved, sold or never painted.', 'atelier-mira' ); ?></p>
	<p class="button-row">
		<a class="button" href="<?php echo esc_url( get_post_type_archive_link( 'artwork' ) ? get_post_type_archive_link( 'artwork' ) : home_url( '/' ) ); ?>"><?php esc_html_e( 'See the portfolio', 'atelier-mira' ); ?></a>
		<a class="button button--quiet" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the home page', 'atelier-mira' ); ?></a>
	</p>
</div>
<?php
get_footer();
