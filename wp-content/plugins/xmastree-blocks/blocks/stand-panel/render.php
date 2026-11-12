<?php
/**
 * Block Template: Stand Promo Panel
 *
 * Renders the "A Strong Stand Makes All The Difference." dark green spotlight panel
 * from tree-care.html with a two-tone heading, copy, crimson pill CTA button,
 * and a white rounded spotlight card containing an image.
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
$block_id = 'stand-panel-' . ( $block['id'] ?? uniqid() );
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
			} else {
				$att_title = get_the_title( $id );
				if ( ! empty( $att_title ) && '' === $alt ) {
					$alt = (string) $att_title;
				}
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
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_stand_panel)
// -----------------------------------------------------------------------------
$title       = function_exists( 'get_field' ) ? trim( (string) get_field( 'stand_title' ) ) : '';
$description = function_exists( 'get_field' ) ? trim( (string) get_field( 'stand_description' ) ) : '';
$button      = function_exists( 'get_field' ) ? get_field( 'stand_button' ) : null;
$card_image  = function_exists( 'get_field' ) ? get_field( 'stand_card_image' ) : null;
$card_pos    = function_exists( 'get_field' ) ? $normalize_select( get_field( 'stand_card_position' ) ) : '';
$section_bg  = function_exists( 'get_field' ) ? $normalize_select( get_field( 'stand_section_bg_color' ) ) : '';
$panel_bg    = function_exists( 'get_field' ) ? $normalize_select( get_field( 'stand_panel_bg_color' ) ) : '';

// -----------------------------------------------------------------------------
// 3. Resolve Section Classes, Optional Backgrounds & CTA Link
// -----------------------------------------------------------------------------
$class_names = array( 'treecare-stand-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . (string) $block['align'];
}

if ( 'left' !== $card_pos ) {
	$card_pos = 'right';
}

$section_style_attr = '';
$sanitized_sec_bg   = '' !== $section_bg ? sanitize_hex_color( $section_bg ) : null;
if ( is_string( $sanitized_sec_bg ) && '' !== $sanitized_sec_bg ) {
	$section_style_attr = ' style="' . esc_attr( 'background-color: ' . strtoupper( $sanitized_sec_bg ) . ';' ) . '"';
}

$panel_style_attr = '';
$sanitized_pan_bg = '' !== $panel_bg ? sanitize_hex_color( $panel_bg ) : null;
if ( is_string( $sanitized_pan_bg ) && '' !== $sanitized_pan_bg ) {
	$panel_style_attr = ' style="' . esc_attr( 'background-color: ' . strtoupper( $sanitized_pan_bg ) . ';' ) . '"';
}

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

// -----------------------------------------------------------------------------
// 4. Resolve Content Visibility & Card Image
// -----------------------------------------------------------------------------
$title_plain = $extract_plain_text( $title );
$has_title   = '' !== $title_plain;
$has_desc    = $has_rich_content( $description );
$has_button  = '' !== $btn_url && '' !== $btn_title;

$resolved_img = $resolve_image( $card_image, $has_title ? $title_plain : '' );
$image_url    = $resolved_img['url'];
$image_alt    = $resolved_img['alt'];
$has_image    = '' !== $image_url;

$has_text_col = $has_title || $has_desc || $has_button;
$has_content  = $has_text_col || $has_image;

if ( ! $has_content && ! $is_preview ) {
	return;
}

$formatted_title = '';
if ( $has_title ) {
	$has_explicit_br = 1 === preg_match( '/<br\b/i', $title );
	$formatted_title = $has_explicit_br ? wp_kses_post( $title ) : nl2br( wp_kses_post( $title ) );
}

// Column classes based on card presence and position.
if ( $has_text_col && ! $has_image ) {
	$text_col_class = 'col-lg-10 px-0';
} elseif ( 'left' === $card_pos ) {
	$text_col_class = 'col-lg-6 px-0 ps-lg-4';
} else {
	$text_col_class = 'col-lg-6 mb-4 mb-lg-0 px-0 pe-lg-4';
}

if ( ! $has_text_col && $has_image ) {
	$card_col_class = 'col-lg-8 mx-auto text-center px-0';
} elseif ( 'left' === $card_pos ) {
	$card_col_class = 'col-lg-6 mb-4 mb-lg-0 text-center px-0 pe-lg-2';
} else {
	$card_col_class = 'col-lg-6 text-center px-0 ps-lg-2';
}

// AOS animations active on frontend only so editor blocks remain immediately visible.
$aos_panel = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
$aos_text  = '';
$aos_card  = '';
if ( ! $is_preview ) {
	$aos_text = 'left' === $card_pos
		? ' data-aos="fade-left" data-aos-duration="800" data-aos-delay="100"'
		: ' data-aos="fade-right" data-aos-duration="800" data-aos-delay="100"';
	$aos_card = 'left' === $card_pos
		? ' data-aos="fade-right" data-aos-duration="800" data-aos-delay="150"'
		: ' data-aos="fade-left" data-aos-duration="800" data-aos-delay="150"';
}

$render_text_col = static function () use ( $text_col_class, $aos_text, $has_title, $formatted_title, $has_desc, $description, $has_button, $btn_url, $btn_title, $btn_target ): void {
	?>
	<div class="<?php echo esc_attr( $text_col_class ); ?>"<?php echo $aos_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php if ( $has_title ) : ?>
			<h2 class="treecare-stand-title mb-3"><?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
		<?php endif; ?>

		<?php if ( $has_desc ) : ?>
			<?php echo wp_kses_post( wpautop( $description ) ); ?>
		<?php endif; ?>

		<?php if ( $has_button ) : ?>
			<a href="<?php echo esc_url( $btn_url ); ?>"<?php echo ! empty( $btn_target ) ? ' target="' . esc_attr( $btn_target ) . '" rel="noopener noreferrer"' : ''; ?> class="treecare-stand-btn">
				<?php echo esc_html( $btn_title ); ?> <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
			</a>
		<?php endif; ?>
	</div>
	<?php
};

$render_card_col = static function () use ( $card_col_class, $aos_card, $image_url, $image_alt, $card_pos, $has_text_col ): void {
	$card_class = 'treecare-stand-card';
	if ( ! $has_text_col ) {
		$card_class .= ' mx-auto';
	} elseif ( 'left' === $card_pos ) {
		$card_class .= ' ms-lg-0 me-lg-auto';
	}
	?>
	<div class="<?php echo esc_attr( $card_col_class ); ?>"<?php echo $aos_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="<?php echo esc_attr( $card_class ); ?>">
			<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" class="treecare-stand-img">
		</div>
	</div>
	<?php
};
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="stand-container">
		<?php if ( $has_content ) : ?>
			<div class="treecare-stand-panel"<?php echo $panel_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $aos_panel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<div class="row align-items-center w-100 m-0">
					<?php
					if ( 'left' === $card_pos ) {
						if ( $has_image ) {
							$render_card_col();
						}
						if ( $has_text_col ) {
							$render_text_col();
						}
					} else {
						if ( $has_text_col ) {
							$render_text_col();
						}
						if ( $has_image ) {
							$render_card_col();
						}
					}
					?>
				</div>
			</div>
		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Stand Promo Panel Block — Click here to add heading, copy, button link, and spotlight image in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>
