<?php
/**
 * Block Template: Farm Moments Carousel
 *
 * Renders the "Moments From The Farm" Owl Carousel section from our-farms.html
 * with a centered two-tone heading, rounded photo slides, and side circular arrows.
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
$block_id = 'farm-moments-' . ( $block['id'] ?? uniqid() );
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

$resolve_gallery_image = static function ( $img_entry, string $fallback_alt = '' ): ?array {
	$url = '';
	$alt = $fallback_alt;

	if ( is_array( $img_entry ) && ! empty( $img_entry['url'] ) ) {
		$url = (string) $img_entry['url'];
		if ( ! empty( $img_entry['alt'] ) ) {
			$alt = (string) $img_entry['alt'];
		} elseif ( ! empty( $img_entry['title'] ) ) {
			$alt = (string) $img_entry['title'];
		}
	} elseif ( is_numeric( $img_entry ) && (int) $img_entry > 0 ) {
		$id      = (int) $img_entry;
		$img_src = wp_get_attachment_image_url( $id, 'large' );
		if ( ! $img_src ) {
			$img_src = wp_get_attachment_image_url( $id, 'full' );
		}
		if ( $img_src ) {
			$url      = (string) $img_src;
			$alt_meta = get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt_meta ) ) {
				$alt = (string) $alt_meta;
			} else {
				$att_title = get_the_title( $id );
				if ( ! empty( $att_title ) ) {
					$alt = (string) $att_title;
				}
			}
		}
	} elseif ( is_string( $img_entry ) && '' !== trim( $img_entry ) ) {
		$url = trim( $img_entry );
	}

	if ( '' === $url ) {
		return null;
	}

	if ( '' === $alt ) {
		$alt = get_bloginfo( 'name' );
	}

	return array(
		'url' => $url,
		'alt' => $alt,
	);
};

// -----------------------------------------------------------------------------
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_farm_moments)
// -----------------------------------------------------------------------------
$title       = function_exists( 'get_field' ) ? trim( (string) get_field( 'farm_moments_title' ) ) : '';
$gallery_raw = function_exists( 'get_field' ) ? get_field( 'farm_moments_gallery' ) : null;
$bg_color    = function_exists( 'get_field' ) ? $normalize_select( get_field( 'farm_moments_background_color' ) ) : '';

// -----------------------------------------------------------------------------
// 3. Resolve Section Classes & Optional Inline Background
// -----------------------------------------------------------------------------
$class_names = array( 'farm-moments-section' );
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

// -----------------------------------------------------------------------------
// 4. Resolve Carousel Slides
// -----------------------------------------------------------------------------
$title_plain    = $extract_plain_text( $title );
$has_title      = '' !== $title_plain;
$fallback_label = $has_title ? $title_plain : get_bloginfo( 'name' );

$rendered_slides = array();
if ( is_array( $gallery_raw ) && ! empty( $gallery_raw ) ) {
	foreach ( $gallery_raw as $entry ) {
		$resolved = $resolve_gallery_image( $entry, $fallback_label );
		if ( null !== $resolved ) {
			$rendered_slides[] = $resolved;
		}
	}
}

$has_slides  = ! empty( $rendered_slides );
$has_content = $has_title || $has_slides;

if ( ! $has_content && ! $is_preview ) {
	return;
}

$formatted_title = '';
if ( $has_title ) {
	$has_explicit_br = 1 === preg_match( '/<br\b/i', $title );
	$formatted_title = $has_explicit_br ? wp_kses_post( $title ) : nl2br( wp_kses_post( $title ) );
}

// AOS animations active on frontend only so editor blocks remain immediately visible.
$aos_header   = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
$aos_carousel = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800" data-aos-delay="150"';
$slide_count  = count( $rendered_slides );
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if ( $has_content ) : ?>

			<?php if ( $has_title ) : ?>
				<div class="section-header text-center mb-5"<?php echo $aos_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<h2 class="section-title"><?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
				</div>
			<?php endif; ?>

			<?php if ( $has_slides ) : ?>
				<?php if ( $is_preview ) : ?>
					<!-- Editor Preview Grid (Owl Carousel initializes on frontend page load) -->
					<div class="row g-3">
						<?php foreach ( $rendered_slides as $slide ) : ?>
							<div class="col-xl-3 col-lg-4 col-sm-6">
								<div class="moments-card">
									<img src="<?php echo esc_url( $slide['url'] ); ?>" alt="<?php echo esc_attr( $slide['alt'] ); ?>">
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<!-- Owl Carousel with Side Navigation Arrows -->
					<div class="moments-carousel-wrapper"<?php echo $aos_carousel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php if ( $slide_count > 1 ) : ?>
							<button type="button" class="moments-prev" aria-label="<?php esc_attr_e( 'Previous image', 'xmastree-blocks' ); ?>">
								<i class="bi bi-chevron-left" aria-hidden="true"></i>
							</button>
						<?php endif; ?>

						<div class="owl-carousel owl-theme moments-carousel">
							<?php foreach ( $rendered_slides as $slide ) : ?>
								<div class="item">
									<div class="moments-card">
										<img src="<?php echo esc_url( $slide['url'] ); ?>" alt="<?php echo esc_attr( $slide['alt'] ); ?>">
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<?php if ( $slide_count > 1 ) : ?>
							<button type="button" class="moments-next" aria-label="<?php esc_attr_e( 'Next image', 'xmastree-blocks' ); ?>">
								<i class="bi bi-chevron-right" aria-hidden="true"></i>
							</button>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>

		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Farm Moments Carousel Block — Click here to add section heading and carousel photos in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>

