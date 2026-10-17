<?php
/**
 * Site footer.
 *
 * @package AtelierMira\Theme
 */

?>
</main>

<footer class="site-footer">
	<div class="site-footer__inner">
		<div class="site-footer__studio">
			<p class="wordmark__name">Atelier Mira</p>
			<p><?php esc_html_e( 'Illustration, prints and commissions from a small studio in Marseille. Every print is made, checked and packed by hand.', 'atelier-mira' ); ?></p>
		</div>
		<nav class="site-footer__nav" aria-labelledby="footer-nav-title">
			<h2 id="footer-nav-title" class="site-footer__title"><?php esc_html_e( 'Explore', 'atelier-mira' ); ?></h2>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'site-footer__list',
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
		<div class="site-footer__visit">
			<h2 class="site-footer__title"><?php esc_html_e( 'Visit the studio', 'atelier-mira' ); ?></h2>
			<ul class="site-footer__contact">
				<li><?php esc_html_e( 'Rue de la Loge, Marseille (by appointment)', 'atelier-mira' ); ?></li>
				<li><?php esc_html_e( 'Tuesday to Saturday, 10 am – 6 pm', 'atelier-mira' ); ?></li>
				<li><?php esc_html_e( 'Prints ship worldwide from Marseille', 'atelier-mira' ); ?></li>
			</ul>
		</div>
		<p class="site-footer__small">
			<?php
			/* translators: %s: year. */
			echo esc_html( sprintf( __( '© %s Atelier Mira · A fictional studio, built as a WordPress portfolio project.', 'atelier-mira' ), gmdate( 'Y' ) ) );
			?>
		</p>
	</div>
</footer>

<?php
/**
 * After the footer (the cart drawer is added by inc/woocommerce.php).
 */
do_action( 'atelier_mira_after_footer' );
wp_footer();
?>
</body>
</html>
