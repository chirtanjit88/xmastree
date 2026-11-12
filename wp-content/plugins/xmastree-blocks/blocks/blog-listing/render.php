<?php
/**
 * Block Template: Blog Listing & Featured Story
 *
 * Renders the Featured Article Spotlight (.blog-featured-section) and Main Blog Section
 * (.blog-main-section with category filter tabs, live search, responsive article cards,
 * numbered pagination, and configurable sidebar widgets) from blog.html.
 * Dynamically powered by WordPress Posts, Categories, Tags, and SCF/ACF Block Fields
 * with zero hardcoded articles, static copy, or fallback images.
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

// -----------------------------------------------------------------------------
// 1. Block Attributes & Normalization Helpers
// -----------------------------------------------------------------------------
$block_id = 'blog-listing-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
	$block_id = (string) $block['anchor'];
}

$normalize_select = static function ( $value ): string {
	if ( is_array( $value ) ) {
		if ( isset( $value['value'] ) ) {
			$value = $value['value'];
		} else {
			return '';
		}
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

$extract_plain_text = static function ( string $html ): string {
	$with_breaks = str_ireplace( array( '<br>', '<br/>', '<br />' ), ' ', $html );
	$stripped    = wp_strip_all_tags( $with_breaks );
	$decoded     = html_entity_decode( $stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$cleaned     = str_replace( "\xc2\xa0", ' ', $decoded );
	$normalized  = preg_replace( '/\s+/u', ' ', $cleaned );

	return trim( is_string( $normalized ) ? $normalized : $cleaned );
};

$resolve_image = static function ( $img_field, string $default_alt = '' ): array {
	$url = '';
	$alt = $default_alt;

	if ( is_array( $img_field ) && ! empty( $img_field['url'] ) ) {
		$url = (string) $img_field['url'];
		if ( ! empty( $img_field['alt'] ) ) {
			$alt = (string) $img_field['alt'];
		} elseif ( ! empty( $img_field['title'] ) && '' === $alt ) {
			$alt = (string) $img_field['title'];
		}
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

$compute_read_time = static function ( \WP_Post $post_obj ): int {
	$rendered_or_raw = strip_shortcodes( (string) $post_obj->post_content );
	$plain           = trim( wp_strip_all_tags( $rendered_or_raw ) );
	if ( '' === $plain ) {
		return 1;
	}

	$words = preg_split( '/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY );
	$count = is_array( $words ) ? count( $words ) : 0;

	return max( 1, (int) ceil( $count / 200 ) );
};

$resolve_primary_category = static function ( int $target_post_id, bool $hide_uncat ): ?\WP_Term {
	$cats = get_the_category( $target_post_id );
	if ( empty( $cats ) || is_wp_error( $cats ) ) {
		return null;
	}

	foreach ( $cats as $cat ) {
		if ( 'uncategorized' !== $cat->slug ) {
			return $cat;
		}
	}

	if ( $hide_uncat ) {
		return null;
	}

	return $cats[0] ?? null;
};

$get_post_thumb = static function ( int $target_post_id, string $size = 'large', string $fallback_alt = '' ): array {
	$thumb_id = get_post_thumbnail_id( $target_post_id );
	if ( ! $thumb_id ) {
		return array(
			'url' => '',
			'alt' => $fallback_alt,
		);
	}

	$url = wp_get_attachment_image_url( (int) $thumb_id, $size );
	if ( ! $url ) {
		$url = wp_get_attachment_image_url( (int) $thumb_id, 'full' );
	}

	$alt_meta = get_post_meta( (int) $thumb_id, '_wp_attachment_image_alt', true );
	$alt      = ! empty( $alt_meta ) ? (string) $alt_meta : $fallback_alt;

	return array(
		'url' => $url ? (string) $url : '',
		'alt' => $alt,
	);
};

$get_post_excerpt_text = static function ( \WP_Post $post_obj, int $word_limit ): string {
	$raw_excerpt = has_excerpt( $post_obj ) ? (string) $post_obj->post_excerpt : (string) $post_obj->post_content;
	$cleaned     = wp_strip_all_tags( strip_shortcodes( $raw_excerpt ) );
	$cleaned     = html_entity_decode( $cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$cleaned     = trim( (string) preg_replace( '/\s+/u', ' ', str_replace( "\xc2\xa0", ' ', $cleaned ) ) );

	if ( '' === $cleaned ) {
		return '';
	}

	return wp_trim_words( $cleaned, $word_limit, '...' );
};

// -----------------------------------------------------------------------------
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_blog_listing)
// -----------------------------------------------------------------------------
$posts_per_page     = $read_int( 'blog_posts_per_page', 6, 1, 24 );
$columns            = function_exists( 'get_field' ) ? $normalize_select( get_field( 'blog_columns' ) ) : '2';
$orderby            = function_exists( 'get_field' ) ? $normalize_select( get_field( 'blog_orderby' ) ) : 'date';
$order              = function_exists( 'get_field' ) ? strtoupper( $normalize_select( get_field( 'blog_order' ) ) ) : 'DESC';
$include_cats_raw   = function_exists( 'get_field' ) ? get_field( 'blog_include_categories' ) : null;
$include_cat_ids    = $normalize_id_list( $include_cats_raw );
$hide_uncategorized = $read_bool( 'blog_hide_uncategorized', false );

$show_featured        = $read_bool( 'blog_show_featured', true );
$featured_source      = function_exists( 'get_field' ) ? $normalize_select( get_field( 'blog_featured_source' ) ) : 'auto';
$featured_post_raw    = function_exists( 'get_field' ) ? get_field( 'blog_featured_post' ) : null;
$exclude_featured     = $read_bool( 'blog_exclude_featured', true );
$featured_pill_custom = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_featured_pill' ) ) : '';
$featured_cta_custom  = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_featured_cta_label' ) ) : '';
$show_featured_author = $read_bool( 'blog_show_featured_author', true );

$show_filter_bar     = $read_bool( 'blog_show_filter_bar', true );
$show_category_tabs  = $read_bool( 'blog_show_category_tabs', true );
$filter_cats_raw     = function_exists( 'get_field' ) ? get_field( 'blog_filter_categories' ) : null;
$filter_cat_ids      = $normalize_id_list( $filter_cats_raw );
$all_tab_custom      = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_all_tab_label' ) ) : '';
$show_search_bar     = $read_bool( 'blog_show_search_bar', true );
$search_placeholder  = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_search_placeholder' ) ) : '';
$search_icon_raw     = function_exists( 'get_field' ) ? get_field( 'blog_search_icon' ) : null;
$show_excerpt        = $read_bool( 'blog_show_excerpt', true );
$excerpt_length      = $read_int( 'blog_excerpt_length', 24, 5, 80 );
$show_read_time      = $read_bool( 'blog_show_read_time', true );
$card_cta_custom     = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_card_cta_label' ) ) : '';
$show_pagination     = $read_bool( 'blog_show_pagination', true );
$empty_msg_custom    = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_empty_message' ) ) : '';

$show_sidebar            = $read_bool( 'blog_show_sidebar', true );
$sidebar_show_search     = $read_bool( 'blog_sidebar_show_search', true );
$sidebar_search_title    = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_sidebar_search_title' ) ) : '';
$sidebar_search_ph       = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_sidebar_search_placeholder' ) ) : '';
$sidebar_show_categories = $read_bool( 'blog_sidebar_show_categories', true );
$sidebar_cats_title      = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_sidebar_cats_title' ) ) : '';
$sidebar_show_recent     = $read_bool( 'blog_sidebar_show_recent', true );
$sidebar_recent_title    = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_sidebar_recent_title' ) ) : '';
$sidebar_recent_count    = $read_int( 'blog_sidebar_recent_count', 3, 1, 10 );

$promo_badge = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_promo_badge' ) ) : '';
$promo_title = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_promo_title' ) ) : '';
$promo_desc  = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_promo_description' ) ) : '';
$promo_btn   = function_exists( 'get_field' ) ? get_field( 'blog_promo_button' ) : null;

$sidebar_show_tags  = $read_bool( 'blog_sidebar_show_tags', true );
$sidebar_tags_title = function_exists( 'get_field' ) ? trim( (string) get_field( 'blog_sidebar_tags_title' ) ) : '';
$sidebar_tags_limit = $read_int( 'blog_sidebar_tags_limit', 12, 1, 40 );

$bg_color = function_exists( 'get_field' ) ? $normalize_select( get_field( 'blog_background_color' ) ) : '';

// Validate select options.
$allowed_columns = array(
	'1' => 'col-12',
	'2' => 'col-md-6',
	'3' => 'col-lg-4 col-md-6',
);
if ( ! isset( $allowed_columns[ $columns ] ) ) {
	$columns = '2';
}
$card_col_class = $allowed_columns[ $columns ];
$cards_per_row  = '3' === $columns ? 3 : ( '1' === $columns ? 1 : 2 );

$allowed_orderby = array( 'date', 'modified', 'title', 'comment_count' );
if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
	$orderby = 'date';
}
if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
	$order = 'DESC';
}
if ( 'manual' !== $featured_source ) {
	$featured_source = 'auto';
}

// UI labels (translatable WordPress interface chrome when custom labels aren't supplied).
$label_featured_pill  = '' !== $featured_pill_custom ? $featured_pill_custom : __( 'Featured Story', 'xmastree-blocks' );
$label_featured_cta   = '' !== $featured_cta_custom ? $featured_cta_custom : __( 'Read Full Story', 'xmastree-blocks' );
$label_card_cta       = '' !== $card_cta_custom ? $card_cta_custom : __( 'Read More', 'xmastree-blocks' );
$label_all_tab        = '' !== $all_tab_custom ? $all_tab_custom : __( 'All Stories', 'xmastree-blocks' );
$label_search_ph      = '' !== $search_placeholder ? $search_placeholder : __( 'Search articles...', 'xmastree-blocks' );
$label_empty_msg      = '' !== $empty_msg_custom ? $empty_msg_custom : __( 'No articles found matching your selection.', 'xmastree-blocks' );
$label_sb_search_t    = '' !== $sidebar_search_title ? $sidebar_search_title : __( 'Search Articles', 'xmastree-blocks' );
$label_sb_search_ph   = '' !== $sidebar_search_ph ? $sidebar_search_ph : __( 'Search farm blog...', 'xmastree-blocks' );
$label_sb_cats_t      = '' !== $sidebar_cats_title ? $sidebar_cats_title : __( 'Farm Categories', 'xmastree-blocks' );
$label_sb_recent_t    = '' !== $sidebar_recent_title ? $sidebar_recent_title : __( 'Recent Stories', 'xmastree-blocks' );
$label_sb_tags_t      = '' !== $sidebar_tags_title ? $sidebar_tags_title : __( 'Popular Tags', 'xmastree-blocks' );

$search_icon_data = $resolve_image( $search_icon_raw, '' );
$search_icon_url  = $search_icon_data['url'];

// -----------------------------------------------------------------------------
// 3. Resolve Active Query Filters, Search & Pagination URL Base
// -----------------------------------------------------------------------------
$active_cat_slug = isset( $_GET['blog_cat'] ) ? sanitize_title( wp_unslash( (string) $_GET['blog_cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$active_tag_slug = isset( $_GET['blog_tag'] ) ? sanitize_title( wp_unslash( (string) $_GET['blog_tag'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$search_query    = isset( $_GET['blog_s'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['blog_s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current_page    = isset( $_GET['blog_page'] ) ? max( 1, absint( $_GET['blog_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// Inherit WordPress archive / search context if explicit block params aren't set.
if ( '' === $active_cat_slug && '' === $active_tag_slug && '' === $search_query ) {
	if ( is_category() ) {
		$queried_term = get_queried_object();
		if ( $queried_term instanceof \WP_Term ) {
			$active_cat_slug = $queried_term->slug;
		}
	} elseif ( is_tag() ) {
		$queried_term = get_queried_object();
		if ( $queried_term instanceof \WP_Term ) {
			$active_tag_slug = $queried_term->slug;
		}
	} elseif ( is_search() ) {
		$search_query = trim( (string) get_search_query( false ) );
	}
}

// Resolve canonical page/archive base URL for building filter and pagination links.
$base_url = '';
if ( ! empty( $post_id ) && is_numeric( $post_id ) && (int) $post_id > 0 && is_singular() ) {
	$base_url = (string) get_permalink( (int) $post_id );
} elseif ( is_singular() ) {
	$base_url = (string) get_permalink( get_queried_object_id() );
}
if ( '' === $base_url ) {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST || wp_doing_ajax() ) {
		$base_url = home_url( '/' );
	} else {
		$raw_pagenum = (string) get_pagenum_link( 1, false );
		$base_url    = remove_query_arg( array( 'blog_cat', 'blog_tag', 'blog_s', 'blog_page' ), $raw_pagenum );
	}
}
$base_url = remove_query_arg( array( 'blog_cat', 'blog_tag', 'blog_s', 'blog_page' ), $base_url );

$parsed_base_query = array();
$parsed_url_parts  = wp_parse_url( $base_url );
if ( ! empty( $parsed_url_parts['query'] ) ) {
	parse_str( (string) $parsed_url_parts['query'], $parsed_base_query );
}

$build_blog_url = static function ( array $params ) use ( $base_url ): string {
	$merged = array(
		'blog_cat'  => false,
		'blog_tag'  => false,
		'blog_s'    => false,
		'blog_page' => false,
	);

	foreach ( $params as $k => $v ) {
		if ( null === $v || '' === $v || false === $v ) {
			$merged[ $k ] = false;
		} else {
			$merged[ $k ] = $v;
		}
	}

	if ( isset( $merged['blog_page'] ) && (int) $merged['blog_page'] <= 1 ) {
		$merged['blog_page'] = false;
	}

	return add_query_arg( $merged, $base_url );
};

// -----------------------------------------------------------------------------
// 4. Resolve Taxonomy Terms (Categories & Tags)
// -----------------------------------------------------------------------------
$uncat_term_id = (int) get_option( 'default_category', 1 );

// Validate active category against allowed category scope (when configured).
if ( '' !== $active_cat_slug && ! empty( $include_cat_ids ) ) {
	$active_cat_obj = get_category_by_slug( $active_cat_slug );
	if ( ! $active_cat_obj instanceof \WP_Term || ! in_array( (int) $active_cat_obj->term_id, $include_cat_ids, true ) ) {
		$active_cat_slug = '';
	}
}

$cat_query_args = array(
	'taxonomy'   => 'category',
	'hide_empty' => true,
	'orderby'    => 'name',
	'order'      => 'ASC',
);
if ( $hide_uncategorized ) {
	$cat_query_args['exclude'] = array( $uncat_term_id );
}
if ( ! empty( $include_cat_ids ) ) {
	$cat_query_args['include'] = $include_cat_ids;
}
$sidebar_categories = get_categories( $cat_query_args );
if ( is_wp_error( $sidebar_categories ) ) {
	$sidebar_categories = array();
}

// Top Filter Tabs: explicit subset if selected, else active categories.
$filter_tab_terms = array();
if ( ! empty( $filter_cat_ids ) ) {
	$tab_include = ! empty( $include_cat_ids )
		? array_values( array_intersect( $filter_cat_ids, $include_cat_ids ) )
		: $filter_cat_ids;
	if ( ! empty( $tab_include ) ) {
		$tab_terms = get_categories(
			array(
				'taxonomy'   => 'category',
				'include'    => $tab_include,
				'hide_empty' => false,
				'orderby'    => 'include',
			)
		);
		if ( ! is_wp_error( $tab_terms ) ) {
			$filter_tab_terms = $tab_terms;
		}
	}
} else {
	$filter_tab_terms = $sidebar_categories;
}

$sidebar_tags = array();
if ( $show_sidebar && $sidebar_show_tags ) {
	$tag_args = array(
		'taxonomy'   => 'post_tag',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => $sidebar_tags_limit,
	);
	$fetched_tags = get_tags( $tag_args );
	if ( ! is_wp_error( $fetched_tags ) && is_array( $fetched_tags ) ) {
		$sidebar_tags = $fetched_tags;
	}
}

$is_filtered = ( '' !== $active_cat_slug || '' !== $active_tag_slug || '' !== $search_query );

// -----------------------------------------------------------------------------
// 5. Resolve Featured Article Spotlight (Unfiltered Page 1 Only)
// -----------------------------------------------------------------------------
$featured_post = null;
if ( $show_featured ) {
	if ( 'manual' === $featured_source ) {
		$manual_id = 0;
		if ( $featured_post_raw instanceof \WP_Post ) {
			$manual_id = (int) $featured_post_raw->ID;
		} elseif ( is_numeric( $featured_post_raw ) ) {
			$manual_id = (int) $featured_post_raw;
		}
		if ( $manual_id > 0 ) {
			$candidate = get_post( $manual_id );
			if ( $candidate instanceof \WP_Post && 'post' === $candidate->post_type && 'publish' === $candidate->post_status ) {
				$featured_post = $candidate;
			}
		}
	}

	if ( null === $featured_post && 'auto' === $featured_source ) {
		$sticky_ids = get_option( 'sticky_posts' );
		if ( is_array( $sticky_ids ) && ! empty( $sticky_ids ) ) {
			$sticky_args = array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'post__in'            => array_map( 'intval', $sticky_ids ),
				'posts_per_page'      => 1,
				'ignore_sticky_posts' => true,
				'orderby'             => 'date',
				'order'               => 'DESC',
			);
			if ( ! empty( $include_cat_ids ) ) {
				$sticky_args['category__in'] = $include_cat_ids;
			}
			$sticky_q = new \WP_Query( $sticky_args );
			if ( ! empty( $sticky_q->posts ) ) {
				$featured_post = $sticky_q->posts[0];
			}
			wp_reset_postdata();
		}

		if ( null === $featured_post ) {
			$auto_args = array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 1,
				'ignore_sticky_posts' => true,
				'orderby'             => $orderby,
				'order'               => $order,
			);
			if ( ! empty( $include_cat_ids ) ) {
				$auto_args['category__in'] = $include_cat_ids;
			}
			$auto_q = new \WP_Query( $auto_args );
			if ( ! empty( $auto_q->posts ) ) {
				$featured_post = $auto_q->posts[0];
			}
			wp_reset_postdata();
		}
	}
}

$should_display_featured = $featured_post instanceof \WP_Post && ! $is_filtered && 1 === $current_page;

// -----------------------------------------------------------------------------
// 6. Query Main Articles Grid (Ignoring Sticky Order for Clean Paginated Grid)
// -----------------------------------------------------------------------------
$main_query_args = array(
	'post_type'           => 'post',
	'post_status'         => 'publish',
	'ignore_sticky_posts' => true,
	'posts_per_page'      => $posts_per_page,
	'paged'               => $current_page,
	'orderby'             => $orderby,
	'order'               => $order,
);

if ( ! empty( $include_cat_ids ) ) {
	$main_query_args['category__in'] = $include_cat_ids;
}
if ( '' !== $active_cat_slug ) {
	$main_query_args['category_name'] = $active_cat_slug;
}
if ( '' !== $active_tag_slug ) {
	$main_query_args['tag'] = $active_tag_slug;
}
if ( '' !== $search_query ) {
	$main_query_args['s'] = $search_query;
}
if ( $featured_post instanceof \WP_Post && $exclude_featured && ! $is_filtered ) {
	$main_query_args['post__not_in'] = array( (int) $featured_post->ID );
}

$posts_query = new \WP_Query( $main_query_args );
if ( $posts_query->max_num_pages > 0 && $current_page > (int) $posts_query->max_num_pages ) {
	$current_page             = (int) $posts_query->max_num_pages;
	$main_query_args['paged'] = $current_page;
	wp_reset_postdata();
	$posts_query = new \WP_Query( $main_query_args );
}

$grid_posts = is_array( $posts_query->posts ) ? $posts_query->posts : array();
$total_pages = (int) $posts_query->max_num_pages;
wp_reset_postdata();

// Recent posts widget.
$recent_posts = array();
if ( $show_sidebar && $sidebar_show_recent ) {
	$recent_args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $sidebar_recent_count,
		'ignore_sticky_posts' => true,
		'orderby'             => 'date',
		'order'               => 'DESC',
	);
	if ( ! empty( $include_cat_ids ) ) {
		$recent_args['category__in'] = $include_cat_ids;
	}
	$recent_q     = new \WP_Query( $recent_args );
	$recent_posts = is_array( $recent_q->posts ) ? $recent_q->posts : array();
	wp_reset_postdata();
}

// -----------------------------------------------------------------------------
// 7. Resolve Promo Card, Layout Styles & Overall Visibility
// -----------------------------------------------------------------------------
$promo_btn_url    = '';
$promo_btn_title  = '';
$promo_btn_target = '';
if ( is_array( $promo_btn ) && ! empty( $promo_btn['url'] ) ) {
	$promo_btn_url    = trim( (string) $promo_btn['url'] );
	$promo_btn_title  = ! empty( $promo_btn['title'] ) ? trim( (string) $promo_btn['title'] ) : '';
	$promo_btn_target = ! empty( $promo_btn['target'] ) ? trim( (string) $promo_btn['target'] ) : '';
}
$has_promo_btn  = '' !== $promo_btn_url && '' !== $promo_btn_title;
$has_promo_card = '' !== $promo_badge || '' !== $promo_title || '' !== $promo_desc || $has_promo_btn;

$has_sidebar_content = $show_sidebar && (
	$sidebar_show_search ||
	( $sidebar_show_categories && ! empty( $sidebar_categories ) ) ||
	( $sidebar_show_recent && ! empty( $recent_posts ) ) ||
	$has_promo_card ||
	( $sidebar_show_tags && ! empty( $sidebar_tags ) )
);

// Check whether any posts exist at all in the configured scope.
$has_any_posts = ( $featured_post instanceof \WP_Post ) || ! empty( $grid_posts ) || ! empty( $recent_posts );
if ( ! $has_any_posts && $is_filtered ) {
	// If filtered query was empty, verify whether any posts exist when unfiltered.
	$check_args = array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	);
	if ( ! empty( $include_cat_ids ) ) {
		$check_args['category__in'] = $include_cat_ids;
	}
	$check_q       = new \WP_Query( $check_args );
	$has_any_posts = ! empty( $check_q->posts );
	wp_reset_postdata();
}

$has_renderable_content = $has_any_posts || $has_promo_card;

if ( ! $has_renderable_content && ! $is_preview ) {
	return;
}

// Background style override.
$section_style_attr   = '';
$sanitized_bg_color   = '' !== $bg_color ? sanitize_hex_color( $bg_color ) : null;
if ( is_string( $sanitized_bg_color ) && '' !== $sanitized_bg_color ) {
	$section_style_attr = ' style="' . esc_attr( 'background-color: ' . strtoupper( $sanitized_bg_color ) . ';' ) . '"';
}

// Block wrapper classes.
$wrapper_classes = array( 'xmastree-blog-listing-block' );
if ( ! empty( $block['className'] ) ) {
	$wrapper_classes[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$wrapper_classes[] = 'align' . (string) $block['align'];
}

// Active taxonomy display names for active filter indicator strip.
$active_tag_name = '';
if ( '' !== $active_tag_slug ) {
	$tag_obj = get_term_by( 'slug', $active_tag_slug, 'post_tag' );
	if ( $tag_obj instanceof \WP_Term ) {
		$active_tag_name = $tag_obj->name;
	}
}
$active_cat_name = '';
if ( '' !== $active_cat_slug ) {
	$cat_obj = get_category_by_slug( $active_cat_slug );
	if ( $cat_obj instanceof \WP_Term ) {
		$active_cat_name = $cat_obj->name;
	}
}

// Build numbered pagination items matching Figma V3 ellipsis layout.
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
			'url'    => $build_blog_url(
				array(
					'blog_cat'  => $active_cat_slug,
					'blog_tag'  => $active_tag_slug,
					'blog_s'    => $search_query,
					'blog_page' => $num,
				)
			),
		);
		$prev_num = $num;
	}
}

$aos_featured = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
$aos_filter   = $is_preview ? '' : ' data-aos="fade-up"';
$aos_sidebar  = $is_preview ? '' : ' data-aos="fade-left" data-aos-duration="800"';
$aos_pag      = $is_preview ? '' : ' data-aos="fade-up"';
$search_uid   = sanitize_html_class( $block_id );
?>

<div
	id="<?php echo esc_attr( $block_id ); ?>"
	class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>"
	data-blog-dynamic="1"
	data-base-url="<?php echo esc_url( $base_url ); ?>"
	data-active-cat="<?php echo esc_attr( $active_cat_slug ); ?>"
	data-active-tag="<?php echo esc_attr( $active_tag_slug ); ?>"
	data-active-search="<?php echo esc_attr( $search_query ); ?>"
>
	<?php if ( ! $has_renderable_content && $is_preview ) : ?>
		<section class="blog-main-section py-5"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="container">
				<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
					<span><?php esc_html_e( 'Blog Listing Block — No published posts found yet. Publish articles under Posts in WordPress Admin, or configure Featured, Grid, and Sidebar settings in the block sidebar.', 'xmastree-blocks' ); ?></span>
				</div>
			</div>
		</section>
	<?php else : ?>

		<?php if ( $show_featured ) : ?>
			<div data-blog-featured>
				<?php if ( $should_display_featured ) : ?>
					<?php
					$f_id         = (int) $featured_post->ID;
					$f_title      = get_the_title( $f_id );
					$f_permalink  = get_permalink( $f_id );
					$f_thumb      = $get_post_thumb( $f_id, 'large', $f_title );
					$f_has_img    = '' !== $f_thumb['url'];
					$f_cat        = $resolve_primary_category( $f_id, $hide_uncategorized );
					$f_date_iso   = get_the_date( 'c', $f_id );
					$f_date_label = get_the_date( 'd M Y', $f_id );
					$f_read_mins  = $compute_read_time( $featured_post );
					$f_excerpt    = $show_excerpt ? $get_post_excerpt_text( $featured_post, max( $excerpt_length, 32 ) ) : '';
					$f_author_id  = (int) $featured_post->post_author;
					$f_author     = trim( (string) get_the_author_meta( 'display_name', $f_author_id ) );
					$f_avatar_url = $show_featured_author && $f_author_id > 0 ? (string) get_avatar_url( $f_author_id, array( 'size' => 96 ) ) : '';
					$f_badge_cls  = $f_has_img ? 'blog-featured-pill' : 'blog-featured-pill blog-featured-pill--inline';
					?>
					<section class="blog-featured-section"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<div class="container">
							<article class="blog-featured-card"<?php echo $aos_featured; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<div class="row g-0 align-items-stretch">
									<?php if ( $f_has_img ) : ?>
										<div class="col-lg-6">
											<div class="blog-featured-img-wrap">
												<?php if ( '' !== $label_featured_pill ) : ?>
													<div class="<?php echo esc_attr( $f_badge_cls ); ?>"><?php echo esc_html( $label_featured_pill ); ?></div>
												<?php endif; ?>
												<a href="<?php echo esc_url( $f_permalink ); ?>"<?php echo $is_preview ? ' tabindex="-1"' : ''; ?>>
													<img src="<?php echo esc_url( $f_thumb['url'] ); ?>" alt="<?php echo esc_attr( $f_thumb['alt'] ); ?>" class="blog-featured-img">
												</a>
											</div>
										</div>
									<?php endif; ?>

									<div class="<?php echo esc_attr( $f_has_img ? 'col-lg-6' : 'col-12' ); ?>">
										<div class="blog-featured-content">
											<?php if ( ! $f_has_img && '' !== $label_featured_pill ) : ?>
												<div class="<?php echo esc_attr( $f_badge_cls ); ?>"><?php echo esc_html( $label_featured_pill ); ?></div>
											<?php endif; ?>

											<div class="blog-meta">
												<?php if ( $f_cat instanceof \WP_Term ) : ?>
													<div class="blog-meta-category"><?php echo esc_html( $f_cat->name ); ?></div>
												<?php endif; ?>

												<?php if ( '' !== $f_date_label ) : ?>
													<div class="blog-meta-date">
														<i class="bi bi-calendar3" aria-hidden="true"></i>
														<time datetime="<?php echo esc_attr( (string) $f_date_iso ); ?>"><?php echo esc_html( $f_date_label ); ?></time>
													</div>
												<?php endif; ?>

												<?php if ( $show_read_time ) : ?>
													<div class="blog-meta-readtime">
														<i class="bi bi-clock" aria-hidden="true"></i>
														<span>
															<?php
															/* translators: %d: estimated reading minutes */
															echo esc_html( sprintf( __( '%d min read', 'xmastree-blocks' ), $f_read_mins ) );
															?>
														</span>
													</div>
												<?php endif; ?>
											</div>

											<h2 class="blog-featured-title">
												<a href="<?php echo esc_url( $f_permalink ); ?>"><?php echo esc_html( $f_title ); ?></a>
											</h2>

											<?php if ( '' !== $f_excerpt ) : ?>
												<p><?php echo esc_html( $f_excerpt ); ?></p>
											<?php endif; ?>

											<div class="blog-author-action">
												<?php if ( $show_featured_author && ( '' !== $f_author || '' !== $f_avatar_url ) ) : ?>
													<div class="blog-author-info">
														<?php if ( '' !== $f_avatar_url ) : ?>
															<img src="<?php echo esc_url( $f_avatar_url ); ?>" alt="<?php echo esc_attr( $f_author ); ?>" class="blog-author-avatar">
														<?php endif; ?>
														<?php if ( '' !== $f_author ) : ?>
															<span><?php echo esc_html( $f_author ); ?></span>
														<?php endif; ?>
													</div>
												<?php endif; ?>

												<a href="<?php echo esc_url( $f_permalink ); ?>" class="blog-btn-crimson">
													<?php echo esc_html( $label_featured_cta ); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i>
												</a>
											</div>
										</div>
									</div>
								</div>
							</article>
						</div>
					</section>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php
		$render_main_section = ! empty( $grid_posts ) || $is_filtered || $has_sidebar_content || ! $should_display_featured;
		if ( $render_main_section ) :
			$show_top_tabs    = $show_filter_bar && $show_category_tabs && ! empty( $filter_tab_terms );
			$show_top_search  = $show_filter_bar && $show_search_bar;
			$has_top_bar      = $show_top_tabs || $show_top_search;
			$main_col_class   = $has_sidebar_content ? 'col-lg-8' : 'col-12';
			?>
			<section class="blog-main-section"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div class="container">

					<?php if ( $has_top_bar ) : ?>
						<div class="blog-filter-bar"<?php echo $aos_filter; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php if ( $show_top_tabs ) : ?>
								<div class="blog-filter-tabs" data-blog-tabs role="navigation" aria-label="<?php esc_attr_e( 'Article categories', 'xmastree-blocks' ); ?>">
									<?php
									$all_url       = $build_blog_url(
										array(
											'blog_cat'  => false,
											'blog_tag'  => false,
											'blog_s'    => $search_query,
											'blog_page' => 1,
										)
									);
									$is_all_active = ( '' === $active_cat_slug && '' === $active_tag_slug );
									?>
									<a
										href="<?php echo esc_url( $all_url ); ?>"
										class="blog-filter-btn<?php echo $is_all_active ? ' active' : ''; ?>"
										data-filter="all"
										data-blog-filter-link="1"
										<?php echo $is_all_active ? 'aria-current="page"' : ''; ?>
									><?php echo esc_html( $label_all_tab ); ?></a>

									<?php
									foreach ( $filter_tab_terms as $term ) :
										$is_term_active = ( $active_cat_slug === $term->slug );
										$term_url       = $build_blog_url(
											array(
												'blog_cat'  => $term->slug,
												'blog_tag'  => false,
												'blog_s'    => $search_query,
												'blog_page' => 1,
											)
										);
										?>
										<a
											href="<?php echo esc_url( $term_url ); ?>"
											class="blog-filter-btn<?php echo $is_term_active ? ' active' : ''; ?>"
											data-filter="<?php echo esc_attr( $term->slug ); ?>"
											data-blog-filter-link="1"
											<?php echo $is_term_active ? 'aria-current="page"' : ''; ?>
										><?php echo esc_html( $term->name ); ?></a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<?php if ( $show_top_search ) : ?>
								<form class="event-search-wrapper xmastree-blog-search-form<?php echo ! $show_top_tabs ? ' ms-auto' : ''; ?>" role="search" method="get" action="<?php echo esc_url( $base_url ); ?>" data-blog-search-form>
									<?php
									foreach ( $parsed_base_query as $param_key => $param_val ) :
										if ( is_string( $param_val ) ) :
											?>
											<input type="hidden" name="<?php echo esc_attr( $param_key ); ?>" value="<?php echo esc_attr( $param_val ); ?>">
											<?php
										endif;
									endforeach;
									?>
									<?php if ( '' !== $active_cat_slug ) : ?>
										<input type="hidden" name="blog_cat" value="<?php echo esc_attr( $active_cat_slug ); ?>" data-blog-hidden-cat>
									<?php endif; ?>
									<?php if ( '' !== $search_icon_url ) : ?>
										<img src="<?php echo esc_url( $search_icon_url ); ?>" alt="" class="event-search-icon" aria-hidden="true">
									<?php else : ?>
										<i class="bi bi-search blog-search-icon-bi" aria-hidden="true"></i>
									<?php endif; ?>
									<label class="visually-hidden" for="<?php echo esc_attr( $search_uid . '-search-input' ); ?>"><?php esc_html_e( 'Search articles', 'xmastree-blocks' ); ?></label>
									<input
										type="search"
										id="<?php echo esc_attr( $search_uid . '-search-input' ); ?>"
										name="blog_s"
										value="<?php echo esc_attr( $search_query ); ?>"
										class="form-control"
										placeholder="<?php echo esc_attr( $label_search_ph ); ?>"
										data-blog-search-input
										autocomplete="off"
									>
									<button
										type="button"
										class="blog-search-clear-btn"
										data-blog-search-clear
										aria-label="<?php esc_attr_e( 'Clear search', 'xmastree-blocks' ); ?>"
										<?php echo '' === $search_query ? 'hidden' : ''; ?>
									>
										<i class="bi bi-x-lg" aria-hidden="true"></i>
									</button>
								</form>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<div class="row g-4 g-lg-5">
						<div class="<?php echo esc_attr( $main_col_class ); ?>">
							<div class="xmastree-blog-results" data-blog-results>

								<?php
								$active_cat_in_tabs = false;
								if ( '' !== $active_cat_slug && $show_top_tabs ) {
									foreach ( $filter_tab_terms as $tab_t ) {
										if ( $tab_t->slug === $active_cat_slug ) {
											$active_cat_in_tabs = true;
											break;
										}
									}
								}
								$show_filter_strip = '' !== $active_tag_slug || '' !== $search_query || ( '' !== $active_cat_slug && ! $active_cat_in_tabs );
								?>
								<?php if ( $show_filter_strip ) : ?>
									<div class="blog-active-filters mb-4">
										<span class="blog-active-filters-label"><?php esc_html_e( 'Showing:', 'xmastree-blocks' ); ?></span>
										<?php if ( '' !== $active_cat_name ) : ?>
											<span class="blog-active-filter-chip"><?php echo esc_html( $active_cat_name ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== $active_tag_name ) : ?>
											<?php $tag_chip = str_starts_with( $active_tag_name, '#' ) ? $active_tag_name : '#' . $active_tag_name; ?>
											<span class="blog-active-filter-chip"><?php echo esc_html( $tag_chip ); ?></span>
										<?php endif; ?>
										<?php if ( '' !== $search_query ) : ?>
											<span class="blog-active-filter-chip">&ldquo;<?php echo esc_html( $search_query ); ?>&rdquo;</span>
										<?php endif; ?>
										<a href="<?php echo esc_url( $base_url ); ?>" class="blog-clear-filters-link" data-blog-reset-link>
											<?php esc_html_e( 'Clear Filters', 'xmastree-blocks' ); ?>
										</a>
									</div>
								<?php endif; ?>

								<?php if ( ! empty( $grid_posts ) ) : ?>
									<div class="row g-4">
										<?php
										foreach ( $grid_posts as $index => $card_post ) :
											$c_id         = (int) $card_post->ID;
											$c_title      = get_the_title( $c_id );
											$c_permalink  = get_permalink( $c_id );
											$c_thumb      = $get_post_thumb( $c_id, 'large', $c_title );
											$c_has_img    = '' !== $c_thumb['url'];
											$c_cat        = $resolve_primary_category( $c_id, $hide_uncategorized );
											$c_date_iso   = get_the_date( 'c', $c_id );
											$c_date_label = get_the_date( 'd M Y', $c_id );
											$c_read_mins  = $compute_read_time( $card_post );
											$c_excerpt    = $show_excerpt ? $get_post_excerpt_text( $card_post, $excerpt_length ) : '';

											$c_cats_all   = get_the_category( $c_id );
											$c_cat_slugs  = array();
											if ( is_array( $c_cats_all ) ) {
												foreach ( $c_cats_all as $cat_term ) {
													$c_cat_slugs[] = $cat_term->slug;
												}
											}
											$c_data_cat = ! empty( $c_cat_slugs ) ? implode( ' ', $c_cat_slugs ) : '';
											$aos_delay  = 100 + ( ( $index % $cards_per_row ) * 50 );
											$aos_card   = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="600" data-aos-delay="' . esc_attr( (string) $aos_delay ) . '"';
											?>
											<div class="<?php echo esc_attr( $card_col_class ); ?> blog-card-item" data-category="<?php echo esc_attr( $c_data_cat ); ?>"<?php echo $aos_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
												<article class="blog-card">
													<?php if ( $c_has_img ) : ?>
														<div class="blog-card-img-wrap">
															<?php if ( $c_cat instanceof \WP_Term ) : ?>
																<div class="blog-card-badge"><?php echo esc_html( $c_cat->name ); ?></div>
															<?php endif; ?>
															<a href="<?php echo esc_url( $c_permalink ); ?>"<?php echo $is_preview ? ' tabindex="-1"' : ''; ?>>
																<img src="<?php echo esc_url( $c_thumb['url'] ); ?>" alt="<?php echo esc_attr( $c_thumb['alt'] ); ?>" class="blog-card-img">
															</a>
														</div>
													<?php endif; ?>

													<div class="blog-card-body">
														<?php if ( ! $c_has_img && $c_cat instanceof \WP_Term ) : ?>
															<div class="blog-card-badge blog-card-badge--inline"><?php echo esc_html( $c_cat->name ); ?></div>
														<?php endif; ?>

														<div class="blog-meta">
															<?php if ( '' !== $c_date_label ) : ?>
																<div class="blog-meta-date">
																	<i class="bi bi-calendar3" aria-hidden="true"></i>
																	<time datetime="<?php echo esc_attr( (string) $c_date_iso ); ?>"><?php echo esc_html( $c_date_label ); ?></time>
																</div>
															<?php endif; ?>

															<?php if ( $show_read_time ) : ?>
																<div class="blog-meta-readtime">
																	<i class="bi bi-clock" aria-hidden="true"></i>
																	<span>
																		<?php
																		/* translators: %d: estimated reading minutes */
																		echo esc_html( sprintf( __( '%d min', 'xmastree-blocks' ), $c_read_mins ) );
																		?>
																	</span>
																</div>
															<?php endif; ?>
														</div>

														<h3 class="blog-card-title">
															<a href="<?php echo esc_url( $c_permalink ); ?>"><?php echo esc_html( $c_title ); ?></a>
														</h3>

														<?php if ( '' !== $c_excerpt ) : ?>
															<p><?php echo esc_html( $c_excerpt ); ?></p>
														<?php endif; ?>

														<div class="blog-card-footer">
															<a href="<?php echo esc_url( $c_permalink ); ?>" class="blog-read-more">
																<?php echo esc_html( $label_card_cta ); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i>
															</a>
														</div>
													</div>
												</article>
											</div>
										<?php endforeach; ?>
									</div>

									<?php if ( ! empty( $pagination_items ) ) : ?>
										<?php
										$prev_url = $current_page > 1
											? $build_blog_url(
												array(
													'blog_cat'  => $active_cat_slug,
													'blog_tag'  => $active_tag_slug,
													'blog_s'    => $search_query,
													'blog_page' => $current_page - 1,
												)
											)
											: '';
										$next_url = $current_page < $total_pages
											? $build_blog_url(
												array(
													'blog_cat'  => $active_cat_slug,
													'blog_tag'  => $active_tag_slug,
													'blog_s'    => $search_query,
													'blog_page' => $current_page + 1,
												)
											)
											: '';
										?>
										<nav class="events-pagination-nav" aria-label="<?php esc_attr_e( 'Blog pagination', 'xmastree-blocks' ); ?>"<?php echo $aos_pag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
											<ul class="pagination events-pagination">
												<?php if ( '' !== $prev_url ) : ?>
													<li class="page-item">
														<a class="page-link prev-ctrl" href="<?php echo esc_url( $prev_url ); ?>" data-blog-page-link><?php esc_html_e( 'Prev', 'xmastree-blocks' ); ?></a>
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
																data-blog-page-link
																<?php echo $p_item['active'] ? 'aria-current="page"' : ''; ?>
															><?php echo esc_html( (string) $p_item['number'] ); ?></a>
														</li>
													<?php endif; ?>
												<?php endforeach; ?>

												<?php if ( '' !== $next_url ) : ?>
													<li class="page-item">
														<a class="page-link next-ctrl" href="<?php echo esc_url( $next_url ); ?>" data-blog-page-link><?php esc_html_e( 'Next', 'xmastree-blocks' ); ?></a>
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
										<p class="mb-3"><?php echo esc_html( $label_empty_msg ); ?></p>
										<a href="<?php echo esc_url( $base_url ); ?>" class="blog-btn-crimson" data-blog-reset-link>
											<?php esc_html_e( 'View All Stories', 'xmastree-blocks' ); ?>
										</a>
									</div>
								<?php else : ?>
									<div class="blog-empty-state">
										<p class="mb-0"><?php echo esc_html( $label_empty_msg ); ?></p>
									</div>
								<?php endif; ?>

							</div>
						</div>

						<?php if ( $has_sidebar_content ) : ?>
							<div class="col-lg-4">
								<aside class="blog-sidebar"<?php echo $aos_sidebar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

									<?php if ( $sidebar_show_search ) : ?>
										<div class="blog-sidebar-widget">
											<h4 class="widget-title"><?php echo esc_html( $label_sb_search_t ); ?></h4>
											<form class="widget-search-form xmastree-blog-search-form" role="search" method="get" action="<?php echo esc_url( $base_url ); ?>" data-blog-search-form>
												<?php
												foreach ( $parsed_base_query as $param_key => $param_val ) :
													if ( is_string( $param_val ) ) :
														?>
														<input type="hidden" name="<?php echo esc_attr( $param_key ); ?>" value="<?php echo esc_attr( $param_val ); ?>">
														<?php
													endif;
												endforeach;
												?>
												<?php if ( '' !== $active_cat_slug ) : ?>
													<input type="hidden" name="blog_cat" value="<?php echo esc_attr( $active_cat_slug ); ?>" data-blog-hidden-cat>
												<?php endif; ?>
												<div class="input-group">
													<label class="visually-hidden" for="<?php echo esc_attr( $search_uid . '-sidebar-search' ); ?>"><?php echo esc_html( $label_sb_search_t ); ?></label>
													<input
														type="search"
														id="<?php echo esc_attr( $search_uid . '-sidebar-search' ); ?>"
														name="blog_s"
														value="<?php echo esc_attr( $search_query ); ?>"
														class="form-control"
														placeholder="<?php echo esc_attr( $label_sb_search_ph ); ?>"
														data-blog-search-input
														autocomplete="off"
													>
													<button type="submit" class="btn-search" aria-label="<?php esc_attr_e( 'Search', 'xmastree-blocks' ); ?>">
														<i class="bi bi-search" aria-hidden="true"></i>
													</button>
												</div>
											</form>
										</div>
									<?php endif; ?>

									<?php if ( $sidebar_show_categories && ! empty( $sidebar_categories ) ) : ?>
										<div class="blog-sidebar-widget" data-blog-sidebar-cats>
											<h4 class="widget-title"><?php echo esc_html( $label_sb_cats_t ); ?></h4>
											<ul class="widget-category-list">
												<?php
												foreach ( $sidebar_categories as $cat_term ) :
													$is_cat_active = ( $active_cat_slug === $cat_term->slug );
													$cat_link      = $build_blog_url(
														array(
															'blog_cat'  => $cat_term->slug,
															'blog_tag'  => false,
															'blog_s'    => $search_query,
															'blog_page' => 1,
														)
													);
													?>
													<li class="widget-category-item">
														<a
															href="<?php echo esc_url( $cat_link ); ?>"
															class="widget-category-link<?php echo $is_cat_active ? ' active' : ''; ?>"
															data-blog-tax-link="category"
															data-filter="<?php echo esc_attr( $cat_term->slug ); ?>"
															<?php echo $is_cat_active ? 'aria-current="page"' : ''; ?>
														>
															<?php echo esc_html( $cat_term->name ); ?>
															<span><?php echo esc_html( (string) $cat_term->count ); ?></span>
														</a>
													</li>
												<?php endforeach; ?>
											</ul>
										</div>
									<?php endif; ?>

									<?php if ( $sidebar_show_recent && ! empty( $recent_posts ) ) : ?>
										<div class="blog-sidebar-widget">
											<h4 class="widget-title"><?php echo esc_html( $label_sb_recent_t ); ?></h4>
											<div class="widget-recent-posts">
												<?php
												foreach ( $recent_posts as $r_post ) :
													$r_id        = (int) $r_post->ID;
													$r_title     = get_the_title( $r_id );
													$r_permalink = get_permalink( $r_id );
													$r_date      = get_the_date( 'd M Y', $r_id );
													$r_date_iso  = get_the_date( 'c', $r_id );
													$r_thumb     = $get_post_thumb( $r_id, 'thumbnail', $r_title );
													?>
													<div class="widget-recent-item">
														<?php if ( '' !== $r_thumb['url'] ) : ?>
															<div class="widget-recent-thumb">
																<a href="<?php echo esc_url( $r_permalink ); ?>"<?php echo $is_preview ? ' tabindex="-1"' : ''; ?>>
																	<img src="<?php echo esc_url( $r_thumb['url'] ); ?>" alt="<?php echo esc_attr( $r_thumb['alt'] ); ?>">
																</a>
															</div>
														<?php endif; ?>
														<div class="widget-recent-content">
															<?php if ( '' !== $r_date ) : ?>
																<time class="widget-recent-date" datetime="<?php echo esc_attr( (string) $r_date_iso ); ?>"><?php echo esc_html( $r_date ); ?></time>
															<?php endif; ?>
															<h5 class="widget-recent-title">
																<a href="<?php echo esc_url( $r_permalink ); ?>"><?php echo esc_html( $r_title ); ?></a>
															</h5>
														</div>
													</div>
												<?php endforeach; ?>
											</div>
										</div>
									<?php endif; ?>

									<?php if ( $has_promo_card ) : ?>
										<div class="widget-promo-card">
											<?php if ( '' !== $promo_badge ) : ?>
												<div class="widget-promo-badge"><?php echo esc_html( $promo_badge ); ?></div>
											<?php endif; ?>
											<?php if ( '' !== $promo_title ) : ?>
												<h4 class="widget-promo-title"><?php echo esc_html( $promo_title ); ?></h4>
											<?php endif; ?>
											<?php if ( '' !== $promo_desc ) : ?>
												<p><?php echo nl2br( esc_html( $promo_desc ) ); ?></p>
											<?php endif; ?>
											<?php if ( $has_promo_btn ) : ?>
												<a href="<?php echo esc_url( $promo_btn_url ); ?>"<?php echo ! empty( $promo_btn_target ) ? ' target="' . esc_attr( $promo_btn_target ) . '" rel="noopener noreferrer"' : ''; ?> class="widget-promo-btn">
													<?php echo esc_html( $promo_btn_title ); ?> <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
												</a>
											<?php endif; ?>
										</div>
									<?php endif; ?>

									<?php if ( $sidebar_show_tags && ! empty( $sidebar_tags ) ) : ?>
										<div class="blog-sidebar-widget" data-blog-sidebar-tags>
											<h4 class="widget-title"><?php echo esc_html( $label_sb_tags_t ); ?></h4>
											<div class="widget-tags-cloud">
												<?php
												foreach ( $sidebar_tags as $tag_term ) :
													$is_tag_active = ( $active_tag_slug === $tag_term->slug );
													$tag_link      = $build_blog_url(
														array(
															'blog_cat'  => false,
															'blog_tag'  => $tag_term->slug,
															'blog_s'    => false,
															'blog_page' => 1,
														)
													);
													$tag_label     = str_starts_with( $tag_term->name, '#' ) ? $tag_term->name : '#' . $tag_term->name;
													?>
													<a
														href="<?php echo esc_url( $tag_link ); ?>"
														class="widget-tag-item<?php echo $is_tag_active ? ' active' : ''; ?>"
														data-blog-tax-link="tag"
														data-tag="<?php echo esc_attr( $tag_term->slug ); ?>"
														<?php echo $is_tag_active ? 'aria-current="page"' : ''; ?>
													><?php echo esc_html( $tag_label ); ?></a>
												<?php endforeach; ?>
											</div>
										</div>
									<?php endif; ?>

								</aside>
							</div>
						<?php endif; ?>
					</div>

				</div>
			</section>
		<?php endif; ?>

	<?php endif; ?>
</div>
