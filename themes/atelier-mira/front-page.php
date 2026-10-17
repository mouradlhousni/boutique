<?php
/**
 * Home page: a full-bleed hero photo with the featured print, the series (page content,
 * built with the "Artwork Series Showcase" block), new prints on the night band, the studio
 * and the newsletter.
 *
 * @package AtelierMira\Theme
 */

get_header();

$atelier_mira_hero  = atelier_mira_hero_artwork();
$atelier_mira_photo = atelier_mira_hero_image_id();
?>

<?php if ( $atelier_mira_photo ) : ?>
	<section class="hero" aria-labelledby="hero-title">
		<figure class="hero__media">
			<?php echo atelier_mira_image( $atelier_mira_photo, 'mira-xl', '100vw', true, array( 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?>
		</figure>

		<div class="hero__inner">
			<div class="hero__text">
				<p class="eyebrow eyebrow--rule"><span><?php esc_html_e( 'Atelier Mira', 'atelier-mira' ); ?></span><span><?php esc_html_e( 'Marseille', 'atelier-mira' ); ?></span></p>
				<h1 id="hero-title" class="title-stagger hero__title">
					<span><?php esc_html_e( 'Tides, gardens', 'atelier-mira' ); ?></span>
					<span class="title-stagger__second"><?php esc_html_e( 'and cities after dark', 'atelier-mira' ); ?></span>
				</h1>
				<p class="hero__lede"><?php esc_html_e( 'Painted slowly in a small studio by the Vieux-Port, printed on cotton rag paper, signed and numbered by hand.', 'atelier-mira' ); ?></p>
				<p class="button-row">
					<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
						<a class="button button--light" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Shop prints', 'atelier-mira' ); ?></a>
					<?php endif; ?>
					<a class="button button--ghost-light" href="<?php echo esc_url( (string) get_post_type_archive_link( 'artwork' ) ); ?>"><?php esc_html_e( 'See the portfolio', 'atelier-mira' ); ?></a>
				</p>
			</div>

			<?php if ( $atelier_mira_hero ) : ?>
				<?php $atelier_mira_hero_art = atelier_mira_artwork( $atelier_mira_hero ); ?>
				<aside class="hero__card" aria-label="<?php esc_attr_e( 'Featured work', 'atelier-mira' ); ?>">
					<a class="hero__card-art" href="<?php echo esc_url( get_permalink( $atelier_mira_hero ) ); ?>" tabindex="-1" aria-hidden="true">
						<?php echo atelier_mira_image( (int) get_post_thumbnail_id( $atelier_mira_hero ), 'medium_large', '(min-width: 900px) 240px, 104px', false, array( 'loading' => 'eager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?>
					</a>
					<div>
						<p class="eyebrow">
							<?php
							echo $atelier_mira_hero_art['series']
								/* translators: %s: series name. */
								? esc_html( sprintf( __( 'From %s', 'atelier-mira' ), $atelier_mira_hero_art['series'][0]->name ) )
								: esc_html__( 'Featured work', 'atelier-mira' );
							?>
						</p>
						<?php atelier_mira_wall_label( $atelier_mira_hero, 'h2' ); ?>
						<p class="hero__card-foot">
							<?php if ( $atelier_mira_hero_art['product_id'] ) : ?>
								<span><?php echo esc_html( atelier_mira_price_from( $atelier_mira_hero_art['product_id'] ) ); ?></span>
								<a href="<?php echo esc_url( get_permalink( $atelier_mira_hero_art['product_id'] ) ); ?>"><?php esc_html_e( 'Buy a print', 'atelier-mira' ); ?></a>
							<?php else : ?>
								<a href="<?php echo esc_url( get_permalink( $atelier_mira_hero ) ); ?>"><?php esc_html_e( 'See the work', 'atelier-mira' ); ?></a>
							<?php endif; ?>
						</p>
					</div>
				</aside>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>

<?php
while ( have_posts() ) :
	the_post();
	if ( '' !== trim( get_the_content() ) ) :
		?>
		<div class="home-content entry-content">
			<?php the_content(); ?>
		</div>
		<?php
	endif;
endwhile;
?>

<?php if ( function_exists( 'wc_get_products' ) ) : ?>
	<section class="section section--night" aria-labelledby="new-prints-title">
		<div class="section__head">
			<div>
				<p class="eyebrow eyebrow--rule"><span><?php esc_html_e( 'New', 'atelier-mira' ); ?></span><span><?php esc_html_e( 'In the shop', 'atelier-mira' ); ?></span></p>
				<h2 id="new-prints-title" class="title-stagger">
					<span><?php esc_html_e( 'Prints that speak', 'atelier-mira' ); ?></span>
					<span class="title-stagger__second"><?php esc_html_e( 'a thousand words', 'atelier-mira' ); ?></span>
				</h2>
			</div>
			<a class="button button--ghost-light" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'All prints and editions', 'atelier-mira' ); ?></a>
		</div>
		<?php echo do_shortcode( '[products limit="4" columns="4" orderby="date" order="DESC"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce output. ?>
	</section>
<?php endif; ?>

<section class="section studio" aria-labelledby="studio-title">
	<?php $atelier_mira_studio = atelier_mira_studio_images(); ?>
	<?php if ( $atelier_mira_studio ) : ?>
		<div class="studio__media">
			<?php foreach ( $atelier_mira_studio as $atelier_mira_index => $atelier_mira_image ) : ?>
				<figure class="float">
					<?php echo atelier_mira_image( $atelier_mira_image, 'large', 0 === $atelier_mira_index ? '(min-width: 900px) 480px, 90vw' : '(min-width: 900px) 200px, 38vw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?>
				</figure>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<div class="studio__text">
		<p class="eyebrow eyebrow--rule"><span><?php esc_html_e( 'About', 'atelier-mira' ); ?></span><span><?php esc_html_e( 'The studio', 'atelier-mira' ); ?></span></p>
		<h2 id="studio-title" class="title-stagger">
			<span><?php esc_html_e( 'One table by the window,', 'atelier-mira' ); ?></span>
			<span class="title-stagger__second"><?php esc_html_e( 'a lot of paper', 'atelier-mira' ); ?></span>
		</h2>
		<p class="studio__lead"><?php esc_html_e( 'Mira paints in gouache, cuts paper and draws in ink in a small studio near the Vieux-Port.', 'atelier-mira' ); ?></p>
		<p><?php esc_html_e( 'Prints are made in the studio on cotton rag paper; editions are signed and numbered one by one, and every parcel is packed by hand.', 'atelier-mira' ); ?></p>
		<dl class="studio__facts">
			<div><dt><?php esc_html_e( 'Based in', 'atelier-mira' ); ?></dt><dd><?php esc_html_e( 'Marseille, France', 'atelier-mira' ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Works in', 'atelier-mira' ); ?></dt><dd><?php esc_html_e( 'Gouache, cut paper, ink, screen print', 'atelier-mira' ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Prints', 'atelier-mira' ); ?></dt><dd><?php esc_html_e( 'Giclée on 310 g cotton rag, archival inks', 'atelier-mira' ); ?></dd></div>
			<div><dt><?php esc_html_e( 'Commissions', 'atelier-mira' ); ?></dt><dd><?php esc_html_e( 'Four to six a year, booked in advance', 'atelier-mira' ); ?></dd></div>
		</dl>
		<?php $atelier_mira_about = get_page_by_path( 'about' ); ?>
		<?php if ( $atelier_mira_about ) : ?>
			<a class="link-arrow" href="<?php echo esc_url( get_permalink( $atelier_mira_about ) ); ?>"><?php esc_html_e( 'More about the studio', 'atelier-mira' ); ?></a>
		<?php endif; ?>
	</div>
</section>

<?php get_template_part( 'template-parts/newsletter' ); ?>

<?php
get_footer();
