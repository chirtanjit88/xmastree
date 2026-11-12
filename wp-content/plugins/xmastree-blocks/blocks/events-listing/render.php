<?php
/**
 * Block Template: Christmas Events Listing
 *
 * Renders the dynamic Events Search & Filter Bar (.events-filter-bar),
 * horizontal Event Cards (.events-list-wrapper / .event-card), and numbered
 * Pagination (.events-pagination-nav) matching html/events.html.
 *
 * Powered by the `xmastree_event` custom post type, `event_category` taxonomy,
 * and SCF/ACF fields with zero hardcoded event data or static image fallbacks.
 *
 * @package XmasTree_Blocks
 * @param array      $block      The block settings and attributes.
 * @param string     $content    The block inner HTML (empty).
 * @param bool       $is_preview True during AJAX preview in the editor.
 * @param int|string $post_id    The post ID this block is saved to.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use XmasTree\Blocks\Events;

// -----------------------------------------------------------------------------
// 1. Block Identifier & Field Normalization Helpers
// -----------------------------------------------------------------------------
$block_id = 'events-listing-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
	$block_id = (string) $block['anchor'];
}

$normalize_select = static function ( $value ): string {
	if ( is_array( $value ) ) {
		$value = $value['value'] ?? '';
	}
	if ( is_bool( $value ) || null === $value ) {
		return '';
	}
	return trim( (string) $value );
};

$read_bool = static function ( string $field_name, bool $default = true ): bool {
	if ( ! function_exists( 'get_field' ) ) {
		return $default;
	}
	$val = get_field( $field_name );
	if ( null === $val || '' === $val ) {
		return $default;
	}
	return (bool) $val;
};

$read_int = static function ( string $field_name, int $default, int $min, int $max ): int {
	if ( ! function_exists( 'get_field' ) ) {
		return $default;
	}
	$val = get_field( $field_name );
	if ( null === $val || '' === $val || ! is_numeric( $val ) ) {
		return $default;
	}
	return max( $min, min( $max, (int) $val ) );
};

$normalize_id_list = static function ( $value ): array {
	if ( empty( $value ) ) {
		return array();
	}
	$items = is_array( $value ) ? $value : array( $value );
	$ids   = array();
	foreach ( $items as $item ) {
		if ( $item instanceof \WP_Term ) {
			$ids[] = (int) $item->term_id;
		} elseif ( is_array( $item ) && isset( $item['term_id'] ) ) {
			$ids[] = (int) $item['term_id'];
		} elseif ( is_numeric( $item ) && (int) $item > 0 ) {
			$ids[] = (int) $item;
		}
	}
	return array_values( array_unique( array_filter( $ids ) ) );
};

$resolve_image = static function ( $img_field, string $default_alt = '' ): array {
	$url = '';
	$alt = $default_alt;
	if ( is_array( $img_field ) && ! empty( $img_field['url'] ) ) {
		$url = (string) $img_field['url'];
		$alt = ! empty( $img_field['alt'] ) ? (string) $img_field['alt'] : ( ! empty( $img_field['title'] ) && '' === $alt ? (string) $img_field['title'] : $alt );
	} elseif ( is_numeric( $img_field ) && (int) $img_field > 0 ) {
		$id  = (int) $img_field;
		$src = wp_get_attachment_image_url( $id, 'full' );
		if ( $src ) {
			$url      = (string) $src;
			$alt_meta = get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt_meta ) ) {
				$alt = (string) $alt_meta;
			}
		}
	} elseif ( is_string( $img_field ) && '' !== trim( $img_field ) ) {
		$url = trim( $img_field );
	}

	return array(
		'url' => $url,
		'alt' => $alt,
	);
};

$format_event_date_box = static function ( int $target_post_id ): array {
	$start_raw = trim( (string) get_post_meta( $target_post_id, 'event_start_date', true ) );
	$end_raw   = trim( (string) get_post_meta( $target_post_id, 'event_end_date', true ) );

	if ( '' === $start_raw ) {
		$fallback_ts = (int) get_post_time( 'U', false, $target_post_id );
		return array(
			'iso'   => (string) get_the_date( 'Y-m-d', $target_post_id ),
			'label' => $fallback_ts ? strtoupper( date_i18n( 'j M', $fallback_ts ) ) : '',
		);
	}

	$start_ts = strtotime( $start_raw );
	if ( ! $start_ts ) {
		return array(
			'iso'   => $start_raw,
			'label' => strtoupper( $start_raw ),
		);
	}

	$label = strtoupper( date_i18n( 'j M', $start_ts ) );
	if ( '' !== $end_raw && $end_raw !== $start_raw ) {
		$end_ts = strtotime( $end_raw );
		if ( $end_ts && $end_ts > $start_ts ) {
			if ( gmdate( 'Y-m', $start_ts ) === gmdate( 'Y-m', $end_ts ) ) {
				$label = strtoupper( date_i18n( 'j', $start_ts ) . '–' . date_i18n( 'j M', $end_ts ) );
			} else {
				$label = strtoupper( date_i18n( 'j M', $start_ts ) . ' – ' . date_i18n( 'j M', $end_ts ) );
			}
		}
	}

	return array(
		'iso'   => $start_raw,
		'label' => $label,
	);
};

$get_event_summary = static function ( \WP_Post $event_post ): string {
	$custom_summary = trim( (string) get_post_meta( (int) $event_post->ID, 'event_short_description', true ) );
	if ( '' !== $custom_summary ) {
		return $custom_summary;
	}

	$raw = has_excerpt( $event_post ) ? (string) $event_post->post_excerpt : (string) $event_post->post_content;
	$txt = wp_strip_all_tags( strip_shortcodes( $raw ) );
	$txt = html_entity_decode( $txt, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$txt = trim( (string) preg_replace( '/\s+/u', ' ', str_replace( "\xc2\xa0", ' ', $txt ) ) );
	if ( '' === $txt ) {
		return '';
	}

	return wp_trim_words( $txt, 32, '...' );
};

// -----------------------------------------------------------------------------
// 2. Retrieve SCF / ACF Block Fields
// -----------------------------------------------------------------------------
$events_per_page  = $read_int( 'events_per_page', 4, 1, 24 );
$orderby_field    = function_exists( 'get_field' ) ? $normalize_select( get_field( 'events_orderby' ) ) : 'event_start_date';
$order_field      = function_exists( 'get_field' ) ? strtoupper( $normalize_select( get_field( 'events_order' ) ) ) : 'ASC';
$hide_past        = $read_bool( 'events_hide_past', false );
$include_cats_raw = function_exists( 'get_field' ) ? get_field( 'events_include_categories' ) : null;
$include_cat_ids  = $normalize_id_list( $include_cats_raw );
$cta_custom       = function_exists( 'get_field' ) ? trim( (string) get_field( 'events_cta_label' ) ) : '';
$empty_custom     = function_exists( 'get_field' ) ? trim( (string) get_field( 'events_empty_message' ) ) : '';

$show_filter_bar     = $read_bool( 'events_show_filter_bar', true );
$show_search         = $read_bool( 'events_show_search', true );
$search_ph_custom    = function_exists( 'get_field' ) ? trim( (string) get_field( 'events_search_placeholder' ) ) : '';
$search_icon_raw     = function_exists( 'get_field' ) ? get_field( 'events_search_icon' ) : null;
$show_category_tabs  = $read_bool( 'events_show_category_tabs', true );
$filter_cats_raw     = function_exists( 'get_field' ) ? get_field( 'events_filter_categories' ) : null;
$filter_cat_ids      = $normalize_id_list( $filter_cats_raw );
$all_tab_custom      = function_exists( 'get_field' ) ? trim( (string) get_field( 'events_all_tab_label' ) ) : '';
$show_date_filter    = $read_bool( 'events_show_date_filter', true );
$date_label_custom   = function_exists( 'get_field' ) ? trim( (string) get_field( 'events_date_filter_label' ) ) : '';
$calendar_icon_raw   = function_exists( 'get_field' ) ? get_field( 'events_calendar_icon' ) : null;
$show_pagination     = $read_bool( 'events_show_pagination', true );
$bg_color            = function_exists( 'get_field' ) ? $normalize_select( get_field( 'events_background_color' ) ) : '';

$allowed_orderby = array( 'event_start_date', 'date', 'menu_order', 'title' );
if ( ! in_array( $orderby_field, $allowed_orderby, true ) ) {
	$orderby_field = 'event_start_date';
}
if ( ! in_array( $order_field, array( 'ASC', 'DESC' ), true ) ) {
	$order_field = 'ASC';
}

$label_cta        = '' !== $cta_custom ? $cta_custom : __( 'View Details', 'xmastree-blocks' );
$label_empty      = '' !== $empty_custom ? $empty_custom : __( 'No upcoming events found matching your selection.', 'xmastree-blocks' );
$label_search_ph  = '' !== $search_ph_custom ? $search_ph_custom : __( 'Search for events', 'xmastree-blocks' );
$label_all_tab    = '' !== $all_tab_custom ? $all_tab_custom : __( 'All Events', 'xmastree-blocks' );
$label_date_btn   = '' !== $date_label_custom ? $date_label_custom : __( 'Select Date', 'xmastree-blocks' );

$search_icon_data   = $resolve_image( $search_icon_raw, '' );
$calendar_icon_data = $resolve_image( $calendar_icon_raw, '' );
$search_icon_url    = $search_icon_data['url'];
$calendar_icon_url  = $calendar_icon_data['url'];

// -----------------------------------------------------------------------------
// 3. Resolve Active Query Filters, Selected Date, Search & Base URL
// -----------------------------------------------------------------------------
$active_cat_slug = isset( $_GET['event_cat'] ) ? sanitize_title( wp_unslash( (string) $_GET['event_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$search_query    = isset( $_GET['event_s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['event_s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$selected_date   = isset( $_GET['event_date'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['event_date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current_page    = isset( $_GET['event_page'] ) ? max( 1, absint( $_GET['event_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( '' !== $selected_date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $selected_date ) ) {
	$selected_date = '';
}

if ( '' === $active_cat_slug && is_tax( Events::TAXONOMY ) ) {
	$queried = get_queried_object();
	if ( $queried instanceof \WP_Term ) {
		$active_cat_slug = $queried->slug;
	}
}

$base_url = '';
if ( ! empty( $post_id ) && is_numeric( $post_id ) && (int) $post_id > 0 && is_singular() ) {
	$base_url = (string) get_permalink( (int) $post_id );
} elseif ( is_singular() ) {
	$base_url = (string) get_permalink( get_queried_object_id() );
}
if ( '' === $base_url ) {
	if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || wp_doing_ajax() ) {
		$base_url = home_url( '/' );
	} else {
		$base_url = (string) get_pagenum_link( 1, false );
	}
}
$base_url = remove_query_arg( array( 'event_cat', 'event_s', 'event_date', 'event_page' ), $base_url );

$parsed_base_query = array();
$parsed_url_parts  = wp_parse_url( $base_url );
if ( ! empty( $parsed_url_parts['query'] ) ) {
	parse_str( (string) $parsed_url_parts['query'], $parsed_base_query );
}

$build_events_url = static function ( array $params ) use ( $base_url ): string {
	$merged = array(
		'event_cat'  => false,
		'event_s'    => false,
		'event_date' => false,
		'event_page' => false,
	);

	foreach ( $params as $k => $v ) {
		if ( null === $v || '' === $v || false === $v ) {
			$merged[ $k ] = false;
		} else {
			$merged[ $k ] = $v;
		}
	}

	if ( isset( $merged['event_page'] ) && (int) $merged['event_page'] <= 1 ) {
		$merged['event_page'] = false;
	}

	return add_query_arg( $merged, $base_url );
};

// -----------------------------------------------------------------------------
// 4. Resolve Taxonomy Terms (Event Categories)
// -----------------------------------------------------------------------------
if ( '' !== $active_cat_slug && ! empty( $include_cat_ids ) ) {
	$active_cat_term = get_term_by( 'slug', $active_cat_slug, Events::TAXONOMY );
	if ( ! $active_cat_term instanceof \WP_Term || ! in_array( (int) $active_cat_term->term_id, $include_cat_ids, true ) ) {
		$active_cat_slug = '';
	}
}

$cat_args = array(
	'taxonomy'   => Events::TAXONOMY,
	'hide_empty' => true,
	'orderby'    => 'name',
	'order'      => 'ASC',
);
if ( ! empty( $include_cat_ids ) ) {
	$cat_args['include'] = $include_cat_ids;
}
$all_event_cats = get_terms( $cat_args );
if ( is_wp_error( $all_event_cats ) || ! is_array( $all_event_cats ) ) {
	$all_event_cats = array();
}

$filter_tab_terms = array();
if ( ! empty( $filter_cat_ids ) ) {
	$tab_include = ! empty( $include_cat_ids )
		? array_values( array_intersect( $filter_cat_ids, $include_cat_ids ) )
		: $filter_cat_ids;
	if ( ! empty( $tab_include ) ) {
		$tabs_q = get_terms(
			array(
				'taxonomy'   => Events::TAXONOMY,
				'include'    => $tab_include,
				'hide_empty' => false,
				'orderby'    => 'include',
			)
		);
		if ( ! is_wp_error( $tabs_q ) && is_array( $tabs_q ) ) {
			$filter_tab_terms = $tabs_q;
		}
	}
} else {
	$filter_tab_terms = $all_event_cats;
}

$is_filtered = ( '' !== $active_cat_slug || '' !== $search_query || '' !== $selected_date );

// -----------------------------------------------------------------------------
// 5. Query Published Events
// -----------------------------------------------------------------------------
$query_args = array(
	'post_type'      => Events::POST_TYPE,
	'post_status'    => 'publish',
	'posts_per_page' => $events_per_page,
	'paged'          => $current_page,
);

$tax_clauses = array();
if ( ! empty( $include_cat_ids ) ) {
	$tax_clauses[] = array(
		'taxonomy' => Events::TAXONOMY,
		'field'    => 'term_id',
		'terms'    => $include_cat_ids,
	);
}
if ( '' !== $active_cat_slug ) {
	$tax_clauses[] = array(
		'taxonomy' => Events::TAXONOMY,
		'field'    => 'slug',
		'terms'    => $active_cat_slug,
	);
}
if ( count( $tax_clauses ) > 1 ) {
	$tax_clauses['relation'] = 'AND';
}
if ( ! empty( $tax_clauses ) ) {
	$query_args['tax_query'] = $tax_clauses; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
}

if ( '' !== $search_query ) {
	$query_args['s'] = $search_query;
}

$meta_clauses = array();

if ( '' !== $selected_date ) {
	$meta_clauses[] = array(
		'relation' => 'OR',
		array(
			'key'     => 'event_start_date',
			'value'   => $selected_date,
			'compare' => '=',
		),
		array(
			'relation' => 'AND',
			array(
				'key'     => 'event_start_date',
				'value'   => $selected_date,
				'compare' => '<=',
				'type'    => 'DATE',
			),
			array(
				'key'     => 'event_end_date',
				'value'   => $selected_date,
				'compare' => '>=',
				'type'    => 'DATE',
			),
		),
	);
} elseif ( $hide_past ) {
	$today_ymd      = current_time( 'Y-m-d' );
	$meta_clauses[] = array(
		'relation' => 'OR',
		array(
			'key'     => 'event_start_date',
			'value'   => $today_ymd,
			'compare' => '>=',
			'type'    => 'DATE',
		),
		array(
			'key'     => 'event_end_date',
			'value'   => $today_ymd,
			'compare' => '>=',
			'type'    => 'DATE',
		),
	);
}

if ( 'event_start_date' === $orderby_field ) {
	$meta_clauses['event_date_sort_clause'] = array(
		'relation' => 'OR',
		array(
			'key'     => 'event_start_date',
			'compare' => 'EXISTS',
		),
		array(
			'key'     => 'event_start_date',
			'compare' => 'NOT EXISTS',
		),
	);
	$query_args['orderby'] = array(
		'event_date_sort_clause' => $order_field,
		'date'                   => $order_field,
	);
} else {
	$query_args['orderby'] = $orderby_field;
	$query_args['order']   = $order_field;
}

if ( ! empty( $meta_clauses ) ) {
	if ( count( $meta_clauses ) > 1 ) {
		$meta_clauses['relation'] = 'AND';
	}
	$query_args['meta_query'] = $meta_clauses; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
}

$events_query = new \WP_Query( $query_args );
if ( $events_query->max_num_pages > 0 && $current_page > (int) $events_query->max_num_pages ) {
	$current_page        = (int) $events_query->max_num_pages;
	$query_args['paged'] = $current_page;
	$events_query        = new \WP_Query( $query_args );
}

$event_posts = is_array( $events_query->posts ) ? $events_query->posts : array();
$total_pages = (int) $events_query->max_num_pages;

$has_any_events = ! empty( $event_posts );
if ( ! $has_any_events && $is_filtered ) {
	$check_q = new \WP_Query(
		array(
			'post_type'              => Events::POST_TYPE,
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	$has_any_events = ! empty( $check_q->posts );
}

if ( ! $has_any_events && ! $is_preview ) {
	return;
}

// -----------------------------------------------------------------------------
// 6. Pagination & Styling Helpers
// -----------------------------------------------------------------------------
$pagination_items = array();
if ( $show_pagination && $total_pages > 1 ) {
	$range = array();
	for ( $i = 1; $i <= $total_pages; $i++ ) {
		if ( 1 === $i || $total_pages === $i || ( $i >= $current_page - 1 && $i <= $current_page + 1 ) ) {
			$range[] = $i;
		}
	}
	$prev_num = 0;
	foreach ( $range as $num ) {
		if ( $prev_num > 0 && $num - $prev_num > 1 ) {
			$pagination_items[] = array( 'type' => 'dots' );
		}
		$pagination_items[] = array(
			'type'   => 'page',
			'number' => $num,
			'active' => ( $num === $current_page ),
			'url'    => $build_events_url(
				array(
					'event_cat'  => $active_cat_slug,
					'event_s'    => $search_query,
					'event_date' => $selected_date,
					'event_page' => $num,
				)
			),
		);
		$prev_num = $num;
	}
}

$section_style_attr = '';
$sanitized_bg_color = '' !== $bg_color ? sanitize_hex_color( $bg_color ) : null;
if ( is_string( $sanitized_bg_color ) && '' !== $sanitized_bg_color ) {
	$section_style_attr = ' style="' . esc_attr( 'background-color: ' . strtoupper( $sanitized_bg_color ) . ';' ) . '"';
}

$wrapper_classes = array( 'xmastree-events-listing-block' );
if ( ! empty( $block['className'] ) ) {
	$wrapper_classes[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$wrapper_classes[] = 'align' . (string) $block['align'];
}

$selected_date_label = '';
if ( '' !== $selected_date ) {
	$ts                  = strtotime( $selected_date );
	$selected_date_label = $ts ? date_i18n( 'j M Y', $ts ) : $selected_date;
}

$active_cat_label = '';
if ( '' !== $active_cat_slug ) {
	$term_obj = get_term_by( 'slug', $active_cat_slug, Events::TAXONOMY );
	if ( $term_obj instanceof \WP_Term ) {
		$active_cat_label = $term_obj->name;
	}
}

$search_uid   = sanitize_html_class( $block_id );
$aos_filter   = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
$aos_pag      = $is_preview ? '' : ' data-aos="fade-up"';
$show_top_tabs = $show_filter_bar && $show_category_tabs && ! empty( $filter_tab_terms );
$has_top_bar   = $show_filter_bar && ( $show_search || $show_top_tabs || $show_date_filter );
?>

<section
	id="<?php echo esc_attr( $block_id ); ?>"
	class="events-main-section <?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>"
	data-events-dynamic="1"
	data-base-url="<?php echo esc_url( $base_url ); ?>"
	data-active-cat="<?php echo esc_attr( $active_cat_slug ); ?>"
	data-active-search="<?php echo esc_attr( $search_query ); ?>"
	data-active-date="<?php echo esc_attr( $selected_date ); ?>"
	<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
>
	<div class="container">

		<?php if ( ! $has_any_events && $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white rounded">
				<span><?php esc_html_e( 'Events Listing Block — No published events yet. Add items under Events in WordPress Admin to display them here.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php else : ?>

			<?php if ( $has_top_bar ) : ?>
				<div class="events-filter-bar"<?php echo $aos_filter; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

					<?php if ( $show_search ) : ?>
						<form class="event-search-wrapper xmastree-events-search-form" role="search" method="get" action="<?php echo esc_url( $base_url ); ?>" data-events-search-form>
							<?php
							foreach ( $parsed_base_query as $p_key => $p_val ) :
								if ( is_string( $p_val ) ) :
									?>
									<input type="hidden" name="<?php echo esc_attr( $p_key ); ?>" value="<?php echo esc_attr( $p_val ); ?>">
									<?php
								endif;
							endforeach;
							?>
							<?php if ( '' !== $active_cat_slug ) : ?>
								<input type="hidden" name="event_cat" value="<?php echo esc_attr( $active_cat_slug ); ?>" data-events-hidden-cat>
							<?php endif; ?>
							<?php if ( '' !== $selected_date ) : ?>
								<input type="hidden" name="event_date" value="<?php echo esc_attr( $selected_date ); ?>" data-events-hidden-date>
							<?php endif; ?>

							<?php if ( '' !== $search_icon_url ) : ?>
								<img src="<?php echo esc_url( $search_icon_url ); ?>" alt="" class="event-search-icon" aria-hidden="true">
							<?php else : ?>
								<i class="bi bi-search event-search-icon-bi" aria-hidden="true"></i>
							<?php endif; ?>
							<label class="visually-hidden" for="<?php echo esc_attr( $search_uid . '-search' ); ?>"><?php echo esc_html( $label_search_ph ); ?></label>
							<input
								type="search"
								id="<?php echo esc_attr( $search_uid . '-search' ); ?>"
								name="event_s"
								value="<?php echo esc_attr( $search_query ); ?>"
								class="form-control"
								placeholder="<?php echo esc_attr( $label_search_ph ); ?>"
								data-events-search-input
								autocomplete="off"
							>
							<button
								type="button"
								class="blog-search-clear-btn"
								data-events-search-clear
								aria-label="<?php esc_attr_e( 'Clear search', 'xmastree-blocks' ); ?>"
								<?php echo '' === $search_query ? 'hidden' : ''; ?>
							>
								<i class="bi bi-x-lg" aria-hidden="true"></i>
							</button>
						</form>
					<?php endif; ?>

					<?php if ( $show_top_tabs || $show_date_filter ) : ?>
						<div class="event-filter-buttons" data-events-filter-controls>
							<?php if ( $show_top_tabs ) : ?>
								<?php
								$all_url       = $build_events_url(
									array(
										'event_cat'  => false,
										'event_s'    => $search_query,
										'event_date' => $selected_date,
										'event_page' => 1,
									)
								);
								$is_all_active = ( '' === $active_cat_slug );
								?>
								<a
									href="<?php echo esc_url( $all_url ); ?>"
									class="filter-btn<?php echo $is_all_active ? ' active' : ''; ?>"
									data-filter="all"
									data-events-filter-link="1"
									<?php echo $is_all_active ? 'aria-current="page"' : ''; ?>
								><?php echo esc_html( $label_all_tab ); ?></a>

								<?php
								foreach ( $filter_tab_terms as $term ) :
									$is_term_active = ( $active_cat_slug === $term->slug );
									$term_url       = $build_events_url(
										array(
											'event_cat'  => $term->slug,
											'event_s'    => $search_query,
											'event_date' => $selected_date,
											'event_page' => 1,
										)
									);
									?>
									<a
										href="<?php echo esc_url( $term_url ); ?>"
										class="filter-btn<?php echo $is_term_active ? ' active' : ''; ?>"
										data-filter="<?php echo esc_attr( $term->slug ); ?>"
										data-events-filter-link="1"
										<?php echo $is_term_active ? 'aria-current="page"' : ''; ?>
									><?php echo esc_html( $term->name ); ?></a>
								<?php endforeach; ?>
							<?php endif; ?>

							<?php if ( $show_date_filter ) : ?>
								<div class="event-date-picker-wrap">
									<button
										type="button"
										class="filter-btn date-filter-btn<?php echo '' !== $selected_date ? ' active' : ''; ?>"
										data-events-date-trigger
										aria-label="<?php echo esc_attr( '' !== $selected_date_label ? $selected_date_label : $label_date_btn ); ?>"
									>
										<?php if ( '' !== $calendar_icon_url ) : ?>
											<img src="<?php echo esc_url( $calendar_icon_url ); ?>" alt="" class="filter-cal-icon" aria-hidden="true">
										<?php else : ?>
											<i class="bi bi-calendar-event filter-cal-icon-bi" aria-hidden="true"></i>
										<?php endif; ?>
										<span data-events-date-label><?php echo esc_html( '' !== $selected_date_label ? $selected_date_label : $label_date_btn ); ?></span>
									</button>

									<input
										type="date"
										class="event-date-native-input"
										value="<?php echo esc_attr( $selected_date ); ?>"
										aria-label="<?php esc_attr_e( 'Filter events by date', 'xmastree-blocks' ); ?>"
										data-events-date-input
									>

									<?php if ( '' !== $selected_date ) : ?>
										<?php
										$clear_date_url = $build_events_url(
											array(
												'event_cat'  => $active_cat_slug,
												'event_s'    => $search_query,
												'event_date' => false,
												'event_page' => 1,
											)
										);
										?>
										<a
											href="<?php echo esc_url( $clear_date_url ); ?>"
											class="event-date-clear-btn"
											data-events-filter-link="1"
											aria-label="<?php esc_attr_e( 'Clear selected date', 'xmastree-blocks' ); ?>"
										>
											<i class="bi bi-x-lg" aria-hidden="true"></i>
										</a>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endif; ?>

				</div>
			<?php endif; ?>

			<div class="events-list-wrapper xmastree-events-results" data-events-results>

				<?php if ( $is_filtered && ( '' !== $selected_date_label || '' !== $search_query || ( '' !== $active_cat_label && ! $show_top_tabs ) ) ) : ?>
					<div class="blog-active-filters mb-4">
						<span class="blog-active-filters-label"><?php esc_html_e( 'Showing:', 'xmastree-blocks' ); ?></span>
						<?php if ( '' !== $active_cat_label ) : ?>
							<span class="blog-active-filter-chip"><?php echo esc_html( $active_cat_label ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $selected_date_label ) : ?>
							<span class="blog-active-filter-chip"><?php echo esc_html( $selected_date_label ); ?></span>
						<?php endif; ?>
						<?php if ( '' !== $search_query ) : ?>
							<span class="blog-active-filter-chip">&ldquo;<?php echo esc_html( $search_query ); ?>&rdquo;</span>
						<?php endif; ?>
						<a href="<?php echo esc_url( $base_url ); ?>" class="blog-clear-filters-link" data-events-reset-link>
							<?php esc_html_e( 'Clear Filters', 'xmastree-blocks' ); ?>
						</a>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $event_posts ) ) : ?>
					<?php
					foreach ( $event_posts as $index => $event_post ) :
						$e_id       = (int) $event_post->ID;
						$e_title    = get_the_title( $e_id );
						$e_summary  = $get_event_summary( $event_post );
						$e_date     = $format_event_date_box( $e_id );
						$e_time     = trim( (string) get_post_meta( $e_id, 'event_time_label', true ) );
						$e_location = trim( (string) get_post_meta( $e_id, 'event_location', true ) );

						$custom_cta   = function_exists( 'get_field' ) ? get_field( 'event_cta_link', $e_id ) : null;
						$e_permalink  = (string) get_permalink( $e_id );
						$e_link_url   = $e_permalink;
						$e_link_title = $label_cta;
						$e_link_tgt   = '';
						if ( is_array( $custom_cta ) && ! empty( $custom_cta['url'] ) ) {
							$e_link_url   = trim( (string) $custom_cta['url'] );
							$e_link_title = ! empty( $custom_cta['title'] ) ? trim( (string) $custom_cta['title'] ) : $label_cta;
							$e_link_tgt   = ! empty( $custom_cta['target'] ) ? trim( (string) $custom_cta['target'] ) : '';
						}

						$thumb_id   = get_post_thumbnail_id( $e_id );
						$img_url    = $thumb_id ? (string) wp_get_attachment_image_url( (int) $thumb_id, 'large' ) : '';
						$img_alt    = $thumb_id ? trim( (string) get_post_meta( (int) $thumb_id, '_wp_attachment_image_alt', true ) ) : '';
						if ( '' === $img_alt ) {
							$img_alt = $e_title;
						}

						$e_terms     = get_the_terms( $e_id, Events::TAXONOMY );
						$e_cat_slugs = array();
						if ( is_array( $e_terms ) ) {
							foreach ( $e_terms as $term_item ) {
								$e_cat_slugs[] = $term_item->slug;
							}
						}

						$aos_delay = 100 + ( $index * 50 );
						$aos_card  = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="600" data-aos-delay="' . esc_attr( (string) min( $aos_delay, 300 ) ) . '"';
						?>
						<article class="event-card event-card-item<?php echo '' === $img_url ? ' event-card--no-image' : ''; ?>" data-category="<?php echo esc_attr( implode( ' ', $e_cat_slugs ) ); ?>"<?php echo $aos_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<div class="event-date-box">
								<?php if ( '' !== $calendar_icon_url ) : ?>
									<img src="<?php echo esc_url( $calendar_icon_url ); ?>" alt="" class="event-date-icon" aria-hidden="true">
								<?php else : ?>
									<i class="bi bi-calendar-event event-date-icon-bi" aria-hidden="true"></i>
								<?php endif; ?>
								<?php if ( '' !== $e_date['label'] ) : ?>
									<time datetime="<?php echo esc_attr( $e_date['iso'] ); ?>"><?php echo esc_html( $e_date['label'] ); ?></time>
								<?php endif; ?>
							</div>

							<div class="event-body">
								<h4 class="event-title">
									<a href="<?php echo esc_url( $e_permalink ); ?>"><?php echo esc_html( $e_title ); ?></a>
								</h4>

								<?php if ( '' !== $e_time || '' !== $e_location ) : ?>
									<div class="event-schedule-meta">
										<?php if ( '' !== $e_time ) : ?>
											<span><i class="bi bi-clock" aria-hidden="true"></i> <?php echo esc_html( $e_time ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== $e_location ) : ?>
											<span><i class="bi bi-geo-alt" aria-hidden="true"></i> <?php echo esc_html( $e_location ); ?></span>
										<?php endif; ?>
									</div>
								<?php endif; ?>

								<?php if ( '' !== $e_summary ) : ?>
									<p><?php echo esc_html( $e_summary ); ?></p>
								<?php endif; ?>

								<a href="<?php echo esc_url( $e_link_url ); ?>"<?php echo '' !== $e_link_tgt ? ' target="' . esc_attr( $e_link_tgt ) . '" rel="noopener noreferrer"' : ''; ?> class="event-link">
									<?php echo esc_html( $e_link_title ); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i>
								</a>
							</div>

							<?php if ( '' !== $img_url ) : ?>
								<div class="event-img-box">
									<a href="<?php echo esc_url( $e_permalink ); ?>"<?php echo $is_preview ? ' tabindex="-1"' : ''; ?>>
										<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $img_alt ); ?>" class="event-img">
									</a>
								</div>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>

					<?php if ( ! empty( $pagination_items ) ) : ?>
						<?php
						$prev_url = $current_page > 1
							? $build_events_url(
								array(
									'event_cat'  => $active_cat_slug,
									'event_s'    => $search_query,
									'event_date' => $selected_date,
									'event_page' => $current_page - 1,
								)
							)
							: '';
						$next_url = $current_page < $total_pages
							? $build_events_url(
								array(
									'event_cat'  => $active_cat_slug,
									'event_s'    => $search_query,
									'event_date' => $selected_date,
									'event_page' => $current_page + 1,
								)
							)
							: '';
						?>
						<nav class="events-pagination-nav" aria-label="<?php esc_attr_e( 'Events pagination', 'xmastree-blocks' ); ?>"<?php echo $aos_pag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<ul class="pagination events-pagination">
								<?php if ( '' !== $prev_url ) : ?>
									<li class="page-item">
										<a class="page-link prev-ctrl" href="<?php echo esc_url( $prev_url ); ?>" data-events-page-link><?php esc_html_e( 'Prev', 'xmastree-blocks' ); ?></a>
									</li>
								<?php else : ?>
									<li class="page-item disabled">
										<span class="page-link prev-ctrl"><?php esc_html_e( 'Prev', 'xmastree-blocks' ); ?></span>
									</li>
								<?php endif; ?>

								<?php foreach ( $pagination_items as $p_item ) : ?>
									<?php if ( 'dots' === $p_item['type'] ) : ?>
										<li class="page-item disabled">
											<span class="page-link dots">&hellip;</span>
										</li>
									<?php else : ?>
										<li class="page-item<?php echo $p_item['active'] ? ' active' : ''; ?>">
											<a
												class="page-link"
												href="<?php echo esc_url( $p_item['url'] ); ?>"
												data-events-page-link
												<?php echo $p_item['active'] ? 'aria-current="page"' : ''; ?>
											><?php echo esc_html( (string) $p_item['number'] ); ?></a>
										</li>
									<?php endif; ?>
								<?php endforeach; ?>

								<?php if ( '' !== $next_url ) : ?>
									<li class="page-item">
										<a class="page-link next-ctrl" href="<?php echo esc_url( $next_url ); ?>" data-events-page-link><?php esc_html_e( 'Next', 'xmastree-blocks' ); ?></a>
									</li>
								<?php else : ?>
									<li class="page-item disabled">
										<span class="page-link next-ctrl"><?php esc_html_e( 'Next', 'xmastree-blocks' ); ?></span>
									</li>
								<?php endif; ?>
							</ul>
						</nav>
					<?php endif; ?>

				<?php elseif ( $is_filtered ) : ?>
					<div class="blog-empty-state">
						<p class="mb-3"><?php echo esc_html( $label_empty ); ?></p>
						<a href="<?php echo esc_url( $base_url ); ?>" class="blog-btn-crimson" data-events-reset-link>
							<?php esc_html_e( 'View All Events', 'xmastree-blocks' ); ?>
						</a>
					</div>
				<?php else : ?>
					<div class="blog-empty-state">
						<p class="mb-0"><?php echo esc_html( $label_empty ); ?></p>
					</div>
				<?php endif; ?>

			</div>

		<?php endif; ?>
	</div>
</section>

