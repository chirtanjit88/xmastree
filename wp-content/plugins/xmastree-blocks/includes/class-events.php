<?php
/**
 * Custom Post Type and Taxonomy Registration for Farm Events
 *
 * Registers the `xmastree_event` CPT and `event_category` taxonomy along with
 * admin list-table columns for event dates, times, and locations.
 *
 * @package XmasTree_Blocks
 * @since 1.0.1
 */

declare(strict_types=1);

namespace XmasTree\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Coordinates the XmasTree Event post type, category taxonomy, and admin metadata columns.
 */
class Events {

	/**
	 * Event custom post type slug.
	 */
	public const POST_TYPE = 'xmastree_event';

	/**
	 * Event category taxonomy slug.
	 */
	public const TAXONOMY = 'event_category';

	/**
	 * Option key tracking rewrite rule flush state.
	 */
	private const REWRITE_OPTION = 'xmastree_events_rewrite_version';

	/**
	 * Current rewrite version string.
	 */
	private const REWRITE_VERSION = '1.0.1';

	/**
	 * Hook post type, taxonomy, and admin integration into WordPress.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_taxonomy' ), 8 );
		add_action( 'init', array( $this, 'register_post_type' ), 9 );
		add_action( 'init', array( $this, 'maybe_flush_rewrites' ), 99 );

		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'add_admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_admin_column' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'register_sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'handle_admin_sorting' ) );
	}

	/**
	 * Register the hierarchical Event Category taxonomy.
	 *
	 * @return void
	 */
	public function register_taxonomy(): void {
		$labels = array(
			'name'                       => _x( 'Event Categories', 'taxonomy general name', 'xmastree-blocks' ),
			'singular_name'              => _x( 'Event Category', 'taxonomy singular name', 'xmastree-blocks' ),
			'search_items'               => __( 'Search Event Categories', 'xmastree-blocks' ),
			'all_items'                  => __( 'All Event Categories', 'xmastree-blocks' ),
			'parent_item'                => __( 'Parent Event Category', 'xmastree-blocks' ),
			'parent_item_colon'          => __( 'Parent Event Category:', 'xmastree-blocks' ),
			'edit_item'                  => __( 'Edit Event Category', 'xmastree-blocks' ),
			'update_item'                => __( 'Update Event Category', 'xmastree-blocks' ),
			'add_new_item'               => __( 'Add New Event Category', 'xmastree-blocks' ),
			'new_item_name'              => __( 'New Event Category Name', 'xmastree-blocks' ),
			'menu_name'                  => __( 'Event Categories', 'xmastree-blocks' ),
			'not_found'                  => __( 'No event categories found.', 'xmastree-blocks' ),
		);

		register_taxonomy(
			self::TAXONOMY,
			array( self::POST_TYPE ),
			array(
				'labels'            => $labels,
				'hierarchical'      => true,
				'public'            => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'show_in_nav_menus' => true,
				'show_in_rest'      => true,
				'query_var'         => true,
				'rewrite'           => array(
					'slug'       => 'event-category',
					'with_front' => false,
				),
			)
		);
	}

