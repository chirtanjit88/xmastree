<?php
/**
 * Block Template: Tree Care Callout Panel
 *
 * Renders the "Keep Your Real Christmas Tree Fresh" rounded callout panel section
 * from real-christmas-trees.html: bordered blush panel containing a side photograph,
 * two-tone uppercase heading, description copy, and optional crimson CTA button.
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
$block_id = 'tree-care-callout-' . ( $block['id'] ?? uniqid() );
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

$resolve_image = static function ( $image_raw, string $fallback_alt = '' ): array {
	$url = '';
	$alt = $fallback_alt;

	if ( is_array( $image_raw ) && ! empty( $image_raw['url'] ) ) {
		$url = (string) $image_raw['url'];
		if ( ! empty( $image_raw['alt'] ) ) {
			$alt = (string) $image_raw['alt'];
		} elseif ( ! empty( $image_raw['title'] ) && '' === $alt ) {
			$alt = (string) $image_raw['title'];
		}
	} elseif ( is_numeric( $image_raw ) && (int) $image_raw > 0 ) {
		$id  = (int) $image_raw;
		$src = wp_get_attachment_image_url( $id, 'large' );
		if ( ! $src ) {
			$src = wp_get_attachment_image_url( $id, 'full' );
		}
		if ( $src ) {
			$url      = (string) $src;
			$alt_meta = get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt_meta ) ) {
				$alt = (string) $alt_meta;
			}
		}
	} elseif ( is_string( $image_raw ) && '' !== trim( $image_raw ) ) {
		$url = trim( $image_raw );
	}

	return array(
		'url' => $url,
		'alt' => $alt,
	);
};

// -----------------------------------------------------------------------------
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_tree_care_callout)
// -----------------------------------------------------------------------------
$image_raw      = function_exists( 'get_field' ) ? get_field( 'callout_image' ) : null;
$heading        = function_exists( 'get_field' ) ? trim( (string) get_field( 'callout_heading' ) ) : '';
$description    = function_exists( 'get_field' ) ? trim( (string) get_field( 'callout_description' ) ) : '';
$button_raw     = function_exists( 'get_field' ) ? get_field( 'callout_button' ) : null;
$media_position = function_exists( 'get_field' ) ? $normalize_select( get_field( 'callout_media_position' ) ) : '';
$panel_bg_color = function_exists( 'get_field' ) ? $normalize_select( get_field( 'callout_panel_background' ) ) : '';
$bg_color       = function_exists( 'get_field' ) ? $normalize_select( get_field( 'callout_background_color' ) ) : '';

if ( ! in_array( $media_position, array( 'left', 'right' ), true ) ) {
	$media_position = 'left';
}

// -----------------------------------------------------------------------------
// 3. Resolve Image, Heading, Description & CTA Link
// -----------------------------------------------------------------------------
$heading_plain = $extract_plain_text( $heading );
$has_heading   = '' !== $heading_plain;
$desc_plain    = $extract_plain_text( $description );
$has_desc      = '' !== $desc_plain;

$image_data = $resolve_image( $image_raw, $heading_plain );
$has_image  = '' !== $image_data['url'];

$btn_title  = '';
$btn_url    = '';
$btn_target = '';
if ( is_array( $button_raw ) ) {
	$btn_url    = ! empty( $button_raw['url'] ) ? trim( (string) $button_raw['url'] ) : '';
	$btn_title  = ! empty( $button_raw['title'] ) ? trim( (string) $button_raw['title'] ) : '';
	$btn_target = ! empty( $button_raw['target'] ) ? trim( (string) $button_raw['target'] ) : '';
}
$has_button = '' !== $btn_url && '' !== $btn_title;

$has_text_col    = $has_heading || $has_desc || $has_button;
$has_any_content = $has_image || $has_text_col;

if ( ! $has_any_content && ! $is_preview ) {
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

$formatted_desc = '';
if ( $has_desc ) {
	$formatted_desc = wp_kses_post( wpautop( $description ) );
}

$class_names = array( 'realtree-care-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . (string) $block['align'];
}

$section_styles     = array();
$sanitized_bg_color = '' !== $bg_color ? sanitize_hex_color( $bg_color ) : null;
if ( is_string( $sanitized_bg_color ) && '' !== $sanitized_bg_color ) {
	$section_styles[] = 'background-color: ' . strtoupper( $sanitized_bg_color );
}
$section_style_attr = ! empty( $section_styles ) ? ' style="' . esc_attr( implode( '; ', $section_styles ) . ';' ) . '"' : '';

$panel_styles     = array();
$sanitized_panel  = '' !== $panel_bg_color ? sanitize_hex_color( $panel_bg_color ) : null;
if ( is_string( $sanitized_panel ) && '' !== $sanitized_panel ) {
	$panel_styles[] = 'background-color: ' . strtoupper( $sanitized_panel );
}
$panel_style_attr = ! empty( $panel_styles ) ? ' style="' . esc_attr( implode( '; ', $panel_styles ) . ';' ) . '"' : '';

$row_classes = 'row align-items-center g-4 g-lg-5';
if ( 'right' === $media_position ) {
	$row_classes .= ' flex-lg-row-reverse';
}

$img_col_class  = $has_text_col ? 'col-lg-6' : 'col-12';
$text_col_class = $has_image ? 'col-lg-6' : 'col-12';
$aos_panel      = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if ( $has_any_content ) : ?>
			<div class="realtree-care-panel"<?php echo $aos_panel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $panel_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div class="<?php echo esc_attr( $row_classes ); ?>">

					<?php if ( $has_image ) : ?>
						<div class="<?php echo esc_attr( $img_col_class ); ?>">
							<div class="realtree-care-img-wrap">
								<img src="<?php echo esc_url( $image_data['url'] ); ?>" alt="<?php echo esc_attr( $image_data['alt'] ); ?>" loading="lazy">
							</div>
						</div>
					<?php endif; ?>

					<?php if ( $has_text_col ) : ?>
						<div class="<?php echo esc_attr( $text_col_class ); ?>">
							<div class="realtree-care-content">
								<?php if ( $has_heading ) : ?>
									<h2><?php echo $formatted_heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
								<?php endif; ?>

								<?php if ( $has_desc ) : ?>
									<div class="realtree-care-copy">
										<?php echo $formatted_desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									</div>
								<?php endif; ?>

								<?php if ( $has_button ) : ?>
									<a href="<?php echo esc_url( $btn_url ); ?>" class="btn-crimson"<?php echo '' !== $btn_target ? ' target="' . esc_attr( $btn_target ) . '"' : ''; ?><?php echo '_blank' === $btn_target ? ' rel="noopener noreferrer"' : ''; ?>>
										<?php echo esc_html( $btn_title ); ?>
										<i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
									</a>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

				</div>
			</div>
		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Tree Care Callout Panel Block — Click here to add photo, heading, description, and CTA button in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>

