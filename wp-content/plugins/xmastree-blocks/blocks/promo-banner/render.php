<?php
/**
 * Block Template: Christmas Promo CTA Banner
 *
 * Renders the "Unique Christmas Decorations and Gifts" rounded CTA banner section from mas-corner.html
 * with optional background image, configurable gradient overlay, uppercase heading, rich copy, and CTA pill button.
 * Fully dynamic: powered by SCF/ACF Block Fields with zero hardcoded content or fallback media.
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
$block_id = 'promo-banner-' . ( $block['id'] ?? uniqid() );
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

$resolve_image = static function ( $img_field ): string {
	if ( is_array( $img_field ) && ! empty( $img_field['url'] ) ) {
		return (string) $img_field['url'];
	}

	if ( is_numeric( $img_field ) && (int) $img_field > 0 ) {
		$src = wp_get_attachment_image_url( (int) $img_field, 'full' );
		if ( $src ) {
			return (string) $src;
		}
	}

	if ( is_string( $img_field ) && '' !== trim( $img_field ) ) {
		return trim( $img_field );
	}

	return '';
};

// -----------------------------------------------------------------------------
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_promo_banner)
// -----------------------------------------------------------------------------
$title         = function_exists( 'get_field' ) ? trim( (string) get_field( 'promo_title' ) ) : '';
$description   = function_exists( 'get_field' ) ? trim( (string) get_field( 'promo_description' ) ) : '';
$button        = function_exists( 'get_field' ) ? get_field( 'promo_button' ) : null;
$bg_image      = function_exists( 'get_field' ) ? get_field( 'promo_bg_image' ) : null;
$overlay_style = function_exists( 'get_field' ) ? $normalize_select( get_field( 'promo_overlay_style' ) ) : '';
$banner_bg     = function_exists( 'get_field' ) ? $normalize_select( get_field( 'promo_banner_bg_color' ) ) : '';
$section_bg    = function_exists( 'get_field' ) ? $normalize_select( get_field( 'promo_section_bg_color' ) ) : '';

// -----------------------------------------------------------------------------
// 3. Resolve Visibility & Processed Values
// -----------------------------------------------------------------------------
$title_plain = $extract_plain_text( $title );
$has_title   = '' !== $title_plain;
$has_desc    = $has_rich_content( $description );

$btn_url    = '';
$btn_title  = '';
$btn_target = '';
if ( is_array( $button ) && ! empty( $button['url'] ) ) {
	$btn_url    = trim( (string) $button['url'] );
	$btn_title  = ! empty( $button['title'] ) ? trim( (string) $button['title'] ) : '';
	$btn_target = ! empty( $button['target'] ) ? trim( (string) $button['target'] ) : '';
} elseif ( is_string( $button ) && '' !== trim( $button ) ) {
	$btn_url = trim( $button );
}
$has_button = '' !== $btn_url && '' !== $btn_title;

$bg_image_url = $resolve_image( $bg_image );
$has_bg_image = '' !== $bg_image_url;

$has_text_content = $has_title || $has_desc || $has_button;
$has_any_content  = $has_text_content || $has_bg_image;

if ( ! $has_any_content && ! $is_preview ) {
	return;
}

$formatted_title = '';
if ( $has_title ) {
	$has_explicit_br = 1 === preg_match( '/<br\b/i', $title );
	$formatted_title = $has_explicit_br ? wp_kses_post( $title ) : nl2br( wp_kses_post( $title ) );
}

// -----------------------------------------------------------------------------
// 4. Resolve Wrapper Classes & Inline Styles (Zero Hardcoded Fallback Image)
// -----------------------------------------------------------------------------
$section_classes = array( 'mas-banner-section-wrap' );
if ( ! empty( $block['className'] ) ) {
	$section_classes[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$section_classes[] = 'align' . (string) $block['align'];
}

$section_style_attr   = '';
$sanitized_section_bg = '' !== $section_bg ? sanitize_hex_color( $section_bg ) : null;
if ( is_string( $sanitized_section_bg ) && '' !== $sanitized_section_bg ) {
	$section_style_attr = ' style="' . esc_attr( 'background-color: ' . strtoupper( $sanitized_section_bg ) . ';' ) . '"';
}

$banner_classes = array( 'mas-bottom-banner' );
if ( $has_bg_image ) {
	$banner_classes[] = 'has-bg-image';
	if ( 'green-gradient' === $overlay_style ) {
		$banner_classes[] = 'has-green-overlay';
	} elseif ( 'dark-gradient' === $overlay_style ) {
		$banner_classes[] = 'has-dark-overlay';
	}
}

$banner_styles       = array();
$sanitized_banner_bg = '' !== $banner_bg ? sanitize_hex_color( $banner_bg ) : null;
if ( is_string( $sanitized_banner_bg ) && '' !== $sanitized_banner_bg ) {
	$banner_styles[] = 'background-color: ' . strtoupper( $sanitized_banner_bg );
}
if ( $has_bg_image ) {
	$banner_styles[] = 'background-image: url(\'' . esc_url( $bg_image_url ) . '\')';
} else {
	$banner_styles[] = 'background-image: none';
}
$banner_style_attr = ! empty( $banner_styles ) ? ' style="' . esc_attr( implode( '; ', $banner_styles ) . ';' ) . '"' : '';

// AOS animation attribute on frontend only so Gutenberg editor remains visible.
$aos_banner = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $section_classes ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if ( $has_any_content ) : ?>
			<div class="<?php echo esc_attr( implode( ' ', $banner_classes ) ); ?>"<?php echo $banner_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $aos_banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php if ( $has_text_content ) : ?>
					<div class="mas-banner-content-box">
						<?php if ( $has_title ) : ?>
							<h2 class="mas-banner-heading"><?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
						<?php endif; ?>

						<?php if ( $has_desc ) : ?>
							<?php echo wp_kses_post( wpautop( $description ) ); ?>
						<?php endif; ?>

						<?php if ( $has_button ) : ?>
							<a href="<?php echo esc_url( $btn_url ); ?>"<?php echo ! empty( $btn_target ) ? ' target="' . esc_attr( $btn_target ) . '" rel="noopener noreferrer"' : ''; ?> class="mas-magic-btn">
								<?php echo esc_html( $btn_title ); ?> <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Christmas Promo CTA Banner Block — Click here to add heading, description, CTA button, and background image in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>
