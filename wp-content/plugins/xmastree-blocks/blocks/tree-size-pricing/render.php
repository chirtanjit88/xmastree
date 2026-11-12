<?php
/**
 * Block Template: Tree Size & Pricing Grid
 *
 * Renders the "Find Your Perfect Tree Size" pricing tiers section from real-christmas-trees.html:
 * centered heading + subtitle, responsive 5-column (configurable 3/4/5) card grid with
 * icon circle, size title, height pill badge, Frijole price, and optional dark-green featured tier.
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
$block_id = 'tree-size-pricing-' . ( $block['id'] ?? uniqid() );
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
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_tree_size_pricing)
// -----------------------------------------------------------------------------
$heading     = function_exists( 'get_field' ) ? trim( (string) get_field( 'pricing_heading' ) ) : '';
$subtitle    = function_exists( 'get_field' ) ? trim( (string) get_field( 'pricing_subtitle' ) ) : '';
$tiers_raw   = function_exists( 'get_field' ) ? get_field( 'pricing_tiers' ) : null;
$columns     = function_exists( 'get_field' ) ? $normalize_select( get_field( 'pricing_columns' ) ) : '';
$bg_color    = function_exists( 'get_field' ) ? $normalize_select( get_field( 'pricing_background_color' ) ) : '';

if ( ! in_array( $columns, array( '5', '4', '3' ), true ) ) {
	$columns = '5';
}

// -----------------------------------------------------------------------------
// 3. Process Pricing Tiers Repeater
// -----------------------------------------------------------------------------
$raw_rows = array();
if ( function_exists( 'have_rows' ) && have_rows( 'pricing_tiers' ) ) {
	while ( have_rows( 'pricing_tiers' ) ) {
		the_row();
		$raw_rows[] = array(
			'icon'        => get_sub_field( 'icon' ),
			'size_name'   => get_sub_field( 'size_name' ),
			'size_height' => get_sub_field( 'size_height' ),
			'price'       => get_sub_field( 'price' ),
			'is_featured' => get_sub_field( 'is_featured' ),
		);
	}
} elseif ( is_array( $tiers_raw ) && ! empty( $tiers_raw ) ) {
	foreach ( $tiers_raw as $row ) {
		if ( is_array( $row ) ) {
			$raw_rows[] = array(
				'icon'        => $row['icon'] ?? null,
				'size_name'   => $row['size_name'] ?? '',
				'size_height' => $row['size_height'] ?? '',
				'price'       => $row['price'] ?? '',
				'is_featured' => $row['is_featured'] ?? false,
			);
		}
	}
}

$tiers = array();
foreach ( $raw_rows as $row ) {
	$size_name   = trim( (string) ( $row['size_name'] ?? '' ) );
	$size_height = trim( (string) ( $row['size_height'] ?? '' ) );
	$price       = trim( (string) ( $row['price'] ?? '' ) );
	$is_featured = ! empty( $row['is_featured'] );
	$icon_data   = $resolve_icon( $row['icon'] ?? null, $extract_plain_text( $size_name ) );

	$has_icon = '' !== $icon_data['url'] || '' !== $icon_data['class'];
	if ( ! $has_icon && '' === $size_name && '' === $size_height && '' === $price ) {
		continue;
	}

	$tiers[] = array(
		'icon_url'    => $icon_data['url'],
		'icon_alt'    => $icon_data['alt'],
		'icon_class'  => $icon_data['class'],
		'size_name'   => $size_name,
		'size_height' => $size_height,
		'price'       => $price,
		'is_featured' => $is_featured,
	);
}

// -----------------------------------------------------------------------------
// 4. Resolve Section Visibility, Formatting & Classes
// -----------------------------------------------------------------------------
$heading_plain  = $extract_plain_text( $heading );
$has_heading    = '' !== $heading_plain;
$subtitle_plain = $extract_plain_text( $subtitle );
$has_subtitle   = '' !== $subtitle_plain;
$has_header     = $has_heading || $has_subtitle;
$has_tiers      = ! empty( $tiers );
$has_content    = $has_header || $has_tiers;

if ( ! $has_content && ! $is_preview ) {
	return;
}

$formatted_heading = '';
if ( $has_heading ) {
	$has_explicit_br   = 1 === preg_match( '/<br\b/i', $heading );
	$safe_heading      = wp_kses(
		$heading,
		array(
			'br'     => array(),
			'span'   => array( 'class' => array() ),
			'strong' => array(),
			'em'     => array(),
		)
	);
	$formatted_heading = $has_explicit_br ? $safe_heading : nl2br( $safe_heading );
}

$class_names = array( 'realtree-pricing-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . (string) $block['align'];
}

$grid_classes = array(
	'realtree-pricing-grid',
	'realtree-pricing-grid--cols-' . $columns,
);

$section_styles     = array();
$sanitized_bg_color = '' !== $bg_color ? sanitize_hex_color( $bg_color ) : null;
if ( is_string( $sanitized_bg_color ) && '' !== $sanitized_bg_color ) {
	$section_styles[] = 'background-color: ' . strtoupper( $sanitized_bg_color );
}
$section_style_attr = ! empty( $section_styles ) ? ' style="' . esc_attr( implode( '; ', $section_styles ) . ';' ) . '"' : '';

$aos_header = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if ( $has_content ) : ?>

			<?php if ( $has_header ) : ?>
				<div class="realtree-pricing-header"<?php echo $aos_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php if ( $has_heading ) : ?>
						<h2 class="section-title"><?php echo $formatted_heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
					<?php endif; ?>

					<?php if ( $has_subtitle ) : ?>
						<p><?php echo nl2br( esc_html( $subtitle ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $has_tiers ) : ?>
				<div class="<?php echo esc_attr( implode( ' ', $grid_classes ) ); ?>">
					<?php
					foreach ( $tiers as $index => $tier ) :
						$card_classes = array( 'realtree-price-card' );
						if ( $tier['is_featured'] ) {
							$card_classes[] = 'realtree-price-featured';
						}
						$delay    = 100 + ( $index * 50 );
						$aos_card = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="600" data-aos-delay="' . esc_attr( (string) $delay ) . '"';
						?>
						<div class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>"<?php echo $aos_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php if ( '' !== $tier['icon_url'] || '' !== $tier['icon_class'] ) : ?>
								<div class="realtree-price-icon">
									<?php if ( '' !== $tier['icon_url'] ) : ?>
										<img src="<?php echo esc_url( $tier['icon_url'] ); ?>" alt="<?php echo esc_attr( $tier['icon_alt'] ); ?>" class="realtree-price-icon-img">
									<?php else : ?>
										<i class="<?php echo esc_attr( $tier['icon_class'] ); ?>" aria-hidden="true"></i>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<?php if ( '' !== $tier['size_name'] ) : ?>
								<h3 class="realtree-size-name"><?php echo esc_html( $tier['size_name'] ); ?></h3>
							<?php endif; ?>

							<?php if ( '' !== $tier['size_height'] ) : ?>
								<div class="realtree-size-height"><?php echo esc_html( $tier['size_height'] ); ?></div>
							<?php endif; ?>

							<?php if ( '' !== $tier['price'] ) : ?>
								<div class="realtree-size-price"><?php echo esc_html( $tier['price'] ); ?></div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Tree Size & Pricing Grid Block — Click here to add heading, subtitle, and tree size pricing cards in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>

