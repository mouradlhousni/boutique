<?php
/**
 * Demo content of the seed command.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Cli;

defined( 'ABSPATH' ) || exit;

/**
 * Static demo data: series, artworks, prints and editions, customers.
 */
final class SeedData {

	/**
	 * Series: slug => [name, description, style, accent, palettes].
	 *
	 * Palette roles depend on the style (see ImageGenerator).
	 *
	 * @return array<string, array{name:string, description:string, style:string, accent:string, palettes:array<int, array<int, string>>}>
	 */
	public static function series(): array {
		return array(
			'tidal-hours'            => array(
				'name'        => 'Tidal Hours',
				'description' => 'Gouache seascapes painted at the same harbour wall at different hours of the day: the sea as a clock, the sun as its hand.',
				'style'       => 'tidal',
				'accent'      => '#c4532f',
				'palettes'    => array(
					array( '#f6c9a8', '#f3e6d3', '#e8613c', '#3f7f8c', '#2c5d6e', '#7fb3b5', '#fbf3e6' ),
					array( '#a9c6d6', '#eef0e6', '#f2b13c', '#2f5d7c', '#1f3f5b', '#6f9bb3', '#ffffff' ),
					array( '#f2a07b', '#f6d7b8', '#fff1d6', '#c0573e', '#8c3b35', '#e6926b', '#fff4e3' ),
					array( '#d7d3e8', '#f3ece4', '#f08a5d', '#56718e', '#3a4f6a', '#9db0c4', '#fbf7f0' ),
				),
			),
			'garden-of-small-things' => array(
				'name'        => 'Garden of Small Things',
				'description' => 'Cut-paper botanicals from a balcony garden: stems, seed heads and volunteer flowers, layered like a herbarium that refuses to stay flat.',
				'style'       => 'garden',
				'accent'      => '#5c7f4f',
				'palettes'    => array(
					array( '#f3ead9', '#e9c9b5', '#5c7f4f', '#8aa66b', '#d9734e', '#e8a3a0', '#2d2a26' ),
					array( '#eef1e8', '#cfe0d2', '#3f6b4f', '#7c9e6a', '#f2b33d', '#e46f5d', '#23302a' ),
					array( '#f6efe6', '#f2d4c4', '#6b7f3a', '#9fb06a', '#b04a5a', '#f19c79', '#2f2a2a' ),
					array( '#f1ece2', '#d9d4e8', '#4f6f5c', '#86a38e', '#7b5ea7', '#e9b949', '#272431' ),
				),
			),
			'night-cartography'      => array(
				'name'        => 'Night Cartography',
				'description' => 'Imaginary survey maps of the city after dark: contour lines for the quiet, constellations for the street lamps.',
				'style'       => 'night',
				'accent'      => '#b8892f',
				'palettes'    => array(
					array( '#141b33', '#2b2d55', '#e3b75b', '#8f9bd1', '#1d2142' ),
					array( '#0f2a2e', '#1f4b4a', '#f0c674', '#7fb7a8', '#14343a' ),
					array( '#2a1633', '#4a2a4f', '#f2a65a', '#c59ac7', '#341c3e' ),
					array( '#10141f', '#24304a', '#d9d2b0', '#7c8fb0', '#161d2c' ),
				),
			),
		);
	}

