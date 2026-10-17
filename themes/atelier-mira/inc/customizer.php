<?php
/**
 * Customizer: the home page photos (hero background, studio).
 *
 * `wp mira seed` fills both theme mods from the demo images when they are provided
 * (plugins/mira-studio/assets/demo-images/); without them the home page falls back to
 * artworks (see atelier_mira_hero_image_id() and atelier_mira_studio_images()).
 *
 * @package AtelierMira\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Home page" section.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 * @return void
 */
function atelier_mira_customize_register( WP_Customize_Manager $wp_customize ): void {
	$wp_customize->add_section(
		'atelier_mira_home',
		array(
			'title'    => __( 'Home page', 'atelier-mira' ),
			'priority' => 30,
		)
	);

	$photos = array(
		'atelier_mira_hero_image'   => array(
			__( 'Hero photo', 'atelier-mira' ),
			__( 'Full-width photo behind the title, ideally an interior or the studio (landscape, at least 2000 px wide). Without one, the featured artwork is used.', 'atelier-mira' ),
		),
		'atelier_mira_studio_image' => array(
			__( 'Studio photo', 'atelier-mira' ),
			__( 'Shown in the "About the studio" section. Without one, an artwork is used.', 'atelier-mira' ),
		),
	);

	foreach ( $photos as $id => list( $label, $description ) ) {
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => 0,
				'sanitize_callback' => 'absint',
				'capability'        => 'edit_theme_options',
			)
		);

		$wp_customize->add_control(
			new WP_Customize_Media_Control(
				$wp_customize,
				$id,
				array(
					'label'       => $label,
					'description' => $description,
					'section'     => 'atelier_mira_home',
					'mime_type'   => 'image',
				)
			)
		);
	}
}
add_action( 'customize_register', 'atelier_mira_customize_register' );
