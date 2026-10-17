<?php
/**
 * Portfolio card: the artwork and a short wall label.
 *
 * Arguments: `index` (position in the grid, the first ones load eagerly) and `hidden`
 * (filtered out on a series page, revealed by the in-page filter).
 *
 * @package AtelierMira\Theme
 */

$atelier_mira_index   = (int) ( $args['index'] ?? 99 );
$atelier_mira_hidden  = ! empty( $args['hidden'] );
$atelier_mira_artwork = atelier_mira_artwork( (int) get_the_ID() );
$atelier_mira_series  = wp_list_pluck( $atelier_mira_artwork['series'], 'slug' );
?>
<li class="art-card" data-series="<?php echo esc_attr( implode( ' ', $atelier_mira_series ) ); ?>"<?php echo $atelier_mira_hidden ? ' hidden' : ''; ?>>
	<a class="art-card__link" href="<?php the_permalink(); ?>">
		<span class="art-card__frame">
			<?php echo atelier_mira_image( (int) get_post_thumbnail_id(), 'large', atelier_mira_card_sizes(), false, array( 'loading' => $atelier_mira_index < 3 && ! $atelier_mira_hidden ? 'eager' : 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?>
		</span>
		<span class="art-card__label">
			<span class="art-card__title"><?php the_title(); ?></span>
			<span class="art-card__meta">
				<?php
				echo esc_html(
					implode(
						' · ',
						array_filter(
							array(
								$atelier_mira_artwork['year'] ? (string) $atelier_mira_artwork['year'] : '',
								$atelier_mira_artwork['series'] ? $atelier_mira_artwork['series'][0]->name : '',
							)
						)
					)
				);
				?>
			</span>
		</span>
	</a>
</li>
