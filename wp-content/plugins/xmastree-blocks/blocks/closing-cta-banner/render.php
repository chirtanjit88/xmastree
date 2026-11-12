<?php
/**
 * Block Template: Closing CTA Banner
 *
 * Renders the "Find Your Perfect Tree This Christmas" full-width background banner
 * from real-christmas-trees.html: dynamic background image, configurable dark overlay,
 * centered two-tone heading, subtitle, and dual CTA buttons (crimson pill + outline white pill).
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
$block_id = 'closing-cta-banner-' . ( $block['id'] ?? uniqid() );
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

$resolve_image_url = static function ( $image_raw ): string {
	if ( is_array( $image_raw ) && ! empty( $image_raw['url'] ) ) {
		return (string) $image_raw['url'];
	}
	if ( is_numeric( $image_raw ) && (int) $image_raw > 0 ) {
		$src = wp_get_attachment_image_url( (int) $image_raw, 'full' );
		return $src ? (string) $src : '';
	}
	if ( is_string( $image_raw ) && '' !== trim( $image_raw ) ) {
		return trim( $image_raw );
	}
	return '';
};

$resolve_icon = static function ( $icon_raw ): array {
	$url   = '';
	$class = '';

	if ( empty( $icon_raw ) ) {
		return array(
			'url'   => '',
			'class' => '',
		);
	}

	$icon_type = '';
	if ( is_array( $icon_raw ) ) {
		if ( ! empty( $icon_raw['type'] ) ) {
			$icon_type = (string) $icon_raw['type'];
		}
		if ( ! empty( $icon_raw['url'] ) ) {
			return array(
				'url'   => (string) $icon_raw['url'],
				'class' => '',
			);
		}
		if ( isset( $icon_raw['value'] ) ) {
			$icon_raw = $icon_raw['value'];
		} else {
			return array(
				'url'   => '',
				'class' => '',
			);
		}
	}

	if ( is_numeric( $icon_raw ) && (int) $icon_raw > 0 ) {
		$src = wp_get_attachment_image_url( (int) $icon_raw, 'full' );
		if ( $src ) {
			$url = (string) $src;
		}
	} elseif ( is_string( $icon_raw ) && '' !== trim( $icon_raw ) ) {
		$icon_str = trim( $icon_raw );
		$is_image = 'media_library' === $icon_type
			|| 'url' === $icon_type
			|| str_starts_with( $icon_str, 'http://' )
			|| str_starts_with( $icon_str, 'https://' )
			|| str_starts_with( $icon_str, '/' )
			|| 1 === preg_match( '/\.(?:svg|png|webp|jpe?g|gif)(?:\?.*)?$/i', $icon_str );

		if ( $is_image ) {
			$url = $icon_str;
		} else {
			$class = $icon_str;
			if ( str_starts_with( $class, 'bi-' ) && ! str_contains( $class, 'bi ' ) ) {
				$class = 'bi ' . $class;
			} elseif ( str_starts_with( $class, 'dashicons-' ) && ! str_contains( $class, 'dashicons ' ) ) {
				$class = 'dashicons ' . $class;
			}
		}
	}

	return array(
		'url'   => $url,
		'class' => $class,
	);
};

$resolve_button = static function ( $link_raw, $icon_raw ) use ( $resolve_icon ): array {
	if ( ! is_array( $link_raw ) || empty( $link_raw['url'] ) || empty( $link_raw['title'] ) ) {
		return array( 'valid' => false );
	}

	$url    = trim( (string) $link_raw['url'] );
	$title  = trim( (string) $link_raw['title'] );
	$target = ! empty( $link_raw['target'] ) ? trim( (string) $link_raw['target'] ) : '';
	if ( '' === $url || '' === $title ) {
		return array( 'valid' => false );
	}

	$icon = $resolve_icon( $icon_raw );

	return array(
		'valid'      => true,
		'url'        => $url,
		'title'      => $title,
		'target'     => $target,
		'icon_url'   => $icon['url'],
		'icon_class' => $icon['class'],
	);
};

// -----------------------------------------------------------------------------
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_closing_cta_banner)
// -----------------------------------------------------------------------------
$bg_image_raw   = function_exists( 'get_field' ) ? get_field( 'cta_banner_bg_image' ) : null;
$heading        = function_exists( 'get_field' ) ? trim( (string) get_field( 'cta_banner_heading' ) ) : '';
$subtitle       = function_exists( 'get_field' ) ? trim( (string) get_field( 'cta_banner_subtitle' ) ) : '';
$btn1_raw       = function_exists( 'get_field' ) ? get_field( 'cta_banner_primary_button' ) : null;
$btn1_icon_raw  = function_exists( 'get_field' ) ? get_field( 'cta_banner_primary_icon' ) : null;
$btn2_raw       = function_exists( 'get_field' ) ? get_field( 'cta_banner_secondary_button' ) : null;
$btn2_icon_raw  = function_exists( 'get_field' ) ? get_field( 'cta_banner_secondary_icon' ) : null;
$overlay_preset = function_exists( 'get_field' ) ? $normalize_select( get_field( 'cta_banner_overlay_opacity' ) ) : '';
$height_preset  = function_exists( 'get_field' ) ? $normalize_select( get_field( 'cta_banner_min_height' ) ) : '';

// -----------------------------------------------------------------------------
// 3. Process Banner Content & Buttons
// -----------------------------------------------------------------------------
$bg_image_url = $resolve_image_url( $bg_image_raw );
$has_bg_image = '' !== $bg_image_url;

$heading_plain  = $extract_plain_text( $heading );
$has_heading    = '' !== $heading_plain;
$subtitle_plain = $extract_plain_text( $subtitle );
$has_subtitle   = '' !== $subtitle_plain;

$btn1 = $resolve_button( $btn1_raw, $btn1_icon_raw );
$btn2 = $resolve_button( $btn2_raw, $btn2_icon_raw );

$has_actions     = $btn1['valid'] || $btn2['valid'];
$has_any_content = $has_bg_image || $has_heading || $has_subtitle || $has_actions;

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

$class_names = array( 'realtree-cta-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . (string) $block['align'];
}

$section_styles = array();
if ( $has_bg_image ) {
	$section_styles[] = 'background-image: url(\'' . esc_url( $bg_image_url ) . '\')';
}
if ( in_array( $height_preset, array( '380', '440', '520' ), true ) && '440' !== $height_preset ) {
	$section_styles[] = 'min-height: ' . $height_preset . 'px';
}
$section_style_attr = ! empty( $section_styles ) ? ' style="' . esc_attr( implode( '; ', $section_styles ) . ';' ) . '"' : '';

$overlay_style_attr = '';
$show_overlay       = '0' !== $overlay_preset;
if ( in_array( $overlay_preset, array( '60', '82' ), true ) ) {
	$alpha              = (int) $overlay_preset / 100;
	$overlay_style_attr = ' style="' . esc_attr( 'background: linear-gradient(180deg, rgba(0, 28, 14, ' . $alpha . ') 0%, rgba(0, 0, 0, ' . $alpha . ') 100%);' ) . '"';
}

$aos_content = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $show_overlay ) : ?>
		<div class="realtree-cta-overlay"<?php echo $overlay_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></div>
	<?php endif; ?>

	<div class="container position-relative z-2">
		<?php if ( $has_heading || $has_subtitle || $has_actions ) : ?>
			<div class="realtree-cta-content"<?php echo $aos_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
				<?php if ( $has_heading ) : ?>
					<h2><?php echo $formatted_heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
				<?php endif; ?>

				<?php if ( $has_subtitle ) : ?>
					<p><?php echo nl2br( esc_html( $subtitle ) ); ?></p>
				<?php endif; ?>

				<?php if ( $has_actions ) : ?>
					<div class="realtree-cta-actions">
						<?php if ( $btn1['valid'] ) : ?>
							<a href="<?php echo esc_url( $btn1['url'] ); ?>" class="btn-crimson"<?php echo '' !== $btn1['target'] ? ' target="' . esc_attr( $btn1['target'] ) . '"' : ''; ?><?php echo '_blank' === $btn1['target'] ? ' rel="noopener noreferrer"' : ''; ?>>
								<?php echo esc_html( $btn1['title'] ); ?>
								<?php if ( '' !== $btn1['icon_url'] ) : ?>
									<img src="<?php echo esc_url( $btn1['icon_url'] ); ?>" alt="" class="realtree-cta-btn-icon" aria-hidden="true">
								<?php elseif ( '' !== $btn1['icon_class'] ) : ?>
									<i class="<?php echo esc_attr( $btn1['icon_class'] ); ?> ms-1" aria-hidden="true"></i>
								<?php endif; ?>
							</a>
						<?php endif; ?>

						<?php if ( $btn2['valid'] ) : ?>
							<a href="<?php echo esc_url( $btn2['url'] ); ?>" class="btn-outline-white"<?php echo '' !== $btn2['target'] ? ' target="' . esc_attr( $btn2['target'] ) . '"' : ''; ?><?php echo '_blank' === $btn2['target'] ? ' rel="noopener noreferrer"' : ''; ?>>
								<?php echo esc_html( $btn2['title'] ); ?>
								<?php if ( '' !== $btn2['icon_url'] ) : ?>
									<img src="<?php echo esc_url( $btn2['icon_url'] ); ?>" alt="" class="realtree-cta-btn-icon" aria-hidden="true">
								<?php elseif ( '' !== $btn2['icon_class'] ) : ?>
									<i class="<?php echo esc_attr( $btn2['icon_class'] ); ?> ms-1" aria-hidden="true"></i>
								<?php endif; ?>
							</a>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-white bg-dark bg-opacity-50 rounded">
				<span><?php esc_html_e( 'Closing CTA Banner Block — Click here to set background photo, heading, subtitle, and action buttons in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>

