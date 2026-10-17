<?php
/**
 * Procedural artwork images (no external downloads, no hotlinking).
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Cli;

defined( 'ABSPATH' ) || exit;

/**
 * Paints abstract compositions with GD, one visual language per series:
 *
 * - `tidal`: gradient skies, a haloed sun and layered sine-wave bands;
 * - `garden`: cut-paper botanicals (stems, lens-shaped leaves, flowers) on paper;
 * - `night`: deep gradients, wobbly contour rings, stars and a crescent moon.
 *
 * A seeded PRNG makes every artwork reproducible. Images are drawn at twice the size
 * and downsampled for cheap anti-aliasing, then saved as WebP when GD supports it
 * (GdImage objects are freed when they go out of scope).
 */
final class ImageGenerator {

	private const SCALE = 2;

	/**
	 * Canvas (supersampled).
	 *
	 * @var \GdImage
	 */
	private $img;

	/**
	 * Canvas width (supersampled).
	 *
	 * @var int
	 */
	private int $w = 0;

	/**
	 * Canvas height (supersampled).
	 *
	 * @var int
	 */
	private int $h = 0;

	/**
	 * PRNG state.
	 *
	 * @var int
	 */
	private int $state = 1;

	/**
	 * Whether this server can write WebP.
	 *
	 * @return bool
	 */
	public static function supports_webp(): bool {
		return function_exists( 'imagewebp' ) && ( imagetypes() & IMG_WEBP );
	}

	/**
	 * File extension used for generated images.
	 *
	 * @return string
	 */
	public static function extension(): string {
		return self::supports_webp() ? 'webp' : 'jpg';
	}

	/**
	 * Paints an artwork and writes it to disk.
	 *
	 * @param string            $style   tidal|garden|night.
	 * @param array<int,string> $palette Hex colours (see the style methods for the roles).
	 * @param int               $seed    PRNG seed.
	 * @param int               $width   Output width.
	 * @param int               $height  Output height.
	 * @param string            $file    Target path without extension.
	 * @return string Written file path.
	 */
	public function artwork( string $style, array $palette, int $seed, int $width, int $height, string $file ): string {
		$this->state = max( 1, $seed * 7919 );
		$this->w     = $width * self::SCALE;
		$this->h     = $height * self::SCALE;
		$this->img   = imagecreatetruecolor( $this->w, $this->h );
		imagealphablending( $this->img, true );

		switch ( $style ) {
			case 'garden':
				$this->garden( $palette );
				break;
			case 'night':
				$this->night( $palette );
				break;
			default:
				$this->tidal( $palette );
		}

		$out = imagecreatetruecolor( $width, $height );
		imagecopyresampled( $out, $this->img, 0, 0, 0, 0, $width, $height, $this->w, $this->h );

		return $this->write( $out, $file );
	}

	/**
	 * The print framed on a wall, for the product gallery.
	 *
	 * @param string $source Artwork file.
	 * @param string $wall   Wall colour.
	 * @param string $frame  Frame colour.
	 * @param int    $size   Square output size.
	 * @param string $file   Target path without extension.
	 * @return string Written file path.
	 */
	public function mockup( string $source, string $wall, string $frame, int $size, string $file ): string {
		$art = $this->load( $source );
		$aw  = imagesx( $art );
		$ah  = imagesy( $art );
		$img = imagecreatetruecolor( $size, $size );
		imagealphablending( $img, true );

		// Wall with a soft light falloff, floor line.
		$this->gradient_on( $img, 0, 0, $size, (int) ( $size * 0.86 ), $this->lighten( $wall, 0.12 ), $wall );
		imagefilledrectangle( $img, 0, (int) ( $size * 0.86 ), $size, $size, $this->color_on( $img, $this->darken( $wall, 0.28 ) ) );

		// Frame box fitted to the artwork ratio.
		$max_w = $size * 0.56;
		$max_h = $size * 0.62;
		$ratio = $aw / $ah;
		$fw    = $ratio >= $max_w / $max_h ? $max_w : $max_h * $ratio;
		$fh    = $fw / $ratio;
		$x     = (int) ( ( $size - $fw ) / 2 );
		$y     = (int) ( $size * 0.42 - $fh / 2 );
		$fw    = (int) $fw;
		$fh    = (int) $fh;

		// Shadow.
		for ( $i = 14; $i > 0; $i-- ) {
			imagefilledrectangle( $img, $x - $i + 10, $y - $i + 16, $x + $fw + $i + 10, $y + $fh + $i + 16, $this->color_on( $img, '#000000', 124 ) );
		}

		$border = (int) max( 8, $fw * 0.035 );
		$mat    = (int) max( 14, $fw * 0.09 );
		imagefilledrectangle( $img, $x, $y, $x + $fw, $y + $fh, $this->color_on( $img, $frame ) );
		imagefilledrectangle( $img, $x + $border, $y + $border, $x + $fw - $border, $y + $fh - $border, $this->color_on( $img, '#f7f4ee' ) );
		imagecopyresampled( $img, $art, $x + $mat, $y + $mat, 0, 0, $fw - 2 * $mat, $fh - 2 * $mat, $aw, $ah );

		return $this->write( $img, $file );
	}

