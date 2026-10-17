<?php
/**
 * Portfolio: every artwork in a masonry grid, filterable by series without reloading.
 *
 * Also used for series pages (/series/<slug>/), which open with that series selected.
 * The filters are real links, so the page works without JavaScript and in the static export;
 * portfolio.js turns them into in-page filters.
 *
 * @package AtelierMira\Theme
 */

get_header();

$atelier_mira_current = is_tax( 'artwork_series' ) ? get_queried_object() : null;
$atelier_mira_intro   = __( 'Three ongoing series in gouache, cut paper and ink. Most pieces exist as prints; originals are occasionally available on request.', 'atelier-mira' );
$atelier_mira_heading = __( 'Selected work, 2022–2025', 'atelier-mira' );

/*
 * Series pages list the whole portfolio too (other series hidden), so switching filters
 * never needs the server: query every artwork when the main query holds only one series.
 */
$atelier_mira_works = $atelier_mira_current
	? new WP_Query(
		array(
			'post_type'      => 'artwork',
			'posts_per_page' => 100,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
			'no_found_rows'  => true,
		)
	)
	: $GLOBALS['wp_query'];

$atelier_mira_count = $atelier_mira_current ? (int) $atelier_mira_current->count : (int) $atelier_mira_works->post_count;
?>
<div class="portfolio">
	<header class="page-head section__head--center">
		<p class="eyebrow eyebrow--rule"><span><?php esc_html_e( 'Portfolio', 'atelier-mira' ); ?></span><span><?php esc_html_e( 'Original works', 'atelier-mira' ); ?></span></p>
		<h1 class="page-title" data-series-title><?php echo esc_html( $atelier_mira_current ? $atelier_mira_current->name : $atelier_mira_heading ); ?></h1>
		<p class="lede" data-series-description><?php echo esc_html( $atelier_mira_current ? $atelier_mira_current->description : $atelier_mira_intro ); ?></p>
	</header>

	<nav class="filters" aria-label="<?php esc_attr_e( 'Filter by series', 'atelier-mira' ); ?>" data-filters>
		<ul class="filters__list">
			<li>
				<a class="filters__link" href="<?php echo esc_url( (string) get_post_type_archive_link( 'artwork' ) ); ?>" data-filter="" data-title="<?php echo esc_attr( $atelier_mira_heading ); ?>" data-description="<?php echo esc_attr( $atelier_mira_intro ); ?>"<?php echo $atelier_mira_current ? '' : ' aria-current="page"'; ?>>
					<?php esc_html_e( 'All', 'atelier-mira' ); ?>
				</a>
			</li>
			<?php foreach ( atelier_mira_series() as $atelier_mira_term ) : ?>
				<li>
					<a class="filters__link" href="<?php echo esc_url( (string) get_term_link( $atelier_mira_term ) ); ?>" data-filter="<?php echo esc_attr( $atelier_mira_term->slug ); ?>" data-title="<?php echo esc_attr( $atelier_mira_term->name ); ?>" data-description="<?php echo esc_attr( $atelier_mira_term->description ); ?>"<?php echo $atelier_mira_current && $atelier_mira_current->term_id === $atelier_mira_term->term_id ? ' aria-current="page"' : ''; ?>>
						<?php echo esc_html( $atelier_mira_term->name ); ?>
						<span class="filters__count"><?php echo esc_html( (string) $atelier_mira_term->count ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="filters__status" role="status" aria-live="polite" data-filter-status>
			<?php
			/* translators: %d: number of works. */
			echo esc_html( sprintf( _n( '%d work', '%d works', $atelier_mira_count, 'atelier-mira' ), $atelier_mira_count ) );
			?>
		</p>
	</nav>

	<ul class="art-grid" data-art-grid<?php echo $atelier_mira_current ? ' data-initial="' . esc_attr( $atelier_mira_current->slug ) . '"' : ''; ?>>
		<?php
		$atelier_mira_index = 0;
		while ( $atelier_mira_works->have_posts() ) :
			$atelier_mira_works->the_post();
			$atelier_mira_terms = wp_list_pluck( atelier_mira_artwork( (int) get_the_ID() )['series'], 'slug' );
			get_template_part(
				'template-parts/artwork-card',
				null,
				array(
					'index'  => $atelier_mira_index++,
					'hidden' => $atelier_mira_current && ! in_array( $atelier_mira_current->slug, $atelier_mira_terms, true ),
				)
			);
		endwhile;
		wp_reset_postdata();
		?>
	</ul>
</div>
<?php
get_footer();