	/**
	 * Register the Farm Event custom post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$labels = array(
			'name'                  => _x( 'Events', 'post type general name', 'xmastree-blocks' ),
			'singular_name'         => _x( 'Event', 'post type singular name', 'xmastree-blocks' ),
			'menu_name'             => _x( 'Events', 'admin menu', 'xmastree-blocks' ),
			'name_admin_bar'        => _x( 'Event', 'add new on admin bar', 'xmastree-blocks' ),
			'add_new'               => _x( 'Add New Event', 'event', 'xmastree-blocks' ),
			'add_new_item'          => __( 'Add New Event', 'xmastree-blocks' ),
			'new_item'              => __( 'New Event', 'xmastree-blocks' ),
			'edit_item'             => __( 'Edit Event', 'xmastree-blocks' ),
			'view_item'             => __( 'View Event', 'xmastree-blocks' ),
			'all_items'             => __( 'All Events', 'xmastree-blocks' ),
			'search_items'          => __( 'Search Events', 'xmastree-blocks' ),
			'not_found'             => __( 'No events found.', 'xmastree-blocks' ),
			'not_found_in_trash'    => __( 'No events found in Trash.', 'xmastree-blocks' ),
			'featured_image'        => __( 'Event Image', 'xmastree-blocks' ),
			'set_featured_image'    => __( 'Set event image', 'xmastree-blocks' ),
			'remove_featured_image' => __( 'Remove event image', 'xmastree-blocks' ),
			'use_featured_image'    => __( 'Use as event image', 'xmastree-blocks' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'             => $labels,
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => true,
				'show_in_admin_bar'  => true,
				'show_in_rest'       => true,
				'query_var'          => true,
				'rewrite'            => array(
					'slug'       => 'event',
					'with_front' => false,
				),
				'capability_type'    => 'post',
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => 21,
				'menu_icon'          => 'dashicons-calendar-alt',
				'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions' ),
				'taxonomies'         => array( self::TAXONOMY ),
			)
		);
	}

	/**
	 * Flush rewrite rules once after post type registration when version updates.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrites(): void {
		if ( get_option( self::REWRITE_OPTION ) === self::REWRITE_VERSION ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_OPTION, self::REWRITE_VERSION, true );
	}

	/**
	 * Add custom columns to the Events admin list table.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function add_admin_columns( array $columns ): array {
		$reordered = array();
		foreach ( $columns as $key => $label ) {
			$reordered[ $key ] = $label;
			if ( 'title' === $key ) {
				$reordered['event_date']     = __( 'Event Date', 'xmastree-blocks' );
				$reordered['event_schedule'] = __( 'Time & Location', 'xmastree-blocks' );
			}
		}

		return $reordered;
	}

	/**
	 * Output custom column values for each Event row in WordPress Admin.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Event post ID.
	 * @return void
	 */
	public function render_admin_column( string $column, int $post_id ): void {
		if ( 'event_date' === $column ) {
			$start = trim( (string) get_post_meta( $post_id, 'event_start_date', true ) );
			$end   = trim( (string) get_post_meta( $post_id, 'event_end_date', true ) );

			if ( '' === $start ) {
				echo '<span class="description">' . esc_html__( 'Not set', 'xmastree-blocks' ) . '</span>';
				return;
			}

			$start_ts = strtotime( $start );
			$out      = $start_ts ? date_i18n( 'j M Y', $start_ts ) : $start;

			if ( '' !== $end && $end !== $start ) {
				$end_ts = strtotime( $end );
				$out   .= ' &ndash; ' . ( $end_ts ? date_i18n( 'j M Y', $end_ts ) : $end );
			}

			echo esc_html( $out );
			return;
		}

		if ( 'event_schedule' === $column ) {
			$time     = trim( (string) get_post_meta( $post_id, 'event_time_label', true ) );
			$location = trim( (string) get_post_meta( $post_id, 'event_location', true ) );
			$parts    = array_filter( array( $time, $location ) );
			echo ! empty( $parts ) ? esc_html( implode( ' • ', $parts ) ) : '&mdash;';
		}
	}

	/**
	 * Allow sorting by Event Date in WordPress Admin.
	 *
	 * @param array<string, string> $columns Sortable columns.
	 * @return array<string, string>
	 */
	public function register_sortable_columns( array $columns ): array {
		$columns['event_date'] = 'event_start_date';
		return $columns;
	}

	/**
	 * Apply meta_value sorting when sorting by Event Date in admin list table.
	 *
	 * @param \WP_Query $query Active admin query.
	 * @return void
	 */
	public function handle_admin_sorting( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( 'event_start_date' === $query->get( 'orderby' ) ) {
			$query->set( 'meta_key', 'event_start_date' );
			$query->set( 'orderby', 'meta_value' );
		}
	}
}