	/**
	 * A gallery room with framed works on the wall, for the home hero when no photo is
	 * provided: warm wall lit by spotlights, oak floor, a bench.
	 *
	 * @param array<int,string> $sources Artwork files (the first three are hung, largest in the middle).
	 * @param int               $width   Output width.
	 * @param int               $height  Output height.
	 * @param string            $file    Target path without extension.
	 * @return string Written file path.
	 */
	public function room( array $sources, int $width, int $height, string $file ): string {
		$img   = imagecreatetruecolor( $width, $height );
		$floor = (int) ( $height * 0.8 );
		imagealphablending( $img, true );

		// Wall, darker towards the floor; floor with plank lines and a skirting board.
		$this->gradient_on( $img, 0, 0, $width, $floor, '#d8cdb9', '#b9aa90' );
		$this->gradient_on( $img, 0, $floor, $width, $height, '#7a5a3c', '#4a3524' );
		for ( $y = $floor + 14; $y < $height; $y += (int) max( 8, ( $y - $floor ) * 0.35 ) ) {
			imageline( $img, 0, $y, $width, $y, $this->color_on( $img, '#2e2016', 100 ) );
		}
		imagefilledrectangle( $img, 0, $floor - 10, $width, $floor, $this->color_on( $img, '#efe8da' ) );

		// Three works: centre one larger, all sharing the same eye line.
		$slots = array(
			array( 0.5, 0.36 ),
			array( 0.2, 0.22 ),
			array( 0.8, 0.22 ),
		);

		foreach ( array_slice( $sources, 0, 3 ) as $index => $source ) {
			list( $cx, $scale ) = $slots[ $index ];

			$art   = $this->load( $source );
			$ratio = imagesx( $art ) / imagesy( $art );
			$fh    = (int) ( $height * $scale * ( $ratio > 1 ? 0.8 : 1.15 ) );
			$fw    = (int) ( $fh * $ratio );
			$x     = (int) ( $width * $cx - $fw / 2 );
			$y     = (int) ( $height * 0.42 - $fh / 2 );

			// Spotlight: a soft pool of light above and on the work.
			for ( $r = 9; $r > 0; $r-- ) {
				imagefilledellipse( $img, (int) ( $width * $cx ), $y + (int) ( $fh * 0.3 ), (int) ( $fw * ( 1.2 + $r * 0.18 ) ), (int) ( $fh * ( 1.3 + $r * 0.2 ) ), $this->color_on( $img, '#fff8ea', 118 ) );
			}

			// Shadow, frame, mat, work.
			for ( $i = 12; $i > 0; $i-- ) {
				imagefilledrectangle( $img, $x - $i + 8, $y - $i + 14, $x + $fw + $i + 8, $y + $fh + $i + 14, $this->color_on( $img, '#000000', 122 ) );
			}
			$border = (int) max( 5, $fw * 0.03 );
			$mat    = (int) max( 10, $fw * 0.08 );
			imagefilledrectangle( $img, $x, $y, $x + $fw, $y + $fh, $this->color_on( $img, '#2b2724' ) );
			imagefilledrectangle( $img, $x + $border, $y + $border, $x + $fw - $border, $y + $fh - $border, $this->color_on( $img, '#f7f4ee' ) );
			imagecopyresampled( $img, $art, $x + $mat, $y + $mat, 0, 0, $fw - 2 * $mat, $fh - 2 * $mat, imagesx( $art ), imagesy( $art ) );
		}

		// Bench in front of the centre work.
		$bx = (int) ( $width * 0.36 );
		$bw = (int) ( $width * 0.28 );
		$by = (int) ( $height * 0.84 );
		imagefilledrectangle( $img, $bx + 10, $by + 28, $bx + $bw + 10, $by + 34, $this->color_on( $img, '#000000', 90 ) );
		imagefilledrectangle( $img, $bx, $by, $bx + $bw, $by + (int) ( $height * 0.03 ), $this->color_on( $img, '#2a211b' ) );
		foreach ( array( $bx + 12, $bx + $bw - 24 ) as $leg ) {
			imagefilledrectangle( $img, $leg, $by, $leg + 12, $by + (int) ( $height * 0.09 ), $this->color_on( $img, '#1c1612' ) );
		}

		return $this->write( $img, $file );
	}

