<?php
/**
 * Commission form replacement in the static export.
 *
 * @package AtelierMira\Theme
 */

?>
<div class="static-commission">
	<p><?php esc_html_e( 'On the live site, this is where you describe your project: subject, size, budget, ideal deadline and up to three reference images. Every request lands in the studio’s commission inbox, where it moves from New to Quoted, then Accepted or Declined.', 'atelier-mira' ); ?></p>
	<ul>
		<li><?php esc_html_e( 'An answer within a week, with questions and an estimate.', 'atelier-mira' ); ?></li>
		<li><?php esc_html_e( 'Two rounds of sketches before any paint touches paper.', 'atelier-mira' ); ?></li>
		<li><?php esc_html_e( 'Reference images are checked, cleaned and kept private.', 'atelier-mira' ); ?></li>
	</ul>
	<p><a class="button" href="<?php echo esc_url( atelier_mira_live_url() ); ?>" rel="noopener"><?php esc_html_e( 'Try the request form in the live demo', 'atelier-mira' ); ?></a></p>
</div>
