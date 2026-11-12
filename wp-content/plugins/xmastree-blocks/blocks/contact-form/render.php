<?php
/**
 * Block Template: Contact Form & Photo
 *
 * Renders the "We'd Love To Hear From You" contact form panel (powered by a dynamic
 * Forminator form shortcode) paired with a rounded side photograph from contact.html.
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
$block_id = 'contact-form-' . ( $block['id'] ?? uniqid() );
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
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_contact_form)
// -----------------------------------------------------------------------------
$title          = function_exists( 'get_field' ) ? trim( (string) get_field( 'contact_form_title' ) ) : '';
$subtitle       = function_exists( 'get_field' ) ? trim( (string) get_field( 'contact_form_subtitle' ) ) : '';
$shortcode_raw  = function_exists( 'get_field' ) ? trim( (string) get_field( 'contact_form_shortcode' ) ) : '';
$image_field    = function_exists( 'get_field' ) ? get_field( 'contact_form_image' ) : null;
$media_position = function_exists( 'get_field' ) ? $normalize_select( get_field( 'contact_form_media_position' ) ) : '';
$bg_color       = function_exists( 'get_field' ) ? $normalize_select( get_field( 'contact_form_background_color' ) ) : '';

if ( ! in_array( $media_position, array( 'right', 'left' ), true ) ) {
	$media_position = 'right';
}

// -----------------------------------------------------------------------------
// 3. Resolve Title, Subtitle, Forminator Shortcode, and Photo
// -----------------------------------------------------------------------------
$title_plain = $extract_plain_text( $title );
$has_title   = '' !== $title_plain;

$formatted_title = '';
if ( $has_title ) {
	$has_explicit_br = 1 === preg_match( '/<br\b/i', $title );
	$formatted_title = $has_explicit_br ? wp_kses_post( $title ) : nl2br( wp_kses_post( $title ) );
}

$subtitle_plain = $extract_plain_text( $subtitle );
$has_subtitle   = '' !== $subtitle_plain;

$formatted_subtitle = '';
if ( $has_subtitle ) {
	$has_sub_br         = 1 === preg_match( '/<br\b/i', $subtitle );
	$formatted_subtitle = $has_sub_br ? wp_kses_post( $subtitle ) : nl2br( wp_kses_post( $subtitle ) );
}

// Normalize and execute Forminator shortcode (accepts full [forminator_form id="..."] or numeric ID).
$form_html      = '';
$shortcode_note = '';
if ( '' !== $shortcode_raw ) {
	$decoded_sc = html_entity_decode( $shortcode_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$clean_sc   = str_replace(
		array( '“', '”', '″', '‘', '’', '′' ),
		array( '"', '"', '"', "'", "'", "'" ),
		trim( $decoded_sc )
	);

	if ( ctype_digit( $clean_sc ) && (int) $clean_sc > 0 ) {
		$clean_sc = '[forminator_form id="' . (int) $clean_sc . '"]';
	}

	if ( 1 === preg_match( '/^\[([a-zA-Z0-9_-]+)(?:\s+[^\]]*)?\]$/', $clean_sc, $sc_match ) ) {
		$tag_name = $sc_match[1];
		if ( shortcode_exists( $tag_name ) ) {
			$rendered_sc = do_shortcode( $clean_sc );
			if ( trim( $rendered_sc ) !== '' && trim( $rendered_sc ) !== $clean_sc ) {
				$form_html = $rendered_sc;
			} elseif ( $is_preview ) {
				$shortcode_note = __( 'Forminator shortcode returned no output. Check that the form ID is published.', 'xmastree-blocks' );
			}
		} elseif ( $is_preview ) {
			$shortcode_note = __( 'Forminator plugin shortcode is not active.', 'xmastree-blocks' );
		}
	} elseif ( $is_preview ) {
		$shortcode_note = __( 'Invalid shortcode format. Expected e.g. [forminator_form id="565"].', 'xmastree-blocks' );
	}
}

$has_form_html  = '' !== trim( $form_html );
$has_form_panel = $has_title || $has_subtitle || $has_form_html || '' !== $shortcode_note;

$image_data = $resolve_image( $image_field, $has_title ? $title_plain : '' );
$image_url  = $image_data['url'];
$image_alt  = $image_data['alt'];
$has_image  = '' !== $image_url;

$has_content = $has_form_panel || $has_image;
if ( ! $has_content && ! $is_preview ) {
	return;
}

// -----------------------------------------------------------------------------
// 4. Resolve Section Classes, Grid Columns & Optional Inline Background
// -----------------------------------------------------------------------------
$class_names = array(
	'about-story-section',
	'bg-cream',
	'contact-form-section',
);
if ( ! empty( $block['className'] ) ) {
	$class_names[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . (string) $block['align'];
}

$section_styles     = array();
$sanitized_bg_color = '' !== $bg_color ? sanitize_hex_color( $bg_color ) : null;
if ( is_string( $sanitized_bg_color ) && '' !== $sanitized_bg_color ) {
	$section_styles[] = 'background-color: ' . strtoupper( $sanitized_bg_color ) . ' !important';
}
$section_style_attr = ! empty( $section_styles ) ? ' style="' . esc_attr( implode( '; ', $section_styles ) . ';' ) . '"' : '';

$row_classes = array( 'row', 'align-items-center', 'g-5' );
if ( 'left' === $media_position && $has_form_panel && $has_image ) {
	$row_classes[] = 'flex-lg-row-reverse';
}

$form_col_class  = ( $has_form_panel && $has_image ) ? 'col-lg-6' : 'col-lg-8 mx-auto';
$image_col_class = ( $has_form_panel && $has_image ) ? 'col-lg-6' : 'col-lg-8 mx-auto';

$aos_form    = $is_preview ? '' : ( 'left' === $media_position ? ' data-aos="fade-left"' : ' data-aos="fade-right"' );
$aos_photo   = $is_preview ? '' : ( 'left' === $media_position ? ' data-aos="fade-right" data-aos-delay="150"' : ' data-aos="fade-left" data-aos-delay="150"' );
$preview_att = $is_preview ? ' data-contact-form-preview="1"' : '';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $preview_att; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if ( $has_content ) : ?>
			<div class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">

				<?php if ( $has_form_panel ) : ?>
					<div class="<?php echo esc_attr( $form_col_class ); ?>"<?php echo $aos_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<div class="contact-form-panel">
							<?php if ( $has_title ) : ?>
								<h2 class="section-title mb-2"><?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
							<?php endif; ?>

							<?php if ( $has_subtitle ) : ?>
								<p class="contact-form-subtitle"><?php echo $formatted_subtitle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
							<?php endif; ?>

							<?php if ( $has_form_html ) : ?>
								<div class="contact-forminator-wrap">
									<?php echo $form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							<?php elseif ( '' !== $shortcode_note && $is_preview ) : ?>
								<div class="p-3 border border-warning rounded bg-white text-dark small">
									<?php echo esc_html( $shortcode_note ); ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( $has_image ) : ?>
					<div class="<?php echo esc_attr( $image_col_class ); ?>"<?php echo $aos_photo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<div class="rounded-photo">
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" class="contact-family-img">
						</div>
					</div>
				<?php endif; ?>

			</div>
		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Contact Form & Photo Block — Click here to add the heading, Forminator shortcode, and side photo in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>

