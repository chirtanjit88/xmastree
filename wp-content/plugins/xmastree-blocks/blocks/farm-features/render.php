<?php
/**
 * Block Template: Farm Features Grid
 *
 * Renders the "More Than Just Christmas Trees" section from our-farms.html
 * with a centered two-tone heading and a responsive grid of bordered feature cards.
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
$block_id = 'farm-features-' . ( $block['id'] ?? uniqid() );
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

$has_rich_content = static function ( string $html ) use ( $extract_plain_text ): bool {
	if ( '' !== $extract_plain_text( $html ) ) {
		return true;
	}

	return 1 === preg_match( '/<(?:img|svg|iframe|video|audio|source|picture)\b/i', $html );
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
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_farm_features)
// -----------------------------------------------------------------------------
$title     = function_exists( 'get_field' ) ? trim( (string) get_field( 'farm_features_title' ) ) : '';
$items_raw = function_exists( 'get_field' ) ? get_field( 'farm_features_items' ) : null;
$columns   = function_exists( 'get_field' ) ? $normalize_select( get_field( 'farm_features_columns' ) ) : '';
$bg_color  = function_exists( 'get_field' ) ? $normalize_select( get_field( 'farm_features_background_color' ) ) : '';

// -----------------------------------------------------------------------------
// 3. Resolve Section Classes, Grid Columns & Optional Inline Background
// -----------------------------------------------------------------------------
$class_names = array( 'farm-features-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . (string) $block['align'];
}

$col_class_map = array(
	'4' => array(
		'class' => 'col-lg-3 col-md-6',
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
// 4. Resolve Feature Cards Repeater (icon [image], title [text], description [wysiwyg])
// -----------------------------------------------------------------------------
$card_rows = array();
if ( function_exists( 'have_rows' ) && have_rows( 'farm_features_items' ) ) {
	while ( have_rows( 'farm_features_items' ) ) {
		the_row();
		$card_rows[] = array(
			'icon'        => get_sub_field( 'icon' ),
			'title'       => get_sub_field( 'title' ),
			'description' => get_sub_field( 'description' ),
		);
	}
} elseif ( is_array( $items_raw ) && ! empty( $items_raw ) ) {
	foreach ( $items_raw as $row ) {
		if ( is_array( $row ) ) {
			$card_rows[] = array(
				'icon'        => $row['icon'] ?? null,
				'title'       => $row['title'] ?? '',
				'description' => $row['description'] ?? '',
			);
		}
	}
}

$rendered_cards = array();
foreach ( $card_rows as $row ) {
	$c_title       = trim( (string) ( $row['title'] ?? '' ) );
	$c_title_plain = $extract_plain_text( $c_title );
	$has_c_title   = '' !== $c_title_plain;

	$c_desc_raw = trim( (string) ( $row['description'] ?? '' ) );
	$has_c_desc = $has_rich_content( $c_desc_raw );
	$c_desc     = $has_c_desc ? $c_desc_raw : '';

	$c_icon       = $row['icon'] ?? null;
	$c_icon_url   = '';
	$c_icon_class = '';
	$c_icon_alt   = $c_title_plain;

	if ( is_array( $c_icon ) || is_numeric( $c_icon ) ) {
		$resolved_icon = $resolve_image( $c_icon, $c_title_plain );
		$c_icon_url    = $resolved_icon['url'];
		$c_icon_alt    = $resolved_icon['alt'];
	} elseif ( is_string( $c_icon ) && '' !== trim( $c_icon ) ) {
		$icon_str = trim( $c_icon );
		if ( str_starts_with( $icon_str, 'http://' ) || str_starts_with( $icon_str, 'https://' ) || str_starts_with( $icon_str, '/' ) ) {
			$c_icon_url = $icon_str;
		} else {
			$c_icon_class = $icon_str;
			if ( str_starts_with( $c_icon_class, 'bi-' ) && ! str_contains( $c_icon_class, 'bi ' ) ) {
				$c_icon_class = 'bi ' . $c_icon_class;
			} elseif ( str_starts_with( $c_icon_class, 'dashicons-' ) && ! str_contains( $c_icon_class, 'dashicons ' ) ) {
				$c_icon_class = 'dashicons ' . $c_icon_class;
			}
		}
	}

	if ( '' === $c_icon_url && '' === $c_icon_class && ! $has_c_title && ! $has_c_desc ) {
		continue;
	}

	$rendered_cards[] = array(
		'icon_url'    => $c_icon_url,
		'icon_class'  => $c_icon_class,
		'icon_alt'    => $c_icon_alt,
		'title'       => $has_c_title ? $c_title : '',
		'description' => $c_desc,
	);
}

// -----------------------------------------------------------------------------
// 5. Resolve Visibility & Heading Format
// -----------------------------------------------------------------------------
$title_plain = $extract_plain_text( $title );
$has_title   = '' !== $title_plain;
$has_cards   = ! empty( $rendered_cards );
$has_content = $has_title || $has_cards;

if ( ! $has_content && ! $is_preview ) {
	return;
}

$formatted_title = '';
if ( $has_title ) {
	$has_explicit_br = 1 === preg_match( '/<br\b/i', $title );
	$formatted_title = $has_explicit_br ? wp_kses_post( $title ) : nl2br( wp_kses_post( $title ) );
}

// AOS animations active on frontend only so editor blocks remain immediately visible.
$aos_header = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if ( $has_content ) : ?>

			<?php if ( $has_title ) : ?>
				<div class="section-header text-center mb-5"<?php echo $aos_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<h2 class="section-title"><?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
				</div>
			<?php endif; ?>

			<?php if ( $has_cards ) : ?>
				<div class="row g-4">
					<?php
					foreach ( $rendered_cards as $index => $card ) :
						$delay    = 100 * ( ( $index % $col_count ) + 1 );
						$aos_card = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="600" data-aos-delay="' . esc_attr( (string) $delay ) . '"';
						?>
						<div class="<?php echo esc_attr( $col_class ); ?>"<?php echo $aos_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<div class="farm-feature-card">
								<?php if ( '' !== $card['icon_url'] ) : ?>
									<img src="<?php echo esc_url( $card['icon_url'] ); ?>" alt="<?php echo esc_attr( $card['icon_alt'] ); ?>" class="farm-feature-icon-img">
								<?php elseif ( '' !== $card['icon_class'] ) : ?>
									<i class="<?php echo esc_attr( $card['icon_class'] ); ?> farm-feature-icon-img d-inline-flex align-items-center justify-content-center fs-1" style="color: #102F1F;" aria-hidden="true"></i>
								<?php endif; ?>

								<?php if ( '' !== $card['title'] ) : ?>
									<h3 class="farm-feature-title"><?php echo esc_html( $card['title'] ); ?></h3>
								<?php endif; ?>

								<?php if ( '' !== $card['description'] ) : ?>
									<?php echo wp_kses_post( wpautop( $card['description'] ) ); ?>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Farm Features Grid Block — Click here to add section heading and feature cards in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>

