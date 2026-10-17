<?php
/**
 * Commission request content type.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Commissions;

defined( 'ABSPATH' ) || exit;

/**
 * Private `commission_request` post type with its workflow statuses.
 *
 * Requests are only created by the public form; the admin manages their status.
 */
final class PostType {

	public const POST_TYPE = 'commission_request';

	/**
	 * Request meta keys.
	 */
	public const META_NAME     = '_mira_name';
	public const META_EMAIL    = '_mira_email';
	public const META_PROJECT  = '_mira_project';
	public const META_BUDGET   = '_mira_budget';
	public const META_DEADLINE = '_mira_deadline';
	public const META_FILES    = '_mira_files';
	public const META_QUOTE    = '_mira_quote';
	public const META_NOTE     = '_mira_note';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	/**
	 * Workflow statuses: post status => label.
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return array(
			'mira-new'      => __( 'New', 'mira-studio' ),
			'mira-quoted'   => __( 'Quoted', 'mira-studio' ),
			'mira-accepted' => __( 'Accepted', 'mira-studio' ),
			'mira-declined' => __( 'Declined', 'mira-studio' ),
		);
	}

	/**
	 * Budget ranges: key => label.
	 *
	 * @return array<string, string>
	 */
	public static function budgets(): array {
		return array(
			'under-300' => __( 'Under €300', 'mira-studio' ),
			'300-800'   => __( '€300 – €800', 'mira-studio' ),
			'800-2000'  => __( '€800 – €2,000', 'mira-studio' ),
			'2000-plus' => __( 'More than €2,000', 'mira-studio' ),
		);
	}

	/**
	 * Registers the post type and statuses (also called on activation).
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'               => __( 'Commissions', 'mira-studio' ),
					'singular_name'      => __( 'Commission request', 'mira-studio' ),
					'menu_name'          => __( 'Commissions', 'mira-studio' ),
					'all_items'          => __( 'All requests', 'mira-studio' ),
					'edit_item'          => __( 'Commission request', 'mira-studio' ),
					'search_items'       => __( 'Search requests', 'mira-studio' ),
					'not_found'          => __( 'No commission requests yet.', 'mira-studio' ),
					'not_found_in_trash' => __( 'No commission requests in Trash.', 'mira-studio' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-email-alt',
				'menu_position'       => 56,
				'supports'            => array( 'title' ),
				'map_meta_cap'        => true,
				'capability_type'     => 'post',
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'rewrite'             => false,
				'query_var'           => false,
			)
		);

		$counts = array(
			/* translators: %s: number of requests. */
			'mira-new'      => _n_noop( 'New <span class="count">(%s)</span>', 'New <span class="count">(%s)</span>', 'mira-studio' ),
			/* translators: %s: number of requests. */
			'mira-quoted'   => _n_noop( 'Quoted <span class="count">(%s)</span>', 'Quoted <span class="count">(%s)</span>', 'mira-studio' ),
			/* translators: %s: number of requests. */
			'mira-accepted' => _n_noop( 'Accepted <span class="count">(%s)</span>', 'Accepted <span class="count">(%s)</span>', 'mira-studio' ),
			/* translators: %s: number of requests. */
			'mira-declined' => _n_noop( 'Declined <span class="count">(%s)</span>', 'Declined <span class="count">(%s)</span>', 'mira-studio' ),
		);

		foreach ( self::statuses() as $status => $label ) {
			register_post_status(
				$status,
				array(
					'label'                     => $label,
					'public'                    => false,
					'protected'                 => true,
					'exclude_from_search'       => true,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => $counts[ $status ],
				)
			);
		}
	}
}