	/**
	 * Copies a provided image (JPEG, PNG or WebP) into uploads, at most `$max` px on its
	 * longest side, in the format the generated images use.
	 *
	 * @param string $source Provided file.
	 * @param int    $max    Longest side.
	 * @param string $file   Target path without extension.
	 * @return string Written file path.
	 * @throws \RuntimeException When the file is not a readable image.
	 */
	public function import( string $source, int $max, string $file ): string {
		$data = file_get_contents( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		$img  = false !== $data ? imagecreatefromstring( $data ) : false;

		if ( ! $img ) {
			throw new \RuntimeException( esc_html( "Cannot read {$source}" ) );
		}

		$w     = imagesx( $img );
		$h     = imagesy( $img );
		$ratio = min( 1, $max / max( $w, $h ) );

		if ( $ratio < 1 ) {
			$out = imagecreatetruecolor( (int) round( $w * $ratio ), (int) round( $h * $ratio ) );
			imagecopyresampled( $out, $img, 0, 0, 0, 0, imagesx( $out ), imagesy( $out ), $w, $h );
			$img = $out;
		}

		return $this->write( $img, $file );
	}

	/**
	 * A zoomed-in crop of an artwork, for galleries.
	 *
	 * @param string $source Artwork file.
	 * @param int    $size   Square output size.
	 * @param int    $seed   Seed for the crop position.
	 * @param string $file   Target path without extension.
	 * @return string Written file path.
	 */
	public function detail( string $source, int $size, int $seed, string $file ): string {
		$this->state = max( 1, $seed * 104729 );
		$art         = $this->load( $source );
		$aw          = imagesx( $art );
		$ah          = imagesy( $art );
		$crop        = (int) ( min( $aw, $ah ) * 0.45 );
		$cx          = (int) ( ( $aw - $crop ) * $this->between( 0.2, 0.8 ) );
		$cy          = (int) ( ( $ah - $crop ) * $this->between( 0.2, 0.8 ) );
		$img         = imagecreatetruecolor( $size, $size );

		imagecopyresampled( $img, $art, 0, 0, $cx, $cy, $size, $size, $crop, $crop );

		return $this->write( $img, $file );
	}

	// Styles.

	/**
	 * Seascape: [sky top, sky bottom, sun, wave 1, wave 2, wave 3, foam].
	 *
	 * @param array<int,string> $p Palette.
	 * @return void
	 */
	private function tidal( array $p ): void {
		$horizon = (int) ( $this->h * $this->between( 0.48, 0.62 ) );
		imagefilledrectangle( $this->img, 0, $horizon, $this->w, $this->h, $this->color( $p[1] ) );
		$this->gradient( 0, 0, $this->w, $horizon, $p[0], $p[1] );

		// Sun with a halo.
		$sx = (int) ( $this->w * $this->between( 0.25, 0.75 ) );
		$sy = (int) ( $horizon * $this->between( 0.35, 0.7 ) );
		$sr = (int) ( min( $this->w, $this->h ) * $this->between( 0.1, 0.17 ) );
		for ( $i = 6; $i > 0; $i-- ) {
			$this->circle( $sx, $sy, (int) ( $sr * ( 1 + $i * 0.18 ) ), $p[2], 112 + $i * 2 );
		}
		$this->circle( $sx, $sy, $sr, $p[2] );

		// Wave bands, back to front.
		$bands = 5;
		for ( $b = 0; $b < $bands; $b++ ) {
			$top   = $horizon + (int) ( ( $this->h - $horizon ) * $b / ( $bands + 0.4 ) );
			$amp   = $this->h * $this->between( 0.008, 0.03 ) * ( 1 + $b * 0.4 );
			$freq  = $this->between( 1.5, 4.5 ) / $this->w * M_PI * 2;
			$phase = $this->between( 0, M_PI * 2 );
			$color = $p[ 3 + ( $b % 3 ) ];
			$color = $b > 2 ? $this->darken( $color, 0.12 * ( $b - 2 ) ) : $color;

			$points = array();
			$crest  = array();
			for ( $x = 0; $x <= $this->w; $x += 16 ) {
				$y        = $top + $amp * sin( $x * $freq + $phase ) + $amp * 0.35 * sin( $x * $freq * 2.7 + $phase * 1.3 );
				$points[] = $x;
				$points[] = (int) $y;
				$crest[]  = array( $x, (int) $y );
			}
			array_push( $points, $this->w, $this->h, 0, $this->h );
			imagefilledpolygon( $this->img, $points, $this->color( $color ) );

			if ( $b % 2 ) {
				imagesetthickness( $this->img, 2 * self::SCALE );
				$foam = $this->color( $p[6], 70 );
				$prev = null;
				foreach ( $crest as $point ) {
					if ( $prev ) {
						imageline( $this->img, $prev[0], $prev[1] + 6, $point[0], $point[1] + 6, $foam );
					}
					$prev = $point;
				}
				imagesetthickness( $this->img, 1 );
			}
		}
	}

	/**
	 * Botanical cut-paper: [paper, blob, green 1, green 2, flower 1, flower 2, ink].
	 *
	 * @param array<int,string> $p Palette.
	 * @return void
	 */
	private function garden( array $p ): void {
		imagefilledrectangle( $this->img, 0, 0, $this->w, $this->h, $this->color( $p[0] ) );
		$unit = min( $this->w, $this->h );

		// Large background shapes.
		$this->circle( (int) ( $this->w * $this->between( 0.3, 0.7 ) ), (int) ( $this->h * $this->between( 0.3, 0.55 ) ), (int) ( $unit * $this->between( 0.28, 0.4 ) ), $p[1] );
		imagefilledrectangle( $this->img, 0, (int) ( $this->h * 0.82 ), $this->w, $this->h, $this->color( $this->darken( $p[1], 0.08 ), 40 ) );

		$stems = (int) $this->between( 3, 6 );
		for ( $s = 0; $s < $stems; $s++ ) {
			$base_x = $this->w * ( 0.15 + 0.7 * ( $s + $this->between( 0.2, 0.8 ) ) / $stems );
			$top_x  = $base_x + $this->w * $this->between( -0.15, 0.15 );
			$top_y  = $this->h * $this->between( 0.12, 0.45 );
			$bend   = $this->w * $this->between( -0.18, 0.18 );
			$green  = $p[ 2 + ( $s % 2 ) ];

			// Quadratic stem, drawn as a thick polyline.
			imagesetthickness( $this->img, (int) ( $unit * 0.008 ) );
			$prev = null;
			$path = array();
			for ( $i = 0; $i <= 24; $i++ ) {
				$t      = $i / 24;
				$x      = ( 1 - $t ) ** 2 * $base_x + 2 * ( 1 - $t ) * $t * ( $base_x + $bend ) + $t ** 2 * $top_x;
				$y      = ( 1 - $t ) ** 2 * $this->h + 2 * ( 1 - $t ) * $t * ( $this->h * 0.6 ) + $t ** 2 * $top_y;
				$path[] = array( $x, $y );
				if ( $prev ) {
					imageline( $this->img, (int) $prev[0], (int) $prev[1], (int) $x, (int) $y, $this->color( $this->darken( $green, 0.15 ) ) );
				}
				$prev = array( $x, $y );
			}
			imagesetthickness( $this->img, 1 );

			// Leaves along the stem, alternating sides.
			$leaves = (int) $this->between( 3, 7 );
			for ( $l = 0; $l < $leaves; $l++ ) {
				$point = $path[ (int) ( 4 + ( 18 * $l / $leaves ) ) ];
				$side  = $l % 2 ? 1 : -1;
				$angle = -M_PI / 2 + $side * $this->between( 0.6, 1.2 );
				$len   = $unit * $this->between( 0.1, 0.18 );
				$this->leaf( $point[0] + cos( $angle ) * $len / 2, $point[1] + sin( $angle ) * $len / 2, $len, $len * $this->between( 0.3, 0.45 ), $angle, $green );
			}

			// Flower or seed head on top.
			$petals = (int) $this->between( 5, 9 );
			$size   = $unit * $this->between( 0.05, 0.09 );
			$color  = $p[ 4 + ( $s % 2 ) ];
			for ( $i = 0; $i < $petals; $i++ ) {
				$angle = $i / $petals * M_PI * 2;
				$this->leaf( $top_x + cos( $angle ) * $size, $top_y + sin( $angle ) * $size, $size * 2, $size * 0.9, $angle, $color );
			}
			$this->circle( (int) $top_x, (int) $top_y, (int) ( $size * 0.55 ), $p[6] );
		}

		// Confetti dots.
		for ( $i = 0; $i < 40; $i++ ) {
			$this->circle( (int) ( $this->w * $this->rand() ), (int) ( $this->h * $this->rand() ), (int) ( $unit * $this->between( 0.004, 0.012 ) ), $p[ 4 + $i % 3 ], 30 );
		}
	}

	/**
	 * Night map: [bg top, bg bottom, gold, line, moon shadow].
	 *
	 * @param array<int,string> $p Palette.
	 * @return void
	 */
	private function night( array $p ): void {
		$this->gradient( 0, 0, $this->w, $this->h, $p[0], $p[1] );
		$unit = min( $this->w, $this->h );

		// Stars.
		for ( $i = 0; $i < 140; $i++ ) {
			$this->circle( (int) ( $this->w * $this->rand() ), (int) ( $this->h * $this->rand() ), (int) ( $unit * $this->between( 0.0015, 0.004 ) ), '#ffffff', (int) $this->between( 20, 90 ) );
		}

		// Contour rings around one or two centres.
		imagesetthickness( $this->img, 2 * self::SCALE );
		$centres = (int) $this->between( 1, 3 );
		for ( $c = 0; $c < $centres; $c++ ) {
			$cx    = $this->w * $this->between( 0.2, 0.8 );
			$cy    = $this->h * $this->between( 0.3, 0.8 );
			$rings = (int) $this->between( 7, 13 );
			$ph1   = $this->between( 0, 6.28 );
			$ph2   = $this->between( 0, 6.28 );
			for ( $r = 1; $r <= $rings; $r++ ) {
				$radius = $unit * 0.035 * $r;
				$points = array();
				for ( $i = 0; $i < 96; $i++ ) {
					$a        = $i / 96 * M_PI * 2;
					$k        = 1 + 0.09 * sin( 3 * $a + $ph1 + $r * 0.3 ) + 0.05 * sin( 5 * $a + $ph2 );
					$points[] = (int) ( $cx + cos( $a ) * $radius * $k );
					$points[] = (int) ( $cy + sin( $a ) * $radius * $k * 0.85 );
				}
				imagepolygon( $this->img, $points, $this->color( 0 === $r % 4 ? $p[2] : $p[3], 0 === $r % 4 ? 30 : 75 ) );
			}
		}

		// A constellation.
		$stars = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$stars[] = array( (int) ( $this->w * $this->between( 0.1, 0.9 ) ), (int) ( $this->h * $this->between( 0.08, 0.45 ) ) );
		}
		imagesetthickness( $this->img, self::SCALE );
		$count = count( $stars );
		for ( $i = 1; $i < $count; $i++ ) {
			imageline( $this->img, $stars[ $i - 1 ][0], $stars[ $i - 1 ][1], $stars[ $i ][0], $stars[ $i ][1], $this->color( $p[2], 80 ) );
		}
		imagesetthickness( $this->img, 1 );
		foreach ( $stars as $star ) {
			$this->circle( $star[0], $star[1], (int) ( $unit * 0.008 ), $p[2] );
		}

		// Crescent moon.
		$mx = (int) ( $this->w * $this->between( 0.15, 0.85 ) );
		$my = (int) ( $this->h * $this->between( 0.12, 0.3 ) );
		$mr = (int) ( $unit * $this->between( 0.06, 0.1 ) );
		$this->circle( $mx, $my, (int) ( $mr * 1.6 ), $p[2], 118 );
		$this->circle( $mx, $my, $mr, $p[2] );
		$this->circle( $mx + (int) ( $mr * 0.45 ), $my - (int) ( $mr * 0.2 ), (int) ( $mr * 0.92 ), $this->mix( $p[0], $p[1], $my / $this->h ) );
	}

