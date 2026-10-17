<?php
/**
 * Demo content seeder.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Cli;

use AtelierMira\Studio\Portfolio\PostTypes;
use AtelierMira\Studio\Shop\Edition;
use AtelierMira\Studio\Shop\PrintFormats;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the demo content. Every created object carries the `_mira_seed` marker,
 * so running the seed again first removes exactly what the previous run created.
 */
final class Seeder {

	public const MARKER = '_mira_seed';

	/**
	 * Longest side of a provided image once imported (keeps the Playground seed fast).
	 */
	private const PROVIDED_MAX = 2400;

	/**
	 * Unique artwork images to paint; artworks beyond reuse them (keeps Playground fast).
	 *
	 * @var int
	 */
	private int $images;

	/**
	 * Progress logger.
	 *
	 * @var callable
	 */
	private $log;

	/**
	 * Image painter.
	 *
	 * @var ImageGenerator
	 */
	private ImageGenerator $painter;

	/**
	 * Series term IDs by slug.
	 *
	 * @var array<string, int>
	 */
	private array $series = array();

	/**
	 * Created artworks: [id, title, series slug, image ID, image file].
	 *
	 * @var array<int, array{id:int, title:string, series:string, image:int, file:string}>
	 */
	private array $artworks = array();

	/**
	 * Created products by SKU.
	 *
	 * @var array<string, \WC_Product>
	 */
	private array $products = array();

	/**
	 * Constructor.
	 *
	 * @param int      $images Unique images.
	 * @param callable $log    Receives progress messages.
	 */
	public function __construct( int $images, callable $log ) {
		$this->images  = max( 1, $images );
		$this->log     = $log;
		$this->painter = new ImageGenerator();
	}

	/**
	 * Runs every step.
	 *
	 * @return void
	 * @throws \Throwable When a step fails (its transaction is rolled back first).
	 */
	public function run(): void {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/post.php';

		// Seeding is a batch job: no term counting per insert, and no email at all (orders, stock alerts).
		wp_defer_term_counting( true );
		add_filter( 'pre_wp_mail', '__return_true' );

		// Image sizes the theme relies on (also set on theme activation), before any upload.
		update_option( 'large_size_w', 1200 );
		update_option( 'large_size_h', 1200 );

		// Light seed (Playground): skip the two intermediate srcset steps, the theme's
		// templates only request thumbnail, WooCommerce and large sizes.
		if ( $this->images < 12 ) {
			add_filter( 'intermediate_image_sizes_advanced', array( $this, 'light_sizes' ) );
		}

		$steps = array( 'reset', 'series', 'artworks' );
		if ( mira_has_woocommerce() ) {
			foreach ( WC()->mailer()->get_emails() as $email ) {
				add_filter( 'woocommerce_email_enabled_' . $email->id, '__return_false' );
			}
			array_push( $steps, 'store', 'attributes', 'products', 'orders' );
		}
		array_push( $steps, 'pages', 'photos' );

		/*
		 * One transaction per step: thousands of small writes are committed at once, which
		 * makes the seed about twice as fast on WordPress Playground's SQLite (and helps MySQL too).
		 */
		global $wpdb;
		foreach ( $steps as $step ) {
			$start = microtime( true );
			$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Batch import.
			try {
				$this->$step();
			} catch ( \Throwable $error ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Batch import.
				throw $error;
			}
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Batch import.
			( $this->log )( sprintf( '%-10s %5.1fs', $step, microtime( true ) - $start ) );
		}

		remove_filter( 'pre_wp_mail', '__return_true' );
		wp_defer_term_counting( false );
		wp_cache_flush();
		flush_rewrite_rules();
	}

	/**
	 * Removes everything a previous run created.
	 *
	 * @return void
	 */
	private function reset(): void {
		if ( mira_has_woocommerce() ) {
			$orders = wc_get_orders(
				array(
					'limit'       => -1,
					'status'      => 'any',
					'created_via' => 'mira-seed',
				)
			);
			foreach ( $orders as $order ) {
				$order->delete( true );
			}
		}

		$posts = get_posts(
			array(
				'post_type'      => array( PostTypes::POST_TYPE, 'product', 'page', 'post', 'nav_menu_item', 'commission_request' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off CLI cleanup.
				'meta_key'       => self::MARKER,
			)
		);
		$posts = array_merge(
			$posts,
			get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off CLI cleanup.
					'meta_key'       => self::MARKER,
				)
			)
		);

