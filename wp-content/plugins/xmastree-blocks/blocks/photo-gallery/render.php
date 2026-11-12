<?php
/**
 * Block Template: Photo Gallery (Lightbox)
 *
 * Renders a responsive multi-column photo gallery grid where every photo opens
 * in an interactive full-screen lightbox popup on click.
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
$block_id = 'photo-gallery-' . ( $block['id'] ?? uniqid() );
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

$resolve_gallery_item = static function ( $entry, string $fallback_alt = '' ): ?array {
	$thumb_url = '';
	$full_url  = '';
	$alt       = $fallback_alt;
	$caption   = '';

	if ( is_array( $entry ) ) {
		$full_url  = ! empty( $entry['url'] ) ? (string) $entry['url'] : '';
		$thumb_url = ! empty( $entry['sizes']['large'] )
			? (string) $entry['sizes']['large']
			: ( ! empty( $entry['sizes']['medium_large'] ) ? (string) $entry['sizes']['medium_large'] : $full_url );

		if ( '' === $full_url && '' !== $thumb_url ) {
			$full_url = $thumb_url;
		}

		if ( ! empty( $entry['alt'] ) ) {
			$alt = (string) $entry['alt'];
		} elseif ( ! empty( $entry['title'] ) && '' === $alt ) {
			$alt = (string) $entry['title'];
		}

		if ( ! empty( $entry['caption'] ) ) {
			$caption = trim( wp_strip_all_tags( (string) $entry['caption'] ) );
		} elseif ( ! empty( $entry['title'] ) ) {
			$caption = trim( wp_strip_all_tags( (string) $entry['title'] ) );
		}
	} elseif ( is_numeric( $entry ) && (int) $entry > 0 ) {
		$id         = (int) $entry;
		$full_src   = wp_get_attachment_image_url( $id, 'full' );
		$large_src  = wp_get_attachment_image_url( $id, 'large' );
		$medium_src = wp_get_attachment_image_url( $id, 'medium_large' );

		$full_url  = $full_src ? (string) $full_src : '';
		$thumb_url = $large_src ? (string) $large_src : ( $medium_src ? (string) $medium_src : $full_url );
		if ( '' === $full_url ) {
			$full_url = $thumb_url;
		}

		$alt_meta = get_post_meta( $id, '_wp_attachment_image_alt', true );
		if ( ! empty( $alt_meta ) ) {
			$alt = (string) $alt_meta;
		}

		$att_caption = wp_get_attachment_caption( $id );
		$att_title   = get_the_title( $id );
		if ( ! empty( $att_caption ) ) {
			$caption = trim( wp_strip_all_tags( (string) $att_caption ) );
		} elseif ( ! empty( $att_title ) ) {
			$caption = trim( wp_strip_all_tags( (string) $att_title ) );
		}

		if ( '' === $alt && '' !== $caption ) {
			$alt = $caption;
		}
	} elseif ( is_string( $entry ) && '' !== trim( $entry ) ) {
		$full_url  = trim( $entry );
		$thumb_url = $full_url;
	}

	if ( '' === $thumb_url || '' === $full_url ) {
		return null;
	}

	if ( '' === $alt ) {
		$alt = get_bloginfo( 'name' );
	}

	return array(
		'thumb'   => $thumb_url,
		'full'    => $full_url,
		'alt'     => $alt,
		'caption' => $caption,
	);
};

// -----------------------------------------------------------------------------
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_photo_gallery)
// -----------------------------------------------------------------------------
$title         = function_exists( 'get_field' ) ? trim( (string) get_field( 'photo_gallery_title' ) ) : '';
$subtitle      = function_exists( 'get_field' ) ? trim( (string) get_field( 'photo_gallery_subtitle' ) ) : '';
$images_raw    = function_exists( 'get_field' ) ? get_field( 'photo_gallery_images' ) : null;
$columns       = function_exists( 'get_field' ) ? $normalize_select( get_field( 'photo_gallery_columns' ) ) : '';
$aspect_ratio  = function_exists( 'get_field' ) ? $normalize_select( get_field( 'photo_gallery_aspect_ratio' ) ) : '';
$show_captions = function_exists( 'get_field' ) ? (bool) get_field( 'photo_gallery_show_captions' ) : false;
$bg_color      = function_exists( 'get_field' ) ? $normalize_select( get_field( 'photo_gallery_background_color' ) ) : '';

// -----------------------------------------------------------------------------
// 3. Resolve Title, Subtitle & Images
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

$fallback_alt   = $has_title ? $title_plain : get_bloginfo( 'name' );
$gallery_items  = array();
if ( is_array( $images_raw ) && ! empty( $images_raw ) ) {
	foreach ( $images_raw as $entry ) {
		$resolved = $resolve_gallery_item( $entry, $fallback_alt );
		if ( null !== $resolved ) {
			$gallery_items[] = $resolved;
		}
	}
}

$has_images  = ! empty( $gallery_items );
$has_content = $has_title || $has_subtitle || $has_images;

if ( ! $has_content && ! $is_preview ) {
	return;
}

// -----------------------------------------------------------------------------
// 4. Resolve Section Classes, Grid Columns, Aspect Ratio & Background
// -----------------------------------------------------------------------------
$col_class_map = array(
	'4' => array(
		'class' => 'col-lg-3 col-sm-6',
		'count' => 4,
	),
	'3' => array(
		'class' => 'col-lg-4 col-sm-6',
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

if ( ! in_array( $aspect_ratio, array( '4x3', '1x1', '3x4', '16x9' ), true ) ) {
	$aspect_ratio = '4x3';
}

$class_names = array(
	'photo-gallery-section',
	'photo-gallery-ratio--' . $aspect_ratio,
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
	$section_styles[] = 'background-color: ' . strtoupper( $sanitized_bg_color );
}
$section_style_attr = ! empty( $section_styles ) ? ' style="' . esc_attr( implode( '; ', $section_styles ) . ';' ) . '"' : '';

$aos_header = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="700"';
?>

<section
	id="<?php echo esc_attr( $block_id ); ?>"
	class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"
	<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
>
	<div class="container">
		<?php if ( $has_content ) : ?>

			<?php if ( $has_title || $has_subtitle ) : ?>
				<div class="section-header text-center mb-5"<?php echo $aos_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php if ( $has_title ) : ?>
						<h2 class="section-title"><?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
					<?php endif; ?>

					<?php if ( $has_subtitle ) : ?>
						<p class="photo-gallery-subtitle"><?php echo $formatted_subtitle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $has_images ) : ?>
				<div class="row g-4 photo-gallery-grid" data-gallery-lightbox-root="<?php echo esc_attr( $block_id ); ?>">
					<?php
					foreach ( $gallery_items as $index => $item ) :
						$delay    = 75 * ( ( $index % $col_count ) + 1 );
						$aos_item = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="600" data-aos-delay="' . esc_attr( (string) $delay ) . '"';
						$aria_lbl = '' !== $item['caption']
							? sprintf( __( 'Open photo in lightbox: %s', 'xmastree-blocks' ), $item['caption'] )
							: sprintf( __( 'Open photo %d in lightbox', 'xmastree-blocks' ), $index + 1 );
						?>
						<div class="<?php echo esc_attr( $col_class ); ?>"<?php echo $aos_item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<figure class="photo-gallery-figure">
								<?php if ( $is_preview ) : ?>
									<div class="photo-gallery-card">
										<div class="photo-gallery-thumb">
											<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="<?php echo esc_attr( $item['alt'] ); ?>" loading="lazy">
											<span class="photo-gallery-zoom-badge" aria-hidden="true">
												<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M6.5 12a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11M13 6.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0"/><path d="M10.344 11.742q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1 6.5 6.5 0 0 1-1.398 1.4z"/><path fill-rule="evenodd" d="M6.5 3a.5.5 0 0 1 .5.5V6h2.5a.5.5 0 0 1 0 1H7v2.5a.5.5 0 0 1-1 0V7H3.5a.5.5 0 0 1 0-1H6V3.5a.5.5 0 0 1 .5-.5"/></svg>
											</span>
										</div>
										<?php if ( $show_captions && '' !== $item['caption'] ) : ?>
											<figcaption class="photo-gallery-caption"><?php echo esc_html( $item['caption'] ); ?></figcaption>
										<?php endif; ?>
									</div>
								<?php else : ?>
									<a
										href="<?php echo esc_url( $item['full'] ); ?>"
										class="photo-gallery-card"
										data-gallery-lightbox-trigger="1"
										data-gallery-index="<?php echo esc_attr( (string) $index ); ?>"
										data-lightbox-src="<?php echo esc_url( $item['full'] ); ?>"
										data-lightbox-thumb="<?php echo esc_url( $item['thumb'] ); ?>"
										data-lightbox-alt="<?php echo esc_attr( $item['alt'] ); ?>"
										data-lightbox-caption="<?php echo esc_attr( $item['caption'] ); ?>"
										aria-label="<?php echo esc_attr( $aria_lbl ); ?>"
									>
										<div class="photo-gallery-thumb">
											<img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="<?php echo esc_attr( $item['alt'] ); ?>" loading="lazy">
											<span class="photo-gallery-zoom-badge" aria-hidden="true">
												<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 16 16" fill="currentColor"><path fill-rule="evenodd" d="M6.5 12a5.5 5.5 0 1 0 0-11 5.5 5.5 0 0 0 0 11M13 6.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0"/><path d="M10.344 11.742q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1 6.5 6.5 0 0 1-1.398 1.4z"/><path fill-rule="evenodd" d="M6.5 3a.5.5 0 0 1 .5.5V6h2.5a.5.5 0 0 1 0 1H7v2.5a.5.5 0 0 1-1 0V7H3.5a.5.5 0 0 1 0-1H6V3.5a.5.5 0 0 1 .5-.5"/></svg>
											</span>
										</div>
										<?php if ( $show_captions && '' !== $item['caption'] ) : ?>
											<figcaption class="photo-gallery-caption"><?php echo esc_html( $item['caption'] ); ?></figcaption>
										<?php endif; ?>
									</a>
								<?php endif; ?>
							</figure>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Photo Gallery (Lightbox) Block — Click here to add a heading and upload/select gallery images in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>
