<?php
/**
 * Block Template: Monterey Pine Banner
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
$block_id = 'monterey-banner-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
	$block_id = (string) $block['anchor'];
}

$class_names = array( 'monterey-pine-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . $block['align'];
}

// -----------------------------------------------------------------------------
// 2. Retrieve ACF Fields
// -----------------------------------------------------------------------------
$eyebrow     = function_exists( 'get_field' ) ? (string) get_field( 'monterey_eyebrow' ) : '';
$title       = function_exists( 'get_field' ) ? (string) get_field( 'monterey_title' ) : '';
$description = function_exists( 'get_field' ) ? (string) get_field( 'monterey_description' ) : '';
$button      = function_exists( 'get_field' ) ? get_field( 'monterey_button' ) : null;
$price_text  = function_exists( 'get_field' ) ? (string) get_field( 'monterey_price_text' ) : '';

$bg_video     = function_exists( 'get_field' ) ? get_field( 'monterey_background_video' ) : null;
$poster_image = function_exists( 'get_field' ) ? get_field( 'monterey_poster_image' ) : null;

// -----------------------------------------------------------------------------
// 3. Process Values
// -----------------------------------------------------------------------------
// Format title preserving intentional <br> line breaks.
$formatted_title = '';
if ( ! empty( $title ) ) {
	$formatted_title = str_contains( $title, '<br' ) ? wp_kses_post( $title ) : nl2br( wp_kses_post( $title ) );
}

// Process CTA button link field.
$btn_url    = '';
$btn_title  = '';
$btn_target = '';
if ( is_array( $button ) && ! empty( $button['url'] ) ) {
	$btn_url    = (string) $button['url'];
	$btn_title  = ! empty( $button['title'] ) ? (string) $button['title'] : '';
	$btn_target = ! empty( $button['target'] ) ? (string) $button['target'] : '';
}

// Process Background Video.
$video_url  = '';
$video_mime = 'video/mp4';
if ( ! empty( $bg_video ) ) {
	if ( is_array( $bg_video ) && ! empty( $bg_video['url'] ) ) {
		$video_url = (string) $bg_video['url'];
		if ( ! empty( $bg_video['mime_type'] ) ) {
			$video_mime = (string) $bg_video['mime_type'];
		}
	} elseif ( is_numeric( $bg_video ) && (int) $bg_video > 0 ) {
		$vid = wp_get_attachment_url( (int) $bg_video );
		if ( $vid ) {
			$video_url = (string) $vid;
			$mime      = get_post_mime_type( (int) $bg_video );
			if ( $mime ) {
				$video_mime = (string) $mime;
			}
		}
	} elseif ( is_string( $bg_video ) && ! empty( $bg_video ) ) {
		$video_url = $bg_video;
	}
}

// Process Poster / Fallback Image.
$poster_url = '';
if ( ! empty( $poster_image ) ) {
	if ( is_array( $poster_image ) && ! empty( $poster_image['url'] ) ) {
		$poster_url = (string) $poster_image['url'];
	} elseif ( is_numeric( $poster_image ) && (int) $poster_image > 0 ) {
		$img = wp_get_attachment_image_url( (int) $poster_image, 'full' );
		if ( $img ) {
			$poster_url = (string) $img;
		}
	} elseif ( is_string( $poster_image ) && ! empty( $poster_image ) ) {
		$poster_url = $poster_image;
	}
}

// Apply inline background image if no video is provided, or let poster handle video cover.
$section_style = '';
if ( empty( $video_url ) && ! empty( $poster_url ) ) {
	$section_style = ' style="background-image: url(' . esc_url( $poster_url ) . '); background-size: cover; background-position: center;"';
}

// Visibility flags.
$has_left_col  = ! empty( $eyebrow ) || ! empty( $formatted_title ) || ! empty( $description ) || ! empty( $btn_url );
$has_right_col = ! empty( $price_text );
$has_content   = $has_left_col || $has_right_col || ! empty( $video_url ) || ! empty( $poster_url );

// AOS animation attributes (only active on frontend).
$aos_left  = $is_preview ? '' : ' data-aos="fade-right" data-aos-duration="800"';
$aos_right = $is_preview ? '' : ' data-aos="fade-left" data-aos-duration="800" data-aos-delay="150"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( ! empty( $video_url ) ) : ?>
		<video class="monterey-video-bg" autoplay loop muted playsinline<?php echo ! empty( $poster_url ) ? ' poster="' . esc_url( $poster_url ) . '"' : ''; ?>>
			<source src="<?php echo esc_url( $video_url ); ?>" type="<?php echo esc_attr( $video_mime ); ?>">
		</video>
	<?php endif; ?>

	<div class="monterey-pine-overlay"></div>

	<div class="container position-relative z-2">
		<?php if ( $has_left_col || $has_right_col ) : ?>
			<div class="row align-items-center">

				<?php if ( $has_left_col ) : ?>
					<div class="col-lg-7"<?php echo $aos_left; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<div class="monterey-content">

							<?php if ( ! empty( $eyebrow ) ) : ?>
								<span><?php echo esc_html( $eyebrow ); ?></span>
							<?php endif; ?>

							<?php if ( ! empty( $formatted_title ) ) : ?>
								<h2 class="monterey-title">
									<?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</h2>
							<?php endif; ?>

							<?php if ( ! empty( $description ) ) : ?>
								<?php echo wp_kses_post( wpautop( $description ) ); ?>
							<?php endif; ?>

							<?php if ( ! empty( $btn_url ) ) : ?>
								<a href="<?php echo esc_url( $btn_url ); ?>" class="btn-outline-white"<?php echo ! empty( $btn_target ) ? ' target="' . esc_attr( $btn_target ) . '" rel="noopener noreferrer"' : ''; ?>>
									<?php echo esc_html( $btn_title ); ?> <i class="bi bi-arrow-right ms-2"></i>
								</a>
							<?php endif; ?>

						</div>
					</div>
				<?php endif; ?>

				<?php if ( $has_right_col ) : ?>
					<div class="col-lg-5 text-lg-end mt-4 mt-lg-0"<?php echo $aos_right; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<div class="monterey-price-frijole">
							<?php echo esc_html( $price_text ); ?>
						</div>
					</div>
				<?php endif; ?>

			</div>
		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-white">
				<p class="mb-0"><?php esc_html_e( 'Monterey Pine Banner Block — Click here to configure fields in the sidebar.', 'xmastree-blocks' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>

