<?php
/**
 * Block Template: Hero Banner
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
$block_id = 'hero-banner-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
	$block_id = (string) $block['anchor'];
}

$class_names = array( 'hero-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . $block['align'];
}

// -----------------------------------------------------------------------------
// 2. Retrieve ACF Fields
// -----------------------------------------------------------------------------
$badge_icon  = function_exists( 'get_field' ) ? get_field( 'hero_badge_icon' ) : '';
$badge_text  = function_exists( 'get_field' ) ? (string) get_field( 'hero_badge_text' ) : '';
$title         = function_exists( 'get_field' ) ? (string) get_field( 'hero_title' ) : '';
$description   = function_exists( 'get_field' ) ? (string) get_field( 'hero_description' ) : '';
$top_heading   = function_exists( 'get_field' ) ? trim( (string) get_field( 'hero_top_heading' ) ) : '';
if ( '' === $top_heading && function_exists( 'get_field' ) ) {
	$top_heading = trim( (string) get_field( 'hero_right_heading' ) );
}
$top_heading_align = function_exists( 'get_field' ) ? (string) get_field( 'hero_top_heading_align' ) : 'center';
if ( empty( $top_heading_align ) ) {
	$top_heading_align = 'center';
}
$button        = function_exists( 'get_field' ) ? get_field( 'hero_button' ) : null;

$bg_image      = function_exists( 'get_field' ) ? get_field( 'hero_background_image' ) : null;
$show_divider  = function_exists( 'get_field' ) ? get_field( 'hero_show_divider' ) : null;
$divider_image = function_exists( 'get_field' ) ? get_field( 'hero_divider_image' ) : null;

// Default divider visibility to true if not explicitly set (default_value: 1 in ACF).
if ( null === $show_divider ) {
	$show_divider = true;
}

// -----------------------------------------------------------------------------
// 3. Process Values
// -----------------------------------------------------------------------------
// Badge Icon formatting (e.g. bi-stars -> bi bi-stars).
$badge_icon_class = is_array( $badge_icon ) ? ( $badge_icon['value'] ?? '' ) : (string) $badge_icon;
if ( ! empty( $badge_icon_class ) ) {
	if ( str_starts_with( $badge_icon_class, 'bi-' ) && ! str_contains( $badge_icon_class, 'bi ' ) ) {
		$badge_icon_class = 'bi ' . $badge_icon_class;
	} elseif ( str_starts_with( $badge_icon_class, 'dashicons-' ) && ! str_contains( $badge_icon_class, 'dashicons ' ) ) {
		$badge_icon_class = 'dashicons ' . $badge_icon_class;
	}
}

// Button (ACF Link field: ['title' => ..., 'url' => ..., 'target' => ...])
$btn_url    = '';
$btn_title  = '';
$btn_target = '';
if ( is_array( $button ) && ! empty( $button['url'] ) ) {
	$btn_url    = (string) $button['url'];
	$btn_title  = ! empty( $button['title'] ) ? (string) $button['title'] : '';
	$btn_target = ! empty( $button['target'] ) ? (string) $button['target'] : '';
}

// Background Image Style
$bg_style = '';
if ( ! empty( $bg_image ) ) {
	$bg_url = '';
	if ( is_array( $bg_image ) && ! empty( $bg_image['url'] ) ) {
		$bg_url = (string) $bg_image['url'];
	} elseif ( is_numeric( $bg_image ) && (int) $bg_image > 0 ) {
		$img = wp_get_attachment_image_url( (int) $bg_image, 'full' );
		if ( $img ) {
			$bg_url = (string) $img;
		}
	} elseif ( is_string( $bg_image ) && ! empty( $bg_image ) ) {
		$bg_url = $bg_image;
	}
	if ( ! empty( $bg_url ) ) {
		$bg_style = ' style="background-image: url(' . esc_url( $bg_url ) . '); background-size: cover; background-position: center;"';
	}
}

// Divider Image URL
$divider_url = '';
if ( $show_divider ) {
	if ( ! empty( $divider_image ) ) {
		if ( is_array( $divider_image ) && ! empty( $divider_image['url'] ) ) {
			$divider_url = (string) $divider_image['url'];
		} elseif ( is_numeric( $divider_image ) && (int) $divider_image > 0 ) {
			$img = wp_get_attachment_image_url( (int) $divider_image, 'full' );
			if ( $img ) {
				$divider_url = (string) $img;
			}
		} elseif ( is_string( $divider_image ) && ! empty( $divider_image ) ) {
			$divider_url = $divider_image;
		}
	}
	if ( empty( $divider_url ) ) {
		$divider_url = get_template_directory_uri() . '/assets/images/hero-divider.png';
	}
}

// Optional Top Announcement Heading (Red + Frijole across the banner).
$top_heading_plain = trim( wp_strip_all_tags( str_ireplace( array( '<br>', '<br/>', '<br />' ), ' ', $top_heading ) ) );
$has_top_heading   = '' !== $top_heading_plain;
$formatted_top     = '';
if ( $has_top_heading ) {
	$has_explicit_br = 1 === preg_match( '/<br\b/i', $top_heading );
	$formatted_top   = $has_explicit_br ? wp_kses_post( $top_heading ) : nl2br( wp_kses_post( $top_heading ) );
}

// AOS animation attributes (active on frontend; omitted in block editor preview to prevent display issues).
$aos_top   = $is_preview ? '' : ' data-aos="fade-down" data-aos-duration="800"';
$aos_badge = $is_preview ? '' : ' data-aos="fade-down" data-aos-duration="600" data-aos-delay="100"';
$aos_title = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800" data-aos-delay="200"';
$aos_desc  = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800" data-aos-delay="300"';
$aos_btn   = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800" data-aos-delay="400"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $bg_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="hero-overlay"></div>
	<div class="container position-relative z-2">

		<?php if ( $has_top_heading ) : ?>
			<div class="hero-top-announcement text-<?php echo esc_attr( $top_heading_align ); ?>"<?php echo $aos_top; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<h2 class="hero-top-heading hero-right-heading"><?php echo $formatted_top; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
			</div>
		<?php endif; ?>

		<div class="row">
			<div class="col-lg-8 col-xl-7">
				<div class="hero-content">

					<?php if ( ! empty( $badge_text ) ) : ?>
						<div class="hero-badge"<?php echo $aos_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php if ( ! empty( $badge_icon_class ) ) : ?>
								<i class="<?php echo esc_attr( $badge_icon_class ); ?> text-gold me-2"></i>
							<?php endif; ?>
							<?php echo esc_html( $badge_text ); ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $title ) ) : ?>
						<h1 class="hero-title"<?php echo $aos_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php echo nl2br( esc_html( $title ) ); ?>
						</h1>
					<?php endif; ?>

					<?php if ( ! empty( $description ) ) : ?>
						<div class="hero-description-wrap"<?php echo $aos_desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php echo wp_kses_post( wpautop( $description ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $btn_url ) ) : ?>
						<div<?php echo $aos_btn; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<a href="<?php echo esc_url( $btn_url ); ?>" class="btn-crimson"<?php echo ! empty( $btn_target ) ? ' target="' . esc_attr( $btn_target ) . '" rel="noopener noreferrer"' : ''; ?>>
								<?php echo esc_html( $btn_title ); ?> <i class="bi bi-arrow-right ms-2"></i>
							</a>
						</div>
					<?php endif; ?>

				</div>
			</div>
		</div>

	</div>

	<?php if ( $show_divider && ! empty( $divider_url ) ) : ?>
		<div class="hero-divider-wrapper">
			<img src="<?php echo esc_url( $divider_url ); ?>" alt="<?php esc_attr_e( 'Decorative festive snow divider', 'xmastree-blocks' ); ?>" class="hero-divider-img">
		</div>
	<?php endif; ?>
</section>
