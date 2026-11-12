<?php
/**
 * Block Template: Farm Locations
 *
 * Renders the "Three Beautiful Locations. One Christmas Tradition." section from our-farms.html
 * with a centered two-tone heading and a responsive grid of farm location cards.
 * Fully dynamic: powered by SCF/ACF Block Fields with zero hardcoded content or fallback images.
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
$block_id = 'farm-locations-' . ( $block['id'] ?? uniqid() );
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

// -----------------------------------------------------------------------------
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_farm_locations)
// -----------------------------------------------------------------------------
$title     = function_exists( 'get_field' ) ? trim( (string) get_field( 'farm_locations_title' ) ) : '';
$items_raw = function_exists( 'get_field' ) ? get_field( 'farm_locations_items' ) : null;
$columns   = function_exists( 'get_field' ) ? $normalize_select( get_field( 'farm_locations_columns' ) ) : '';
$bg_color  = function_exists( 'get_field' ) ? $normalize_select( get_field( 'farm_locations_background_color' ) ) : '';

// -----------------------------------------------------------------------------
// 3. Resolve Section Classes, Grid Columns & Optional Inline Background
// -----------------------------------------------------------------------------
$class_names = array( 'farm-locations-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . (string) $block['align'];
}

$col_class_map = array(
	'3' => array(
		'class' => 'col-lg-4 col-md-6',
		'count' => 3,
	),
	'4' => array(
		'class' => 'col-lg-3 col-md-6',
		'count' => 4,
	),
	'2' => array(
		'class' => 'col-md-6',
		'count' => 2,
	),
);

if ( '' === $columns || ! isset( $col_class_map[ $columns ] ) ) {
	$columns = '3';
}
$col_class = $col_class_map[ $columns ]['class'];
$col_count = $col_class_map[ $columns ]['count'];

$section_styles     = array();
$sanitized_bg_color = '' !== $bg_color ? sanitize_hex_color( $bg_color ) : null;
if ( is_string( $sanitized_bg_color ) && '' !== $sanitized_bg_color ) {
	$section_styles[] = 'background-color: ' . strtoupper( $sanitized_bg_color );
}
$section_style_attr = ! empty( $section_styles ) ? ' style="' . esc_attr( implode( '; ', $section_styles ) . ';' ) . '"' : '';

// -----------------------------------------------------------------------------
// 4. Resolve Farm Locations Repeater (image [image], name [text], hours [text], link [link])
// -----------------------------------------------------------------------------
$location_rows = array();
if ( function_exists( 'have_rows' ) && have_rows( 'farm_locations_items' ) ) {
	while ( have_rows( 'farm_locations_items' ) ) {
		the_row();
		$location_rows[] = array(
			'image' => get_sub_field( 'image' ),
			'name'  => get_sub_field( 'name' ),
			'hours' => get_sub_field( 'hours' ),
			'link'  => get_sub_field( 'link' ),
		);
	}
} elseif ( is_array( $items_raw ) && ! empty( $items_raw ) ) {
	foreach ( $items_raw as $row ) {
		if ( is_array( $row ) ) {
			$location_rows[] = array(
				'image' => $row['image'] ?? null,
				'name'  => $row['name'] ?? '',
				'hours' => $row['hours'] ?? '',
				'link'  => $row['link'] ?? null,
			);
		}
	}
}

$rendered_locations = array();
foreach ( $location_rows as $row ) {
	$loc_name       = trim( (string) ( $row['name'] ?? '' ) );
	$loc_name_plain = $extract_plain_text( $loc_name );
	$has_loc_name   = '' !== $loc_name_plain;

	$loc_hours       = trim( (string) ( $row['hours'] ?? '' ) );
	$loc_hours_plain = $extract_plain_text( $loc_hours );
	$has_loc_hours   = '' !== $loc_hours_plain;

	$resolved_img = $resolve_image( $row['image'] ?? null, $loc_name_plain );
	$img_url      = $resolved_img['url'];
	$img_alt      = $resolved_img['alt'];

	$loc_link    = $row['link'] ?? null;
	$link_url    = '';
	$link_title  = '';
	$link_target = '';
	if ( is_array( $loc_link ) && ! empty( $loc_link['url'] ) ) {
		$link_url    = trim( (string) $loc_link['url'] );
		$link_title  = ! empty( $loc_link['title'] ) ? trim( (string) $loc_link['title'] ) : '';
		$link_target = ! empty( $loc_link['target'] ) ? trim( (string) $loc_link['target'] ) : '';
	} elseif ( is_string( $loc_link ) && '' !== trim( $loc_link ) ) {
		$link_url = trim( $loc_link );
	}

	if ( '' === $img_url && ! $has_loc_name && ! $has_loc_hours && '' === $link_url ) {
		continue;
	}

	$rendered_locations[] = array(
		'img_url'     => $img_url,
		'img_alt'     => $img_alt,
		'name'        => $has_loc_name ? $loc_name : '',
		'hours'       => $has_loc_hours ? $loc_hours : '',
		'link_url'    => $link_url,
		'link_title'  => $link_title,
		'link_target' => $link_target,
	);
}

// -----------------------------------------------------------------------------
// 5. Resolve Visibility & Heading Format
// -----------------------------------------------------------------------------
$title_plain   = $extract_plain_text( $title );
$has_title     = '' !== $title_plain;
$has_locations = ! empty( $rendered_locations );
$has_content   = $has_title || $has_locations;

if ( ! $has_content && ! $is_preview ) {
	return;
}

$formatted_title = '';
if ( $has_title ) {
	$has_explicit_br = 1 === preg_match( '/<br\b/i', $title );
	$formatted_title = $has_explicit_br ? wp_kses_post( $title ) : nl2br( wp_kses_post( $title ) );
}

// AOS animations active on frontend only so editor blocks remain immediately visible.
$aos_header      = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
$total_locations = count( $rendered_locations );
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if ( $has_content ) : ?>

			<?php if ( $has_title ) : ?>
				<div class="section-header text-center mb-5"<?php echo $aos_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<h2 class="section-title"><?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
				</div>
			<?php endif; ?>

			<?php if ( $has_locations ) : ?>
				<div class="row g-4">
					<?php
					foreach ( $rendered_locations as $index => $loc ) :
						$delay         = 100 * ( ( $index % $col_count ) + 1 );
						$aos_card      = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="600" data-aos-delay="' . esc_attr( (string) $delay ) . '"';
						$item_col_cls  = $col_class;
						$is_last_odd   = ( '2' !== $columns ) && ( 1 === ( $total_locations % 2 ) ) && ( $index === $total_locations - 1 ) && ( $total_locations > 1 );
						if ( $is_last_odd ) {
							$item_col_cls .= ' mx-auto';
						}
						?>
						<div class="<?php echo esc_attr( $item_col_cls ); ?>"<?php echo $aos_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<div class="farm-location-item">
								<?php if ( '' !== $loc['img_url'] ) : ?>
									<img src="<?php echo esc_url( $loc['img_url'] ); ?>" alt="<?php echo esc_attr( $loc['img_alt'] ); ?>" class="farm-location-img">
								<?php endif; ?>

								<?php if ( '' !== $loc['name'] ) : ?>
									<div class="farm-location-header">
										<i class="bi bi-geo-alt-fill farm-location-pin" aria-hidden="true"></i>
										<h3 class="farm-location-name"><?php echo esc_html( $loc['name'] ); ?></h3>
									</div>
								<?php endif; ?>

								<?php if ( '' !== $loc['hours'] ) : ?>
									<div class="farm-location-hours-row">
										<i class="bi bi-clock-fill farm-location-clock" aria-hidden="true"></i>
										<p><?php echo esc_html( $loc['hours'] ); ?></p>
									</div>
								<?php endif; ?>

								<?php if ( '' !== $loc['link_url'] ) : ?>
									<div>
										<a href="<?php echo esc_url( $loc['link_url'] ); ?>"<?php echo ! empty( $loc['link_target'] ) ? ' target="' . esc_attr( $loc['link_target'] ) . '" rel="noopener noreferrer"' : ''; ?> class="farm-location-link">
											<?php if ( '' !== $loc['link_title'] ) : ?>
												<?php echo esc_html( $loc['link_title'] ); ?>
											<?php endif; ?>
											<i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
										</a>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Farm Locations Block — Click here to add section heading and farm locations in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>

