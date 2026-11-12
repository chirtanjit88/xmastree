<?php
/**
 * Block Template: Inner Banner
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
// 1. Block Attributes & Retrieve ACF Fields
// -----------------------------------------------------------------------------
$block_id = 'inner-banner-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
	$block_id = (string) $block['anchor'];
}

$title        = function_exists( 'get_field' ) ? (string) get_field( 'inner_banner_title' ) : '';
$description  = function_exists( 'get_field' ) ? (string) get_field( 'inner_banner_description' ) : '';
$bg_image     = function_exists( 'get_field' ) ? get_field( 'inner_banner_background_image' ) : null;
$banner_style = function_exists( 'get_field' ) ? (string) get_field( 'inner_banner_style' ) : '';

// -----------------------------------------------------------------------------
// 2. Resolve Banner Style Classes & Wrappers
// -----------------------------------------------------------------------------
$allowed_variants = array(
	'about-banner-section',
	'treecare-banner-section',
	'ourfarms-banner-section',
	'events-banner-section',
	'mascorner-banner-section',
	'blog-banner-section',
	'default',
);

$custom_class_str   = ! empty( $block['className'] ) ? (string) $block['className'] : '';
$has_custom_variant = false;
if ( '' !== $custom_class_str ) {
	foreach ( $allowed_variants as $variant ) {
		if ( 'default' !== $variant && str_contains( $custom_class_str, $variant ) ) {
			$has_custom_variant = true;
			break;
		}
	}
}

$banner_style = trim( $banner_style );
if ( '' === $banner_style || ! in_array( $banner_style, $allowed_variants, true ) ) {
	$banner_style = $has_custom_variant ? 'default' : 'about-banner-section';
}

$class_names = array( 'page-banner-section' );
if ( 'default' !== $banner_style && ! str_contains( $custom_class_str, $banner_style ) ) {
	$class_names[] = $banner_style;
}
if ( '' !== $custom_class_str ) {
	$class_names[] = $custom_class_str;
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . $block['align'];
}

// -----------------------------------------------------------------------------
// 3. Process Background Image (No static theme image fallbacks)
// -----------------------------------------------------------------------------
$bg_url = '';
if ( ! empty( $bg_image ) ) {
	if ( is_array( $bg_image ) && ! empty( $bg_image['url'] ) ) {
		$bg_url = (string) $bg_image['url'];
	} elseif ( is_numeric( $bg_image ) && (int) $bg_image > 0 ) {
		$img = wp_get_attachment_image_url( (int) $bg_image, 'full' );
		if ( $img ) {
			$bg_url = (string) $img;
		}
	} elseif ( is_string( $bg_image ) && '' !== trim( $bg_image ) ) {
		$bg_url = trim( $bg_image );
	}
}

// Explicitly suppress stylesheet fallback background images when no ACF image is set.
if ( '' !== $bg_url ) {
	$section_style = ' style="background-image: url(' . esc_url( $bg_url ) . '); background-position: center center; background-size: cover; background-repeat: no-repeat;"';
} else {
	$section_style = ' style="background-image: none; background-color: var(--primary-dark, #071e14);"';
}

// -----------------------------------------------------------------------------
// 4. Process Heading, Description & Visibility
// -----------------------------------------------------------------------------
$has_title  = '' !== trim( $title );
$desc_plain = trim( str_replace( array( "\xc2\xa0", '&nbsp;' ), '', wp_strip_all_tags( $description ) ) );
$has_desc   = '' !== $desc_plain;
$has_bg     = '' !== $bg_url;

$has_content = $has_title || $has_desc || $has_bg;

if ( ! $has_content && ! $is_preview ) {
	return;
}

$formatted_title = '';
if ( $has_title ) {
	$formatted_title = str_contains( $title, '<br' ) ? wp_kses_post( $title ) : nl2br( wp_kses_post( $title ) );
}

// AOS animations active only on frontend to keep Gutenberg preview visible.
$aos_title = $is_preview ? '' : ' data-aos="fade-down" data-aos-duration="800"';
$aos_desc  = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800" data-aos-delay="150"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="page-banner-overlay"></div>

	<?php if ( $has_title || $has_desc ) : ?>
		<div class="page-banner-content">
			<?php if ( $has_title ) : ?>
				<h1 class="page-banner-title"<?php echo $aos_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h1>
			<?php endif; ?>

			<?php if ( $has_desc ) : ?>
				<div class="page-banner-description"<?php echo $aos_desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php echo wp_kses_post( wpautop( $description ) ); ?>
				</div>
			<?php endif; ?>
		</div>
	<?php elseif ( $is_preview ) : ?>
		<div class="position-relative z-2 w-100 text-center p-4 border border-dashed text-white mx-auto" style="max-width: 720px;">
			<span>
				<?php
				if ( $has_bg ) {
					esc_html_e( 'Inner Banner Block — Add a banner title or description in the sidebar.', 'xmastree-blocks' );
				} else {
					esc_html_e( 'Inner Banner Block — Click here to configure background image, title, and description in the sidebar.', 'xmastree-blocks' );
				}
				?>
			</span>
		</div>
	<?php endif; ?>
</section>