	/**
	 * Artworks: [title, series, year, technique, dimensions, orientation, palette, text].
	 *
	 * @return array<int, array{0:string, 1:string, 2:int, 3:string, 4:string, 5:string, 6:int, 7:string}>
	 */
	public static function artworks(): array {
		return array(
			array( 'Morning Swell', 'tidal-hours', 2024, 'Gouache on cotton paper', '40 × 50 cm', 'portrait', 0, 'Painted at 7 a.m. from the end of the harbour wall, while the fishing boats were still out. The swell arrives in layers, each one a slightly different blue.' ),
			array( 'Low Tide, Late Sun', 'tidal-hours', 2023, 'Gouache on cotton paper', '50 × 40 cm', 'landscape', 2, 'The tide pulls back and leaves the sky to do the talking. Apricot and rust, painted in one sitting before the light went.' ),
			array( 'The Lighthouse Keeps Time', 'tidal-hours', 2024, 'Gouache and ink', '30 × 30 cm', 'square', 1, 'A study of rhythm: the beam every twelve seconds, the waves every nine. Somewhere in between, a painting.' ),
			array( 'Salt and Apricot', 'tidal-hours', 2022, 'Risograph, three colours', '30 × 40 cm', 'portrait', 2, 'First printed for a zine about summer jobs by the sea. Three inks, slightly misregistered on purpose.' ),
			array( 'Slack Water', 'tidal-hours', 2025, 'Gouache on cotton paper', '60 × 45 cm', 'landscape', 3, 'The moment between tides when nothing moves. Lavender sky, steel water, one long breath.' ),
			array( 'Harbour Blue', 'tidal-hours', 2023, 'Digital painting, archival giclée', '40 × 50 cm', 'portrait', 1, 'A winter harbour in four blues and one stubborn sun.' ),
			array( 'Ebb', 'tidal-hours', 2025, 'Gouache on cotton paper', '30 × 30 cm', 'square', 3, 'A small painting about letting things go out.' ),
			array( 'Small Hours in the Greenhouse', 'garden-of-small-things', 2024, 'Cut paper collage', '40 × 50 cm', 'portrait', 0, 'Seedlings leaning towards a lamp that is not the sun. Cut from hand-painted papers and assembled without glue until the very end.' ),
			array( 'Seed Catalogue No. 3', 'garden-of-small-things', 2023, 'Risograph, four colours', '50 × 40 cm', 'landscape', 1, 'An imaginary page from a seed catalogue for plants that only grow on balconies.' ),
			array( 'Marigold Weather', 'garden-of-small-things', 2024, 'Cut paper collage', '30 × 30 cm', 'square', 2, 'Marigolds in late September, when the light turns the colour of the flowers.' ),
			array( 'Pressed Between Pages', 'garden-of-small-things', 2022, 'Ink and watercolour', '30 × 40 cm', 'portrait', 3, 'Flowers found in an old dictionary, redrawn at full bloom.' ),
			array( 'Volunteer Poppies', 'garden-of-small-things', 2025, 'Cut paper collage', '60 × 45 cm', 'landscape', 1, 'Nobody planted them. They came anyway, every year, through the cracks in the terrace.' ),
			array( 'Window Box', 'garden-of-small-things', 2023, 'Gouache on cotton paper', '40 × 50 cm', 'portrait', 2, 'A very small garden with very large ambitions.' ),
			array( 'The Fern Room', 'garden-of-small-things', 2025, 'Cut paper collage', '30 × 30 cm', 'square', 0, 'Commissioned for a botanical café, then kept by the artist. The café got a different painting.' ),
			array( 'Map of a Quiet Street', 'night-cartography', 2024, 'Ink and gold leaf on black paper', '40 × 50 cm', 'portrait', 0, 'The street where the studio is, surveyed at 2 a.m.: one lamp, two cats, a great deal of silence.' ),
			array( 'Elevation at Midnight', 'night-cartography', 2023, 'Screen print, two colours', '50 × 40 cm', 'landscape', 1, 'Contour lines for the hills behind the city, drawn from memory after a night walk.' ),
			array( 'Lantern Contours', 'night-cartography', 2024, 'Digital painting, archival giclée', '30 × 30 cm', 'square', 2, 'A festival seen from above: every lantern a peak, every alley a valley.' ),
			array( 'Where the River Bends', 'night-cartography', 2022, 'Ink and gouache on black paper', '30 × 40 cm', 'portrait', 3, 'The river is not on the map. It is the shape the map makes around it.' ),
			array( 'Moon over the Survey Line', 'night-cartography', 2025, 'Screen print, three colours', '60 × 45 cm', 'landscape', 0, 'Surveyors used the moon to correct their instruments. Here the moon gets to correct the map.' ),
			array( 'Isobars for Insomniacs', 'night-cartography', 2025, 'Ink and gold leaf on black paper', '30 × 30 cm', 'square', 1, 'A weather map of a sleepless night: high pressure over the pillow, scattered thoughts towards dawn.' ),
		);
	}

