<?php
/**
 * Newsletter sign-up (demo only: nothing is sent or stored, the script just thanks).
 *
 * @package AtelierMira\Theme
 */

?>
<section class="section newsletter" aria-labelledby="newsletter-title">
	<div class="newsletter__inner">
		<p class="eyebrow eyebrow--rule"><span><?php esc_html_e( 'Four a year', 'atelier-mira' ); ?></span><span><?php esc_html_e( 'Newsletter', 'atelier-mira' ); ?></span></p>
		<h2 id="newsletter-title" class="section__title"><?php esc_html_e( 'Studio letters', 'atelier-mira' ); ?></h2>
		<p><?php esc_html_e( 'Four letters a year: new series, edition launches and a look at the work table. No other emails, ever.', 'atelier-mira' ); ?></p>
		<form class="newsletter__form" action="#newsletter-title" method="get" data-newsletter>
			<label class="screen-reader-text" for="newsletter-email"><?php esc_html_e( 'Email address', 'atelier-mira' ); ?></label>
			<span class="newsletter__field">
				<input type="email" id="newsletter-email" name="newsletter" required autocomplete="email" placeholder="<?php esc_attr_e( 'you@example.com', 'atelier-mira' ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Subscribe', 'atelier-mira' ); ?></button>
			</span>
			<p class="newsletter__status" role="status" aria-live="polite" data-newsletter-status></p>
		</form>
	</div>
</section>