		foreach ( array_unique( $posts ) as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		foreach ( array( PostTypes::TAXONOMY, 'product_cat', 'nav_menu' ) as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off CLI cleanup.
					'meta_key'   => self::MARKER,
				)
			);
			foreach ( is_array( $terms ) ? $terms : array() as $term_id ) {
				wp_delete_term( (int) $term_id, $taxonomy );
			}
		}
	}

	/**
	 * Artwork series.
	 *
	 * @return void
	 */
	private function series(): void {
		$order = 0;

		foreach ( SeedData::series() as $slug => $series ) {
			$term = term_exists( $slug, PostTypes::TAXONOMY );
			$term = $term ? $term : wp_insert_term(
				$series['name'],
				PostTypes::TAXONOMY,
				array(
					'slug'        => $slug,
					'description' => $series['description'],
				)
			);

			if ( is_wp_error( $term ) ) {
				continue;
			}

			$term_id               = (int) $term['term_id'];
			$this->series[ $slug ] = $term_id;
			update_term_meta( $term_id, 'mira_accent', $series['accent'] );
			update_term_meta( $term_id, 'mira_order', ++$order );
			update_term_meta( $term_id, self::MARKER, 1 );
		}
	}

	/**
	 * Portfolio artworks with generated images and a detail shot in the gallery.
	 *
	 * @return void
	 */
	private function artworks(): void {
		$series = SeedData::series();
		$images = array();

		foreach ( SeedData::artworks() as $index => list( $title, $slug, $year, $technique, $dimensions, $orientation, $palette, $text ) ) {
			$style = $series[ $slug ]['style'];

			$provided = $this->provided( sprintf( 'artwork-%02d', $index + 1 ) );

			if ( $provided ) {
				$file     = $this->painter->import( $provided, self::PROVIDED_MAX, $this->upload_path( $title ) );
				$image_id = $this->attach( $file, $title, SeedData::alt( $title, $style, $technique ) );
				$images[] = array( $image_id, $file );
			} elseif ( $index < $this->images ) {
				list( $width, $height ) = SeedData::size( $orientation );

				$file     = $this->painter->artwork( $style, $series[ $slug ]['palettes'][ $palette ], $index + 1, $width, $height, $this->upload_path( $title ) );
				$image_id = $this->attach( $file, $title, SeedData::alt( $title, $style, $technique ) );
				$images[] = array( $image_id, $file );
			} else {
				list( $image_id, $file ) = $images[ $index % count( $images ) ];
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => PostTypes::POST_TYPE,
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_excerpt' => $text,
					'post_content' => "<!-- wp:paragraph -->\n<p>" . esc_html( $text ) . "</p>\n<!-- /wp:paragraph -->",
					'menu_order'   => $index,
					// Spread publication dates so "latest" listings look natural.
					'post_date'    => gmdate( 'Y-m-d H:i:s', strtotime( "{$year}-01-01" ) + $index * DAY_IN_SECONDS * 11 ),
					'meta_input'   => array(
						'_mira_year'       => $year,
						'_mira_technique'  => $technique,
						'_mira_dimensions' => $dimensions,
						self::MARKER       => 1,
					),
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				( $this->log )( 'skip ' . $title . ': ' . $post_id->get_error_message() );
				continue;
			}

			set_post_thumbnail( $post_id, $image_id );
			wp_set_object_terms( $post_id, array( $this->series[ $slug ] ), PostTypes::TAXONOMY );

			// Gallery: a detail crop for the first artworks, then a sibling from the same series.
			$gallery = array();
			if ( $index < $this->budget( 'details' ) ) {
				$gallery[] = $this->attach( $this->painter->detail( $file, 1200, $index + 1, $this->upload_path( $title . ' detail' ) ), $title . ' (detail)', sprintf( 'Detail of %s', $title ) );
			}
			$sibling = end( $this->artworks );
			if ( $sibling && $sibling['series'] === $slug && $sibling['image'] !== $image_id ) {
				$gallery[] = $sibling['image'];
			}
			update_post_meta( $post_id, PostTypes::GALLERY_META, $gallery );

			$this->artworks[] = array(
				'id'     => (int) $post_id,
				'title'  => $title,
				'series' => $slug,
				'image'  => $image_id,
				'file'   => $file,
			);
		}
	}

	/**
	 * WooCommerce settings for a European print shop: EUR, demo payment methods,
	 * classic (shortcode) cart and checkout, flat-rate and free shipping.
	 *
	 * @return void
	 */
	private function store(): void {
		$options = array(
			'woocommerce_currency'                     => 'EUR',
			'woocommerce_currency_pos'                 => 'left',
			'woocommerce_price_thousand_sep'           => ',',
			'woocommerce_price_decimal_sep'            => '.',
			'woocommerce_price_num_decimals'           => '2',
			'woocommerce_default_country'              => 'FR:13',
			'woocommerce_store_address'                => '14 rue des Ateliers',
			'woocommerce_store_city'                   => 'Marseille',
			'woocommerce_store_postcode'               => '13001',
			'woocommerce_allowed_countries'            => 'all',
			'woocommerce_calc_taxes'                   => 'no',
			'woocommerce_manage_stock'                 => 'yes',
			'woocommerce_hold_stock_minutes'           => '30',
			'woocommerce_enable_guest_checkout'        => 'yes',
			'woocommerce_enable_reviews'               => 'no',
			'woocommerce_coming_soon'                  => 'no',
			'woocommerce_store_pages_only'             => 'no',
			'woocommerce_task_list_hidden'             => 'yes',
			'woocommerce_task_list_complete'           => 'yes',
			'woocommerce_show_marketplace_suggestions' => 'no',
			'woocommerce_allow_tracking'               => 'no',
			'woocommerce_thumbnail_cropping'           => 'uncropped',
			'woocommerce_bacs_settings'                => array( 'enabled' => 'no' ),
			'woocommerce_cod_settings'                 => array(
				'enabled'      => 'yes',
				'title'        => 'Cash on delivery',
				'description'  => 'Demo payment: pay when the parcel arrives.',
				'instructions' => 'Pay with cash upon delivery.',
			),
			'woocommerce_cheque_settings'              => array(
				'enabled'      => 'yes',
				'title'        => 'Check payment',
				'description'  => 'Demo payment: send a check to the studio.',
				'instructions' => 'Make your check payable to Atelier Mira.',
			),
		);

		foreach ( $options as $name => $value ) {
			update_option( $name, $value );
		}
		update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );

		// Classic shortcode pages: the theme customises them through hooks.
		$pages = array(
			'shop'      => array( 'Shop', '' ),
			'cart'      => array( 'Cart', '[woocommerce_cart]' ),
			'checkout'  => array( 'Checkout', '[woocommerce_checkout]' ),
			'myaccount' => array( 'My account', '[woocommerce_my_account]' ),
		);
		foreach ( $pages as $key => list( $title, $content ) ) {
			$page_id = wc_get_page_id( $key );
			if ( $page_id <= 0 || ! get_post( $page_id ) ) {
				$page_id = wp_insert_post(
					array(
						'post_type'   => 'page',
						'post_status' => 'publish',
						'post_title'  => $title,
					)
				);
				update_option( 'woocommerce_' . $key . '_page_id', $page_id );
			}
			wp_update_post(
				array(
					'ID'           => $page_id,
					'post_status'  => 'publish',
					'post_content' => $content,
				)
			);
		}

		// Shipping: everywhere (zone 0), flat rate with free shipping from €150.
		$zone    = new \WC_Shipping_Zone( 0 );
		$methods = array_map( static fn( $method ) => $method->id, $zone->get_shipping_methods() );
		if ( ! in_array( 'flat_rate', $methods, true ) ) {
			$instance = $zone->add_shipping_method( 'flat_rate' );
			update_option(
				"woocommerce_flat_rate_{$instance}_settings",
				array(
					'title'      => 'Tracked parcel',
					'tax_status' => 'none',
					'cost'       => '9',
				)
			);
		}
		if ( ! in_array( 'free_shipping', $methods, true ) ) {
			$instance = $zone->add_shipping_method( 'free_shipping' );
			update_option(
				"woocommerce_free_shipping_{$instance}_settings",
				array(
					'title'      => 'Free tracked parcel',
					'requires'   => 'min_amount',
					'min_amount' => '150',
				)
			);
		}
		\WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	/**
	 * Global "Format" (with paper sizes) and "Frame" attributes.
	 *
	 * @return void
	 */
	private function attributes(): void {
		$attributes = array(
			'format' => array( 'Format', SeedData::formats() ),
			'frame'  => array( 'Frame', SeedData::frames() ),
		);

		foreach ( $attributes as $slug => list( $label, $terms ) ) {
			$taxonomy = wc_attribute_taxonomy_name( $slug );

			if ( ! wc_attribute_taxonomy_id_by_name( $slug ) ) {
				wc_create_attribute(
					array(
						'name'         => $label,
						'slug'         => $slug,
						'type'         => 'select',
						'order_by'     => 'menu_order',
						'has_archives' => false,
					)
				);
			}

			// The taxonomy is registered on `init` from the DB: register it now for this request.
			if ( ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy( $taxonomy, array( 'product', 'product_variation' ), array( 'hierarchical' => false ) );
			}

			$order = 0;
			foreach ( $terms as $term_slug => $term ) {
				$name     = is_array( $term ) ? $term[0] : $term;
				$existing = term_exists( $term_slug, $taxonomy );
				$term_id  = $existing ? (int) $existing['term_id'] : (int) wp_insert_term( $name, $taxonomy, array( 'slug' => $term_slug ) )['term_id'];

				update_term_meta( $term_id, 'order', $order++ );
				if ( is_array( $term ) ) {
					update_term_meta( $term_id, 'mira_width_mm', $term[1] );
					update_term_meta( $term_id, 'mira_height_mm', $term[2] );
				}
			}
		}
	}

	/**
	 * Open prints (variable: format × frame) and limited editions (simple), linked to their artworks.
	 *
	 * @return void
	 */
	private function products(): void {
		$categories = array();
		foreach ( array(
			'art-prints'       => array( 'Art prints', 'Open-edition giclée and risograph prints, in three sizes, framed or not.' ),
			'limited-editions' => array( 'Limited editions', 'Small, numbered and signed editions. When they are gone, they are gone.' ),
		) as $slug => list( $name, $description ) ) {
			$term = term_exists( $slug, 'product_cat' );
			$term = $term ? $term : wp_insert_term(
				$name,
				'product_cat',
				array(
					'slug'        => $slug,
					'description' => $description,
				)
			);

			$categories[ $slug ] = (int) $term['term_id'];
			update_term_meta( $categories[ $slug ], self::MARKER, 1 );
		}

		$artworks = array_column( $this->artworks, null, 'title' );
		$series   = SeedData::series();
		$formats  = SeedData::formats();
		$index    = 0;

		foreach ( SeedData::products() as $sku => list( $title, $edition, $format, $price, $text ) ) {
			$artwork = $artworks[ $title ] ?? null;
			if ( ! $artwork ) {
				continue;
			}

			$gallery = array();
			if ( $index < $this->budget( 'mockups' ) ) {
				$style     = $series[ $artwork['series'] ]['style'];
				$wall      = array(
					'tidal'  => '#e7e2d9',
					'garden' => '#e4e6dc',
					'night'  => '#dcd8d2',
				)[ $style ];
				$frame     = 'night' === $style ? '#c9a465' : '#2b2724';
				$gallery[] = $this->attach( $this->painter->mockup( $artwork['file'], $wall, $frame, 1400, $this->upload_path( $title . ' framed' ) ), $title . ' (framed)', sprintf( '%s framed and hung on a wall', $title ) );
			}
			$gallery = array_merge( $gallery, (array) get_post_meta( $artwork['id'], PostTypes::GALLERY_META, true ) );

			$product = $edition ? new \WC_Product_Simple() : new \WC_Product_Variable();
			$product->set_name( $edition ? $title . ' – Limited Edition' : $title . ' – Art Print' );
			$product->set_sku( $sku );
			$product->set_status( 'publish' );
			$product->set_short_description( $text );
			$product->set_description( get_post_field( 'post_excerpt', $artwork['id'] ) );
			$product->set_image_id( $artwork['image'] );
			$product->set_gallery_image_ids( array_values( array_unique( array_filter( $gallery ) ) ) );
			$product->set_category_ids( array( $categories[ $edition ? 'limited-editions' : 'art-prints' ] ) );
			$product->set_menu_order( $index++ );
			$product->set_weight( '0.5' );
			$product->update_meta_data( self::MARKER, 1 );
			$product->update_meta_data( '_mira_artwork_id', $artwork['id'] );

			if ( $edition ) {
				$product->set_regular_price( (string) $price );
				$product->set_attributes( array( $this->attribute( PrintFormats::TAXONOMY, array( $format ), false ) ) );
				$product->update_meta_data( Edition::SIZE_META, $edition );
				$product->set_manage_stock( true );
				$product->set_stock_quantity( $edition );
				$product->set_backorders( 'no' );
				$product->save();
			} else {
				$product->set_attributes(
					array(
						$this->attribute( PrintFormats::TAXONOMY, array_keys( $formats ), true ),
						$this->attribute( PrintFormats::FRAME, array_keys( SeedData::frames() ), true ),
					)
				);
				$product->set_default_attributes(
					array(
						PrintFormats::TAXONOMY => 'a3',
						PrintFormats::FRAME    => 'unframed',
					)
				);
				$product->save();

				foreach ( $formats as $format_slug => $format_data ) {
					foreach ( array_keys( SeedData::frames() ) as $frame_index => $frame_slug ) {
						$variation = new \WC_Product_Variation();
						$variation->set_parent_id( $product->get_id() );
						$variation->set_attributes(
							array(
								PrintFormats::TAXONOMY => $format_slug,
								PrintFormats::FRAME    => $frame_slug,
							)
						);
						$variation->set_regular_price( (string) $format_data[ 3 + $frame_index ] );
						$variation->set_sku( $sku . '-' . strtoupper( $format_slug ) . ( $frame_index ? '-F' : '' ) );
						$variation->set_weight( $frame_index ? '3' : '0.5' );
						$variation->save();
					}
				}
				\WC_Product_Variable::sync( $product->get_id() );
			}

			update_post_meta( $artwork['id'], '_mira_product_id', $product->get_id() );
			$this->products[ $sku ] = wc_get_product( $product->get_id() );
		}
	}

	/**
	 * Demo orders through the real WooCommerce flow: status changes reduce (and, for the
	 * cancelled order, restore) the stock, which issues and releases the print numbers.
	 *
	 * @return void
	 */
	private function orders(): void {
		$customers = SeedData::customers();

		foreach ( SeedData::orders() as list( $customer, $status, $payment, $days_ago, $lines, $gift ) ) {
			list( $first, $last, $city, $postcode, $country ) = $customers[ $customer ];

			$order = wc_create_order( array( 'created_via' => 'mira-seed' ) );
			if ( is_wp_error( $order ) ) {
				continue;
			}

			foreach ( $lines as $line ) {
				$product = $this->products[ $line[0] ] ?? null;
				if ( ! $product ) {
					continue;
				}
				if ( $product->is_type( 'variable' ) ) {
					$product = wc_get_product(
						( new \WC_Product_Data_Store_CPT() )->find_matching_product_variation(
							$product,
							array(
								'attribute_' . PrintFormats::TAXONOMY => $line[2],
								'attribute_' . PrintFormats::FRAME    => $line[3],
							)
						)
					);
				}
				if ( $product ) {
					$order->add_product( $product, (int) $line[1] );
				}
			}

			$address = array(
				'first_name' => $first,
				'last_name'  => $last,
				'address_1'  => '1 Demo Street',
				'city'       => $city,
				'postcode'   => $postcode,
				'country'    => $country,
				'email'      => strtolower( remove_accents( $first . '.' . $last ) ) . '@example.com',
				'phone'      => '',
			);
			$order->set_address( $address, 'billing' );
			unset( $address['email'], $address['phone'] );
			$order->set_address( $address, 'shipping' );
			$order->set_payment_method( $payment );
			$order->set_payment_method_title( 'cod' === $payment ? 'Cash on delivery' : 'Check payment' );

			$shipping = new \WC_Order_Item_Shipping();
			$shipping->set_method_title( 'Tracked parcel' );
			$shipping->set_method_id( 'flat_rate' );
			$shipping->set_total( '9' );
			$order->add_item( $shipping );

			if ( $gift ) {
				$order->update_meta_data( '_mira_gift_message', $gift );
			}
			$order->update_meta_data( self::MARKER, 1 );
			$order->set_date_created( time() - $days_ago * DAY_IN_SECONDS - wp_rand( 0, 8 ) * HOUR_IN_SECONDS );
			$order->calculate_totals( false );
			$order->save();

			// Confirm first (stock reduced, numbers issued), then move to the final status.
			$order->update_status( 'on-hold' === $status ? 'on-hold' : 'processing' );
			if ( ! in_array( $status, array( 'processing', 'on-hold' ), true ) ) {
				$order = wc_get_order( $order->get_id() );
				$order->update_status( $status );
			}
		}
	}

	/**
	 * Product attribute built from global attribute terms.
	 *
	 * @param string            $taxonomy  Attribute taxonomy.
	 * @param array<int,string> $slugs     Term slugs.
	 * @param bool              $variation Used for variations.
	 * @return \WC_Product_Attribute
	 */
	private function attribute( string $taxonomy, array $slugs, bool $variation ): \WC_Product_Attribute {
		$attribute = new \WC_Product_Attribute();
		$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
		$attribute->set_name( $taxonomy );
		$attribute->set_options(
			array_map( static fn( string $slug ) => (int) get_term_by( 'slug', $slug, $taxonomy )->term_id, $slugs )
		);
		$attribute->set_visible( true );
		$attribute->set_variation( $variation );

		return $attribute;
	}

	/**
	 * Home, About and Commissions pages, menus, the home page artwork and image sizes.
	 *
	 * @return void
	 */
	private function pages(): void {
		$pages = array(
			'home'        => array(
				'title'   => 'Home',
				'content' => '<!-- wp:atelier-mira/series-showcase {"align":"wide"} /-->',
			),
			'about'       => array(
				'title'   => 'About the studio',
				'excerpt' => 'Mira paints tides, balcony gardens and cities after dark from a small studio in Marseille.',
				'content' => implode(
					"\n\n",
					array(
						'<!-- wp:paragraph {"className":"lede"} --><p class="lede">Atelier Mira is the studio of Mira Aubry, an illustrator who left an agency job in 2019 to paint the things she kept noticing on the way to work: the colour of the harbour at seven, the plants nobody waters, the streets after the last bus.</p><!-- /wp:paragraph -->',
						'<!-- wp:heading --><h2 class="wp-block-heading">How the work is made</h2><!-- /wp:heading -->',
						'<!-- wp:paragraph --><p>Most pieces start in a sketchbook and end in gouache on cotton paper. The garden series is cut from papers painted in advance, then assembled flat on the table for days before anything is glued. The night maps are drawn in ink on black paper, with gold leaf applied by hand.</p><!-- /wp:paragraph -->',
						'<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>I paint the same harbour wall over and over. It is never the same picture twice.</p><!-- /wp:paragraph --><cite>Mira Aubry</cite></blockquote><!-- /wp:quote -->',
						'<!-- wp:heading --><h2 class="wp-block-heading">About the prints</h2><!-- /wp:heading -->',
						'<!-- wp:paragraph --><p>Prints are made in the studio on a pigment printer, on 310 g cotton rag paper, and checked against the original under daylight lamps. Limited editions are signed and numbered by hand, and come with a certificate. Framed prints use solid oak frames from a workshop twenty minutes away.</p><!-- /wp:paragraph -->',
						'<!-- wp:heading --><h2 class="wp-block-heading">Exhibitions</h2><!-- /wp:heading -->',
						'<!-- wp:list --><ul class="wp-block-list"><li>2025 – <em>Night Cartography</em>, Galerie du Panier, Marseille</li><li>2024 – <em>Small Hours</em>, group show, Atelier 13, Lyon</li><li>2023 – <em>Tidal Hours</em>, Librairie Maupetit, Marseille</li></ul><!-- /wp:list -->',
					)
				),
			),
			'commissions' => array(
				'title'    => 'A painting made for one wall',
				'excerpt'  => 'Four to six commissions a year: portraits of places, houses, gardens and the occasional boat.',
				'template' => 'page-templates/commissions.php',
				'content'  => implode(
					"\n\n",
					array(
						'<!-- wp:paragraph --><p>A commission is a new original, painted for you in the style of one of the series. It usually takes six to ten weeks from the first message to delivery.</p><!-- /wp:paragraph -->',
						'<!-- wp:heading --><h2 class="wp-block-heading">How it works</h2><!-- /wp:heading -->',
						'<!-- wp:list {"ordered":true} --><ol class="wp-block-list"><li><strong>You send a request</strong> with your idea, a budget and, if you have them, a few reference photos.</li><li><strong>I reply within a week</strong> with questions and an estimate.</li><li><strong>Two rounds of sketches</strong>, in pencil then in colour, so nothing is a surprise.</li><li><strong>The painting</strong>, with progress photos along the way.</li><li><strong>Delivery</strong>, framed or not, insured and tracked.</li></ol><!-- /wp:list -->',
						'<!-- wp:heading --><h2 class="wp-block-heading">What it costs</h2><!-- /wp:heading -->',
						'<!-- wp:paragraph --><p>Small pieces (30 × 30 cm) start at €450, large ones (60 × 45 cm) at €1,200. A deposit of 30% books the slot; the rest is due when you approve the colour sketch.</p><!-- /wp:paragraph -->',
					)
				),
			),
		);

		$ids = array();
		foreach ( $pages as $slug => $page ) {
			$ids[ $slug ] = (int) wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_name'    => $slug,
					'post_title'   => $page['title'],
					'post_content' => $page['content'],
					'post_excerpt' => $page['excerpt'] ?? '',
					'meta_input'   => array_filter(
						array(
							'_wp_page_template' => $page['template'] ?? '',
							self::MARKER        => 1,
						)
					),
				)
			);
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $ids['home'] );

		// The home page wall shows a portrait from the first series.
		$hero = array_values( array_filter( $this->artworks, static fn( array $artwork ) => 'Morning Swell' === $artwork['title'] ) );
		if ( $hero ) {
			set_theme_mod( 'atelier_mira_hero', $hero[0]['id'] );
		}

		// Menus.
		$primary = array(
			array( 'Portfolio', (string) get_post_type_archive_link( PostTypes::POST_TYPE ) ),
			array( 'Shop', function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '' ),
			array( 'Commissions', $ids['commissions'] ),
			array( 'About', $ids['about'] ),
		);
		$footer  = array(
			array( 'Shop', function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '' ),
			array( 'Commissions', $ids['commissions'] ),
			array( 'About', $ids['about'] ),
			array( 'My account', function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'myaccount' ) : 0 ),
		);

		$locations = (array) get_theme_mod( 'nav_menu_locations', array() );
		foreach ( array(
			'primary' => array( 'Main menu', $primary ),
			'footer'  => array( 'Footer menu', $footer ),
		) as $location => list( $name, $items ) ) {
			$existing = wp_get_nav_menu_object( $name );
			if ( $existing ) {
				wp_delete_nav_menu( $existing->term_id );
			}
			$menu_id = (int) wp_create_nav_menu( $name );
			update_term_meta( $menu_id, self::MARKER, 1 );

			foreach ( $items as $position => list( $title, $target ) ) {
				if ( ! $target ) {
					continue;
				}
				$item = is_int( $target )
					? array(
						'menu-item-object-id' => $target,
						'menu-item-object'    => 'page',
						'menu-item-type'      => 'post_type',
					)
					: array(
						'menu-item-url'  => $target,
						'menu-item-type' => 'custom',
					);
				wp_update_nav_menu_item(
					$menu_id,
					0,
					$item + array(
						'menu-item-title'    => $title,
						'menu-item-status'   => 'publish',
						'menu-item-position' => $position + 1,
					)
				);
			}
			$locations[ $location ] = $menu_id;
		}
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	/**
	 * Home page photos: the provided hero and studio photos, else a painted gallery room
	 * behind the hero (and artworks in the studio section, chosen by the theme).
	 *
	 * @return void
	 */
	private function photos(): void {
		$hero = $this->provided( 'hero' );

		if ( $hero ) {
			$file = $this->painter->import( $hero, self::PROVIDED_MAX, $this->upload_path( 'studio interior' ) );
		} else {
			$file = $this->painter->room( array_column( array_slice( $this->artworks, 0, 3 ), 'file' ), 1800, 1100, $this->upload_path( 'gallery room' ) );
		}
		set_theme_mod( 'atelier_mira_hero_image', $this->attach( $file, 'Atelier Mira, the studio', 'Prints by Atelier Mira hung on a gallery wall' ) );

		$studio = $this->provided( 'studio' );
		if ( $studio ) {
			$file = $this->painter->import( $studio, self::PROVIDED_MAX, $this->upload_path( 'studio' ) );
			set_theme_mod( 'atelier_mira_studio_image', $this->attach( $file, 'The studio', 'The work table in the studio' ) );
		} else {
			remove_theme_mod( 'atelier_mira_studio_image' );
		}
	}

	// Helpers.

	/**
	 * A demo image provided in assets/demo-images/ (see the README there), by base name.
	 *
	 * @param string $name Base name, e.g. "hero" or "artwork-01".
	 * @return string Absolute path, empty when there is none.
	 */
	private function provided( string $name ): string {
		foreach ( array( 'jpg', 'jpeg', 'png', 'webp' ) as $extension ) {
			$path = MIRA_STUDIO_DIR . 'assets/demo-images/' . $name . '.' . $extension;
			if ( is_readable( $path ) ) {
				return $path;
			}
		}

		return '';
	}

	/**
	 * Extra images painted for a given use: the full seed paints a detail crop for the
	 * first six artworks and a framed mockup for every product; a light seed only a few.
	 *
	 * @param string $purpose details|mockups.
	 * @return int
	 */
	private function budget( string $purpose ): int {
		if ( $this->images >= 12 ) {
			return 'details' === $purpose ? 6 : 12;
		}

		return 'details' === $purpose ? 1 : 2;
	}

	/**
	 * Sizes generated by a light seed.
	 *
	 * @param array<string, array<string, mixed>> $sizes Sizes.
	 * @return array<string, array<string, mixed>>
	 */
	public function light_sizes( array $sizes ): array {
		unset( $sizes['medium'], $sizes['medium_large'] );

		return $sizes;
	}

	/**
	 * Unique path (without extension) in the current uploads folder.
	 *
	 * @param string $title Image title.
	 * @return string
	 */
	private function upload_path( string $title ): string {
		$uploads = wp_upload_dir();
		wp_mkdir_p( $uploads['path'] );
		$name = wp_unique_filename( $uploads['path'], sanitize_file_name( 'mira-' . sanitize_title( $title ) . '.' . ImageGenerator::extension() ) );

		return $uploads['path'] . '/' . pathinfo( $name, PATHINFO_FILENAME );
	}

	/**
	 * Registers a generated file as an attachment and builds its sub-sizes.
	 *
	 * @param string $file  Absolute path.
	 * @param string $title Title.
	 * @param string $alt   Alternative text.
	 * @return int Attachment ID.
	 */
	private function attach( string $file, string $title, string $alt ): int {
		$id = wp_insert_attachment(
			array(
				'post_mime_type' => (string) wp_check_filetype( $file )['type'],
				'post_title'     => $title,
				'post_status'    => 'inherit',
				'meta_input'     => array(
					'_wp_attachment_image_alt' => $alt,
					self::MARKER               => 1,
				),
			),
			$file
		);

		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );

		return (int) $id;
	}
}