	/**
	 * Print formats: slug => [name, width mm, height mm, unframed price, framed price].
	 *
	 * @return array<string, array{0:string, 1:int, 2:int, 3:int, 4:int}>
	 */
	public static function formats(): array {
		return array(
			'a4' => array( 'A4', 210, 297, 35, 80 ),
			'a3' => array( 'A3', 297, 420, 55, 120 ),
			'a2' => array( 'A2', 420, 594, 85, 180 ),
		);
	}

	/**
	 * Frame options: slug => name.
	 *
	 * @return array<string, string>
	 */
	public static function frames(): array {
		return array(
			'unframed' => 'Unframed',
			'framed'   => 'Black oak frame',
		);
	}

	/**
	 * Shop products, keyed by SKU: [artwork title, edition size (0 = open print), edition format, edition price, text].
	 *
	 * Open prints become variable products (format × frame); editions are simple products.
	 *
	 * @return array<string, array{0:string, 1:int, 2:string, 3:int, 4:string}>
	 */
	public static function products(): array {
		return array(
			'MS-PRINT' => array( 'Morning Swell', 0, '', 0, 'Giclée print on 310 g cotton rag paper, archival pigment inks. Shipped flat, with a 1 cm white border for framing.' ),
			'SA-PRINT' => array( 'Salt and Apricot', 0, '', 0, 'Reprinted on the studio risograph in three inks: no two prints are exactly alike.' ),
			'SC-PRINT' => array( 'Seed Catalogue No. 3', 0, '', 0, 'Giclée print on 310 g cotton rag paper. The colours of the original cut paper, reproduced as closely as paper allows.' ),
			'MW-PRINT' => array( 'Marigold Weather', 0, '', 0, 'Giclée print on 310 g cotton rag paper, archival pigment inks.' ),
			'WB-PRINT' => array( 'Window Box', 0, '', 0, 'Giclée print on 310 g cotton rag paper, archival pigment inks.' ),
			'MQ-PRINT' => array( 'Map of a Quiet Street', 0, '', 0, 'Giclée print on smooth black-toned paper; the gold leaf of the original is reproduced with a metallic-effect ink.' ),
			'LC-PRINT' => array( 'Lantern Contours', 0, '', 0, 'Giclée print on 310 g cotton rag paper, archival pigment inks.' ),
			'WR-PRINT' => array( 'Where the River Bends', 0, '', 0, 'Giclée print on smooth black-toned paper, archival pigment inks.' ),
			'SW-ED50'  => array( 'Slack Water', 50, 'a2', 240, 'Limited edition giclée of 50, signed and numbered by hand on the front, with a certificate of authenticity.' ),
			'VP-ED30'  => array( 'Volunteer Poppies', 30, 'a2', 220, 'Limited edition giclée of 30 on Hahnemühle Photo Rag, signed and numbered, with a certificate of authenticity.' ),
			'MO-ED20'  => array( 'Moon over the Survey Line', 20, 'a3', 160, 'Hand-pulled screen print in three colours, edition of 20. Each print shows the small irregularities of the process.' ),
			'IS-AP8'   => array( 'Isobars for Insomniacs', 8, 'a3', 380, 'Artist proof edition of 8 with hand-applied gold leaf: each print is finished by hand and slightly unique.' ),
		);
	}