	// Drawing helpers.

	/**
	 * Lens-shaped leaf or petal.
	 *
	 * @param float  $cx     Centre x.
	 * @param float  $cy     Centre y.
	 * @param float  $length Length.
	 * @param float  $width  Width.
	 * @param float  $angle  Angle (radians).
	 * @param string $color  Colour.
	 * @return void
	 */
	private function leaf( float $cx, float $cy, float $length, float $width, float $angle, string $color ): void {
		$points = array();
		$cos    = cos( $angle );
		$sin    = sin( $angle );
		$steps  = 14;

		foreach ( array( 1, -1 ) as $side ) {
			for ( $i = 0; $i <= $steps; $i++ ) {
				$t        = 1 === $side ? $i / $steps : 1 - $i / $steps;
				$u        = ( $t - 0.5 ) * $length;
				$v        = $side * $width / 2 * sin( M_PI * $t );
				$points[] = (int) ( $cx + $u * $cos - $v * $sin );
				$points[] = (int) ( $cy + $u * $sin + $v * $cos );
			}
		}

		imagefilledpolygon( $this->img, $points, $this->color( $color ) );
	}

	/**
	 * Filled circle.
	 *
	 * @param int    $x      Centre x.
	 * @param int    $y      Centre y.
	 * @param int    $radius Radius.
	 * @param string $color  Colour.
	 * @param int    $alpha  GD alpha (0 opaque – 127 transparent).
	 * @return void
	 */
	private function circle( int $x, int $y, int $radius, string $color, int $alpha = 0 ): void {
		imagefilledellipse( $this->img, $x, $y, 2 * $radius, 2 * $radius, $this->color( $color, $alpha ) );
	}

