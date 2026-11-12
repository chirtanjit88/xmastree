<?php
/**
 * Block Template: Moments From The Farm (Gallery Mosaic)
 *
 * Fully dynamic: powered by ACF Block Fields.
 * All static code and hardcoded fallbacks have been removed.
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
// 1. Block Attributes & Wrappers
// -----------------------------------------------------------------------------
$block_id = 'moments-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
	$block_id = (string) $block['anchor'];
}

$class_names = array( 'moments-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . $block['align'];
}

// -----------------------------------------------------------------------------
// 2. Retrieve ACF Fields
// -----------------------------------------------------------------------------
$title       = function_exists( 'get_field' ) ? (string) get_field( 'moments_title' ) : '';
$description = function_exists( 'get_field' ) ? (string) get_field( 'moments_description' ) : '';
$button      = function_exists( 'get_field' ) ? get_field( 'moments_button' ) : null;
$gallery_raw = function_exists( 'get_field' ) ? get_field( 'moments_gallery' ) : null;

// Process Action Button (ACF Link field: ['title' => ..., 'url' => ..., 'target' => ...]).
$btn_url    = '';
$btn_title  = '';
$btn_target = '';
if ( is_array( $button ) && ! empty( $button['url'] ) ) {
	$btn_url    = (string) $button['url'];
	$btn_title  = ! empty( $button['title'] ) ? (string) $button['title'] : '';
	$btn_target = ! empty( $button['target'] ) ? (string) $button['target'] : '';
} elseif ( is_string( $button ) && '' !== trim( $button ) ) {
	$btn_url = trim( $button );
}

// Helper to resolve an ACF gallery/image entry into ['url' => ..., 'alt' => ...].
$resolve_gallery_image = static function ( $img_entry, string $fallback_alt = '' ): ?array {
	$url = '';
	$alt = $fallback_alt;

	if ( is_array( $img_entry ) && ! empty( $img_entry['url'] ) ) {
		$url = (string) $img_entry['url'];
		if ( ! empty( $img_entry['alt'] ) ) {
			$alt = (string) $img_entry['alt'];
		} elseif ( ! empty( $img_entry['title'] ) && '' === $alt ) {
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
				if ( ! empty( $att_title ) && '' === $alt ) {
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

// Normalize up to 6 gallery items into positional mosaic slots (0..5).
$gallery_slots = array();
if ( is_array( $gallery_raw ) && ! empty( $gallery_raw ) ) {
	$fallback_label = '' !== trim( $title ) ? wp_strip_all_tags( $title ) : get_bloginfo( 'name' );
	foreach ( $gallery_raw as $entry ) {
		$resolved = $resolve_gallery_image( $entry, $fallback_label );
		if ( null !== $resolved ) {
			$gallery_slots[] = $resolved;
		}
		if ( count( $gallery_slots ) >= 6 ) {
			break;
		}
	}
}

$has_title     = '' !== trim( $title );
$has_desc      = '' !== trim( $description );
$has_btn       = '' !== $btn_url;
$has_left_col  = $has_title || $has_desc || $has_btn;
$has_right_col = ! empty( $gallery_slots );
$has_content   = $has_left_col || $has_right_col;

if ( ! $has_content && ! $is_preview ) {
	return;
}

// Slice positional mosaic rows matching html/index.html structure.
$left_slot        = $gallery_slots[0] ?? null;
$top_row_slots    = array_slice( $gallery_slots, 1, 3 );
$bottom_wide_slot = $gallery_slots[4] ?? null;
$bottom_thin_slot = $gallery_slots[5] ?? null;
$has_mosaic_right = ! empty( $top_row_slots ) || null !== $bottom_wide_slot || null !== $bottom_thin_slot;

// AOS Animation Attributes (active only on frontend).
$aos_left  = $is_preview ? '' : ' data-aos="fade-right" data-aos-duration="800"';
$aos_right = $is_preview ? '' : ' data-aos="fade-left" data-aos-duration="800"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>">
	<div class="moments-container">
		<?php if ( $has_content ) : ?>
			<div class="moments-inner">

				<?php if ( $has_left_col ) : ?>
					<!-- Left Content -->
					<div class="moments-content"<?php echo $aos_left; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php if ( $has_title ) : ?>
							<h2 class="moments-title"><?php echo wp_kses_post( $title ); ?></h2>
						<?php endif; ?>

						<?php if ( $has_desc ) : ?>
							<?php echo wp_kses_post( wpautop( $description ) ); ?>
						<?php endif; ?>

						<?php if ( $has_btn ) : ?>
							<a href="<?php echo esc_url( $btn_url ); ?>" class="btn-crimson"<?php echo ! empty( $btn_target ) ? ' target="' . esc_attr( $btn_target ) . '" rel="noopener noreferrer"' : ''; ?>><?php if ( '' !== $btn_title ) : ?><?php echo esc_html( $btn_title ); ?> <?php endif; ?><i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></a>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $has_right_col ) : ?>
					<!-- Right Gallery Mosaic (Up to 6 Images) -->
					<div class="gallery-mosaic-wrapper<?php echo ! $has_left_col ? ' mx-auto' : ''; ?>"<?php echo $aos_right; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php if ( null !== $left_slot ) : ?>
							<!-- Left Tall Column -->
							<div class="gallery-mosaic-col-left">
								<div class="gallery-item gallery-item-tall">
									<img src="<?php echo esc_url( $left_slot['url'] ); ?>" alt="<?php echo esc_attr( $left_slot['alt'] ); ?>" loading="lazy">
								</div>
							</div>
						<?php endif; ?>

						<?php if ( $has_mosaic_right ) : ?>
							<!-- Right Container (Top Row + Bottom Row) -->
							<div class="gallery-mosaic-col-right">
								<?php if ( ! empty( $top_row_slots ) ) : ?>
									<!-- Top Row: up to 3 items -->
									<div class="gallery-mosaic-row-top">
										<?php foreach ( $top_row_slots as $slot ) : ?>
											<div class="gallery-item">
												<img src="<?php echo esc_url( $slot['url'] ); ?>" alt="<?php echo esc_attr( $slot['alt'] ); ?>" loading="lazy">
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>

								<?php if ( null !== $bottom_wide_slot || null !== $bottom_thin_slot ) : ?>
									<!-- Bottom Row: up to 2 items (wide + narrow) -->
									<div class="gallery-mosaic-row-bottom">
										<?php if ( null !== $bottom_wide_slot ) : ?>
											<div class="gallery-item gallery-item-bottom-wide">
												<img src="<?php echo esc_url( $bottom_wide_slot['url'] ); ?>" alt="<?php echo esc_attr( $bottom_wide_slot['alt'] ); ?>" loading="lazy">
											</div>
										<?php endif; ?>

										<?php if ( null !== $bottom_thin_slot ) : ?>
											<div class="gallery-item gallery-item-bottom-narrow">
												<img src="<?php echo esc_url( $bottom_thin_slot['url'] ); ?>" alt="<?php echo esc_attr( $bottom_thin_slot['alt'] ); ?>" loading="lazy">
											</div>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

			</div>
		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-4 border border-dashed text-white">
				<p class="mb-0"><?php esc_html_e( 'Moments From The Farm Block — Click here to configure content and select up to 6 gallery images in the sidebar.', 'xmastree-blocks' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>