	/**
	 * Demo customers: [first name, last name, city, postcode, country].
	 *
	 * @return array<int, array{0:string, 1:string, 2:string, 3:string, 4:string}>
	 */
	public static function customers(): array {
		return array(
			array( 'Lou', 'Martin', 'Lyon', '69002', 'FR' ),
			array( 'Ana', 'Ribeiro', 'Porto', '4000-322', 'PT' ),
			array( 'Tomás', 'Herrera', 'Valencia', '46001', 'ES' ),
			array( 'Ines', 'Fischer', 'Berlin', '10115', 'DE' ),
			array( 'Sam', 'Okafor', 'Brussels', '1000', 'BE' ),
			array( 'Yuki', 'Tanaka', 'Amsterdam', '1012 AB', 'NL' ),
			array( 'Clara', 'Dubois', 'Marseille', '13001', 'FR' ),
			array( 'Noor', 'Haddad', 'Milan', '20121', 'IT' ),
		);
	}

	/**
	 * Demo orders: [customer, status, payment method, days ago, lines, gift message].
	 * A line is [SKU, quantity, format, frame] (format and frame only for open prints).
	 *
	 * The plan sells 12/50, 7/30, 3/20 and 8/8 edition prints (the last edition sells out),
	 * and cancels one order to show numbers being released and reissued.
	 *
	 * @return array<int, array{0:int, 1:string, 2:string, 3:int, 4:array<int, array<int, mixed>>, 5:string}>
	 */
	public static function orders(): array {
		return array(
			array( 0, 'completed', 'cod', 58, array( array( 'SW-ED50', 3 ), array( 'IS-AP8', 2 ) ), '' ),
			array( 1, 'completed', 'cheque', 51, array( array( 'MS-PRINT', 1, 'a3', 'framed' ), array( 'VP-ED30', 2 ) ), 'For Rita, who taught me to swim. Happy 70th!' ),
			array( 2, 'completed', 'cod', 44, array( array( 'SW-ED50', 2 ), array( 'IS-AP8', 3 ), array( 'MO-ED20', 1 ) ), '' ),
			array( 3, 'cancelled', 'cod', 37, array( array( 'SW-ED50', 1 ), array( 'SC-PRINT', 1, 'a4', 'unframed' ) ), '' ),
			array( 4, 'completed', 'cod', 30, array( array( 'SW-ED50', 4 ), array( 'VP-ED30', 1 ), array( 'IS-AP8', 1 ) ), '' ),
			array( 5, 'completed', 'cheque', 21, array( array( 'MQ-PRINT', 2, 'a2', 'unframed' ), array( 'VP-ED30', 3 ) ), 'To our new home. Love, Y & K' ),
			array( 6, 'processing', 'cod', 9, array( array( 'SW-ED50', 1 ), array( 'IS-AP8', 2 ), array( 'MO-ED20', 2 ) ), '' ),
			array( 7, 'processing', 'cod', 4, array( array( 'SW-ED50', 2 ), array( 'VP-ED30', 1 ), array( 'LC-PRINT', 1, 'a3', 'framed' ) ), 'Thank you for everything this year. – N.' ),
			array( 3, 'on-hold', 'cheque', 1, array( array( 'MW-PRINT', 1, 'a4', 'framed' ), array( 'WB-PRINT', 1, 'a4', 'framed' ) ), '' ),
		);
	}

	/**
	 * Image size (px) for an orientation.
	 *
	 * @param string $orientation portrait|landscape|square.
	 * @return array{0:int, 1:int}
	 */
	public static function size( string $orientation ): array {
		switch ( $orientation ) {
			case 'landscape':
				return array( 1600, 1200 );
			case 'square':
				return array( 1400, 1400 );
			default:
				return array( 1280, 1600 );
		}
	}

	/**
	 * Alt text for a generated artwork.
	 *
	 * @param string $title     Artwork title.
	 * @param string $style     Series style.
	 * @param string $technique Technique.
	 * @return string
	 */
	public static function alt( string $title, string $style, string $technique ): string {
		$subjects = array(
			'tidal'  => 'an abstract seascape with a round sun over layered waves',
			'garden' => 'cut-paper flowers and leaves on curved stems',
			'night'  => 'gold contour lines, stars and a crescent moon on a dark sky',
		);

		return sprintf( '%s – %s (%s)', $title, $subjects[ $style ] ?? 'an abstract composition', strtolower( $technique ) );
	}
}