	/**
	 * Vertical gradient on the canvas.
	 *
	 * @param int    $x1   Left.
	 * @param int    $y1   Top.
	 * @param int    $x2   Right.
	 * @param int    $y2   Bottom.
	 * @param string $from Top colour.
	 * @param string $to   Bottom colour.
	 * @return void
	 */
	private function gradient( int $x1, int $y1, int $x2, int $y2, string $from, string $to ): void {
		$this->gradient_on( $this->img, $x1, $y1, $x2, $y2, $from, $to );
	}

	/**
	 * Vertical gradient on any image.
	 *
	 * @param \GdImage $img  Image.
	 * @param int      $x1   Left.
	 * @param int      $y1   Top.
	 * @param int      $x2   Right.
	 * @param int      $y2   Bottom.
	 * @param string   $from Top colour.
	 * @param string   $to   Bottom colour.
	 * @return void
	 */
	private function gradient_on( $img, int $x1, int $y1, int $x2, int $y2, string $from, string $to ): void {
		$a      = $this->rgb( $from );
		$b      = $this->rgb( $to );
		$height = max( 1, $y2 - $y1 );

		for ( $y = $y1; $y <= $y2; $y++ ) {
			$t = ( $y - $y1 ) / $height;
			$c = imagecolorallocate( $img, (int) ( $a[0] + ( $b[0] - $a[0] ) * $t ), (int) ( $a[1] + ( $b[1] - $a[1] ) * $t ), (int) ( $a[2] + ( $b[2] - $a[2] ) * $t ) );
			imageline( $img, $x1, $y, $x2, $y, $c );
		}
	}

