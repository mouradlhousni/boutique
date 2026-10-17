<?php
/**
 * Site header.
 *
 * @package AtelierMira\Theme
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#faf8f3">
	<script>document.documentElement.classList.add('js');</script>
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?><?php atelier_mira_body_attributes(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#content"><?php esc_html_e( 'Skip to content', 'atelier-mira' ); ?></a>

<header class="site-header">
	<div class="site-header__inner">
		<a class="wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<span class="wordmark__name">Atelier Mira</span>
			<span class="wordmark__tagline"><?php echo esc_html( get_bloginfo( 'description' ) ); ?></span>
		</a>

		<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" data-nav-toggle>
			<span class="nav-toggle__bars" aria-hidden="true"></span>
			<span class="nav-toggle__label"><?php esc_html_e( 'Menu', 'atelier-mira' ); ?></span>
		</button>

		<nav id="primary-nav" class="primary-nav" aria-label="<?php esc_attr_e( 'Main', 'atelier-mira' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'primary-nav__list',
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>

		<?php
		/**
		 * Header actions (the cart button is added by inc/woocommerce.php).
		 */
		do_action( 'atelier_mira_header_actions' );
		?>
	</div>
</header>

<main id="content" class="site-main" tabindex="-1">
