<?php
/**
 * Block Template: Contact Info Strip
 *
 * Renders the contact information highlights section (Email, Phone, Opening Hours, Delivery)
 * from contact.html and the updated 4-column horizontal layout. Supports both the
 * flat minimal icon strip (default) and bordered contact info cards layout.
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
$block_id = 'contact-info-' . ( $block['id'] ?? uniqid() );
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

$resolve_icon = static function ( $icon_raw, string $default_alt = '' ): array {
	$url   = '';
	$alt   = $default_alt;
	$class = '';

	if ( empty( $icon_raw ) ) {
		return array(
			'url'   => '',
			'alt'   => $alt,
			'class' => '',
		);
	}

	// Unwrap SCF icon_picker array shapes: ['type' => ..., 'value' => ...] or ACF image array ['url' => ...].
	$icon_type = '';
	if ( is_array( $icon_raw ) ) {
		if ( ! empty( $icon_raw['type'] ) ) {
			$icon_type = (string) $icon_raw['type'];
		}
		if ( ! empty( $icon_raw['url'] ) ) {
			$url = (string) $icon_raw['url'];
			if ( ! empty( $icon_raw['alt'] ) ) {
				$alt = (string) $icon_raw['alt'];
			} elseif ( ! empty( $icon_raw['title'] ) && '' === $alt ) {
				$alt = (string) $icon_raw['title'];
			}
			return array(
				'url'   => $url,
				'alt'   => $alt,
				'class' => '',
			);
		}
		if ( isset( $icon_raw['value'] ) ) {
			$icon_raw = $icon_raw['value'];
		} else {
			return array(
				'url'   => '',
				'alt'   => $alt,
				'class' => '',
			);
		}
	}

	if ( is_numeric( $icon_raw ) && (int) $icon_raw > 0 ) {
		$id  = (int) $icon_raw;
		$src = wp_get_attachment_image_url( $id, 'full' );
		if ( $src ) {
			$url      = (string) $src;
			$alt_meta = get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt_meta ) ) {
				$alt = (string) $alt_meta;
			}
		}
	} elseif ( is_string( $icon_raw ) && '' !== trim( $icon_raw ) ) {
		$icon_str = trim( $icon_raw );
		$is_image = 'media_library' === $icon_type
			|| 'url' === $icon_type
			|| str_starts_with( $icon_str, 'http://' )
			|| str_starts_with( $icon_str, 'https://' )
			|| str_starts_with( $icon_str, '/' )
			|| 1 === preg_match( '/\.(?:svg|png|webp|jpe?g|gif)(?:\?.*)?$/i', $icon_str );

		if ( $is_image ) {
			$url = $icon_str;
		} else {
			$class = $icon_str;
			if ( str_starts_with( $class, 'bi-' ) && ! str_contains( $class, 'bi ' ) ) {
				$class = 'bi ' . $class;
			} elseif ( str_starts_with( $class, 'dashicons-' ) && ! str_contains( $class, 'dashicons ' ) ) {
				$class = 'dashicons ' . $class;
			}
		}
	}

	return array(
		'url'   => $url,
		'alt'   => $alt,
		'class' => $class,
	);
};

// -----------------------------------------------------------------------------
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_contact_info)
// -----------------------------------------------------------------------------
$title     = function_exists( 'get_field' ) ? trim( (string) get_field( 'contact_info_title' ) ) : '';
$items_raw = function_exists( 'get_field' ) ? get_field( 'contact_info_items' ) : null;
$layout    = function_exists( 'get_field' ) ? $normalize_select( get_field( 'contact_info_style' ) ) : '';
$columns   = function_exists( 'get_field' ) ? $normalize_select( get_field( 'contact_info_columns' ) ) : '';
$bg_color  = function_exists( 'get_field' ) ? $normalize_select( get_field( 'contact_info_background_color' ) ) : '';

if ( ! in_array( $layout, array( 'minimal', 'cards' ), true ) ) {
	$layout = 'minimal';
}

// -----------------------------------------------------------------------------
// 3. Resolve Section Classes, Grid Columns & Optional Inline Background
// -----------------------------------------------------------------------------
$class_names = array(
	'contact-info-section',
	'contact-info-section--' . $layout,
);
if ( ! empty( $block['className'] ) ) {
	$class_names[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . (string) $block['align'];
}

$col_class_map = array(
	'4' => array(
		'class' => 'col-xl-3 col-md-6',
		'count' => 4,
	),
	'3' => array(
		'class' => 'col-lg-4 col-md-6',
		'count' => 3,
	),
	'2' => array(
		'class' => 'col-md-6',
		'count' => 2,
	),
);

if ( '' === $columns || ! isset( $col_class_map[ $columns ] ) ) {
	$columns = '4';
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
// 4. Resolve Contact Items Repeater (icon [icon_picker], title [text], value [text], link_url [text])
// -----------------------------------------------------------------------------
$raw_rows = array();
if ( function_exists( 'have_rows' ) && have_rows( 'contact_info_items' ) ) {
	while ( have_rows( 'contact_info_items' ) ) {
		the_row();
		$raw_rows[] = array(
			'icon'     => get_sub_field( 'icon' ),
			'title'    => get_sub_field( 'title' ),
			'value'    => get_sub_field( 'value' ),
			'link_url' => get_sub_field( 'link_url' ),
		);
	}
} elseif ( is_array( $items_raw ) && ! empty( $items_raw ) ) {
	foreach ( $items_raw as $row ) {
		if ( is_array( $row ) ) {
			$raw_rows[] = array(
				'icon'     => $row['icon'] ?? null,
				'title'    => $row['title'] ?? '',
				'value'    => $row['value'] ?? '',
				'link_url' => $row['link_url'] ?? '',
			);
		}
	}
}

$allowed_protocols = array_unique( array_merge( wp_allowed_protocols(), array( 'mailto', 'tel' ) ) );
$rendered_items    = array();

foreach ( $raw_rows as $row ) {
	$item_title       = trim( (string) ( $row['title'] ?? '' ) );
	$item_title_plain = $extract_plain_text( $item_title );
	$has_item_title   = '' !== $item_title_plain;

	$item_value_raw   = trim( (string) ( $row['value'] ?? '' ) );
	$item_value_plain = $extract_plain_text( $item_value_raw );
	$has_item_value   = '' !== $item_value_plain;

	$item_link_raw = trim( (string) ( $row['link_url'] ?? '' ) );
	$item_link_url = '' !== $item_link_raw ? esc_url( $item_link_raw, $allowed_protocols ) : '';

	$icon_data      = $resolve_icon( $row['icon'] ?? null, $item_title_plain );
	$has_icon_image = '' !== $icon_data['url'];
	$has_icon_class = '' !== $icon_data['class'];

	if ( ! $has_icon_image && ! $has_icon_class && ! $has_item_title && ! $has_item_value ) {
		continue;
	}

	$formatted_value = '';
	if ( $has_item_value ) {
		$has_br          = 1 === preg_match( '/<br\b/i', $item_value_raw );
		$safe_value      = wp_kses(
			$item_value_raw,
			array(
				'br'     => array(),
				'strong' => array(),
				'em'     => array(),
				'span'   => array( 'class' => array() ),
			)
		);
		$formatted_value = $has_br ? $safe_value : nl2br( $safe_value );
	}

	$rendered_items[] = array(
		'icon_url'   => $icon_data['url'],
		'icon_alt'   => $icon_data['alt'],
		'icon_class' => $icon_data['class'],
		'title'      => $has_item_title ? $item_title : '',
		'value_html' => $formatted_value,
		'link_url'   => $item_link_url,
	);
}

// -----------------------------------------------------------------------------
// 5. Resolve Visibility & Heading Format
// -----------------------------------------------------------------------------
$title_plain = $extract_plain_text( $title );
$has_title   = '' !== $title_plain;
$has_items   = ! empty( $rendered_items );
$has_content = $has_title || $has_items;

if ( ! $has_content && ! $is_preview ) {
	return;
}

$formatted_title = '';
if ( $has_title ) {
	$has_explicit_br = 1 === preg_match( '/<br\b/i', $title );
	$formatted_title = $has_explicit_br ? wp_kses_post( $title ) : nl2br( wp_kses_post( $title ) );
}

$aos_header  = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="700"';
$item_class  = 'cards' === $layout ? 'contact-info-card' : 'contact-info-strip-item';
$row_classes = 'row g-4 justify-content-center contact-info-grid';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="contact-cards-container">
		<?php if ( $has_content ) : ?>

			<?php if ( $has_title ) : ?>
				<div class="section-header text-center mb-4"<?php echo $aos_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<h2 class="section-title"><?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
				</div>
			<?php endif; ?>

			<?php if ( $has_items ) : ?>
				<div class="<?php echo esc_attr( $row_classes ); ?>">
					<?php
					foreach ( $rendered_items as $index => $item ) :
						$delay    = 100 * ( ( $index % $col_count ) + 1 );
						$aos_item = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="600" data-aos-delay="' . esc_attr( (string) $delay ) . '"';
						$is_ext   = '' !== $item['link_url'] && ( str_starts_with( $item['link_url'], 'http://' ) || str_starts_with( $item['link_url'], 'https://' ) );
						?>
						<div class="<?php echo esc_attr( $col_class ); ?>"<?php echo $aos_item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<div class="<?php echo esc_attr( $item_class ); ?>">
								<?php if ( '' !== $item['icon_url'] || '' !== $item['icon_class'] ) : ?>
									<div class="contact-info-icon-wrap">
										<?php if ( '' !== $item['icon_url'] ) : ?>
											<img src="<?php echo esc_url( $item['icon_url'] ); ?>" alt="<?php echo esc_attr( $item['icon_alt'] ); ?>" class="contact-info-icon">
										<?php else : ?>
											<i class="<?php echo esc_attr( $item['icon_class'] ); ?> contact-info-bi-icon" aria-hidden="true"></i>
										<?php endif; ?>
									</div>
								<?php endif; ?>

								<?php if ( '' !== $item['title'] ) : ?>
									<h3 class="contact-info-item-title"><?php echo esc_html( $item['title'] ); ?></h3>
								<?php endif; ?>

								<?php if ( '' !== $item['value_html'] ) : ?>
									<div class="contact-info-item-value">
										<?php if ( '' !== $item['link_url'] ) : ?>
											<a href="<?php echo esc_url( $item['link_url'], $allowed_protocols ); ?>"<?php echo $is_ext ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo $item['value_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
										<?php else : ?>
											<span><?php echo $item['value_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Contact Info Strip Block — Click here to add contact items (Email, Phone, Opening Hours, Delivery) in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>