	/**
	 * Colour on the canvas.
	 *
	 * @param string $hex   Colour.
	 * @param int    $alpha GD alpha.
	 * @return int
	 */
	private function color( string $hex, int $alpha = 0 ): int {
		return $this->color_on( $this->img, $hex, $alpha );
	}

	/**
	 * Colour on any image.
	 *
	 * @param \GdImage $img   Image.
	 * @param string   $hex   Colour.
	 * @param int      $alpha GD alpha.
	 * @return int
	 */
	private function color_on( $img, string $hex, int $alpha = 0 ): int {
		list( $r, $g, $b ) = $this->rgb( $hex );

		return imagecolorallocatealpha( $img, $r, $g, $b, max( 0, min( 127, $alpha ) ) );
	}

	/**
	 * Hex to RGB.
	 *
	 * @param string $hex Colour.
	 * @return array{0:int, 1:int, 2:int}
	 */
	private function rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );

		return array( (int) hexdec( substr( $hex, 0, 2 ) ), (int) hexdec( substr( $hex, 2, 2 ) ), (int) hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * Darker shade.
	 *
	 * @param string $hex    Colour.
	 * @param float  $amount 0–1.
	 * @return string
	 */
	private function darken( string $hex, float $amount ): string {
		return vsprintf( '#%02x%02x%02x', array_map( static fn( int $c ) => (int) ( $c * ( 1 - $amount ) ), $this->rgb( $hex ) ) );
	}

	/**
	 * Colour between two others.
	 *
	 * @param string $from Colour at 0.
	 * @param string $to   Colour at 1.
	 * @param float  $t    Position.
	 * @return string
	 */
	private function mix( string $from, string $to, float $t ): string {
		$a = $this->rgb( $from );
		$b = $this->rgb( $to );

		return sprintf( '#%02x%02x%02x', (int) ( $a[0] + ( $b[0] - $a[0] ) * $t ), (int) ( $a[1] + ( $b[1] - $a[1] ) * $t ), (int) ( $a[2] + ( $b[2] - $a[2] ) * $t ) );
	}

	/**
	 * Lighter tint.
	 *
	 * @param string $hex    Colour.
	 * @param float  $amount 0–1.
	 * @return string
	 */
	private function lighten( string $hex, float $amount ): string {
		return vsprintf( '#%02x%02x%02x', array_map( static fn( int $c ) => (int) ( $c + ( 255 - $c ) * $amount ), $this->rgb( $hex ) ) );
	}

	/**
	 * Opens a generated image.
	 *
	 * @param string $file Path.
	 * @return \GdImage
	 * @throws \RuntimeException When the file cannot be decoded.
	 */
	private function load( string $file ) {
		$img = str_ends_with( $file, '.webp' ) ? imagecreatefromwebp( $file ) : imagecreatefromjpeg( $file );

		if ( ! $img ) {
			throw new \RuntimeException( esc_html( "Cannot read {$file}" ) );
		}

		return $img;
	}

	/**
	 * Writes an image as WebP (or JPEG) and frees it.
	 *
	 * @param \GdImage $img  Image.
	 * @param string   $file Path without extension.
	 * @return string Path.
	 */
	private function write( $img, string $file ): string {
		$path = $file . '.' . self::extension();

		if ( self::supports_webp() ) {
			imagewebp( $img, $path, 82 );
		} else {
			imagejpeg( $img, $path, 84 );
		}

		return $path;
	}

	/**
	 * Deterministic float in [0, 1).
	 *
	 * @return float
	 */
	private function rand(): float {
		$this->state = ( $this->state * 1103515245 + 12345 ) & 0x7fffffff;

		return $this->state / 0x80000000;
	}

	/**
	 * Deterministic float in [min, max).
	 *
	 * @param float $min Minimum.
	 * @param float $max Maximum.
	 * @return float
	 */
	private function between( float $min, float $max ): float {
		return $min + ( $max - $min ) * $this->rand();
	}
}
