<?php
/**
 * Block Template: Process Steps Timeline
 *
 * Renders the "Your Perfect Christmas Tree in Four Simple Steps" timeline section
 * from real-christmas-trees.html: centered two-tone heading, horizontal 4-column
 * (configurable 2/3/4) step cards connected by a dashed crimson timeline with
 * circular step badges, icons, headings, and descriptions.
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
$block_id = 'process-timeline-' . ( $block['id'] ?? uniqid() );
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

$resolve_icon = static function ( $icon_raw, string $default_alt = '' ): array {
	$url   = '';
	$alt   = $default_alt;
	$class = '';

	if ( empty( $icon_raw ) ) {
		return array(
			'url'   => '',
			'alt'   => $alt,
			'class' => '',
		);
	}

	$icon_type = '';
	if ( is_array( $icon_raw ) ) {
		if ( ! empty( $icon_raw['type'] ) ) {
			$icon_type = (string) $icon_raw['type'];
		}
		if ( ! empty( $icon_raw['url'] ) ) {
			$url = (string) $icon_raw['url'];
			if ( ! empty( $icon_raw['alt'] ) ) {
				$alt = (string) $icon_raw['alt'];
			} elseif ( ! empty( $icon_raw['title'] ) && '' === $alt ) {
				$alt = (string) $icon_raw['title'];
			}
			return array(
				'url'   => $url,
				'alt'   => $alt,
				'class' => '',
			);
		}
		if ( isset( $icon_raw['value'] ) ) {
			$icon_raw = $icon_raw['value'];
		} else {
			return array(
				'url'   => '',
				'alt'   => $alt,
				'class' => '',
			);
		}
	}

	if ( is_numeric( $icon_raw ) && (int) $icon_raw > 0 ) {
		$id  = (int) $icon_raw;
		$src = wp_get_attachment_image_url( $id, 'full' );
		if ( $src ) {
			$url      = (string) $src;
			$alt_meta = get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt_meta ) ) {
				$alt = (string) $alt_meta;
			}
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
		'alt'   => $alt,
		'class' => $class,
	);
};

// -----------------------------------------------------------------------------
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_process_timeline)
// -----------------------------------------------------------------------------
$heading        = function_exists( 'get_field' ) ? trim( (string) get_field( 'timeline_heading' ) ) : '';
$subtitle       = function_exists( 'get_field' ) ? trim( (string) get_field( 'timeline_subtitle' ) ) : '';
$steps_raw      = function_exists( 'get_field' ) ? get_field( 'timeline_steps' ) : null;
$columns        = function_exists( 'get_field' ) ? $normalize_select( get_field( 'timeline_columns' ) ) : '';
$show_connector = function_exists( 'get_field' ) ? get_field( 'timeline_show_connector' ) : true;
$bg_color       = function_exists( 'get_field' ) ? $normalize_select( get_field( 'timeline_background_color' ) ) : '';

if ( ! in_array( $columns, array( '4', '3', '2' ), true ) ) {
	$columns = '4';
}
$has_connector = null === $show_connector ? true : (bool) $show_connector;

// -----------------------------------------------------------------------------
// 3. Process Steps Repeater
// -----------------------------------------------------------------------------
$raw_rows = array();
if ( function_exists( 'have_rows' ) && have_rows( 'timeline_steps' ) ) {
	while ( have_rows( 'timeline_steps' ) ) {
		the_row();
		$raw_rows[] = array(
			'step_number' => get_sub_field( 'step_number' ),
			'icon'        => get_sub_field( 'icon' ),
			'title'       => get_sub_field( 'title' ),
			'description' => get_sub_field( 'description' ),
		);
	}
} elseif ( is_array( $steps_raw ) && ! empty( $steps_raw ) ) {
	foreach ( $steps_raw as $row ) {
		if ( is_array( $row ) ) {
			$raw_rows[] = array(
				'step_number' => $row['step_number'] ?? '',
				'icon'        => $row['icon'] ?? null,
				'title'       => $row['title'] ?? '',
				'description' => $row['description'] ?? '',
			);
		}
	}
}

$steps = array();
foreach ( $raw_rows as $index => $row ) {
	$title       = trim( (string) ( $row['title'] ?? '' ) );
	$description = trim( (string) ( $row['description'] ?? '' ) );
	$step_num    = trim( (string) ( $row['step_number'] ?? '' ) );
	$icon_data   = $resolve_icon( $row['icon'] ?? null, $extract_plain_text( $title ) );

	$has_icon = '' !== $icon_data['url'] || '' !== $icon_data['class'];
	if ( '' === $step_num && ! $has_icon && '' === $title && '' === $description ) {
		continue;
	}

	if ( '' === $step_num ) {
		$step_num = (string) ( count( $steps ) + 1 );
	}

	$steps[] = array(
		'step_number' => $step_num,
		'icon_url'    => $icon_data['url'],
		'icon_alt'    => $icon_data['alt'],
		'icon_class'  => $icon_data['class'],
		'title'       => $title,
		'description' => $description,
	);
}

// -----------------------------------------------------------------------------
// 4. Resolve Section Visibility, Formatting & Classes
// -----------------------------------------------------------------------------
$heading_plain  = $extract_plain_text( $heading );
$has_heading    = '' !== $heading_plain;
$subtitle_plain = $extract_plain_text( $subtitle );
$has_subtitle   = '' !== $subtitle_plain;
$has_header     = $has_heading || $has_subtitle;
$has_steps      = ! empty( $steps );
$has_content    = $has_header || $has_steps;

if ( ! $has_content && ! $is_preview ) {
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

$class_names = array( 'realtree-steps-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . (string) $block['align'];
}

$timeline_classes = array(
	'realtree-timeline',
	'realtree-timeline--cols-' . $columns,
);
if ( ! $has_connector ) {
	$timeline_classes[] = 'realtree-timeline--no-line';
}

$section_styles     = array();
$sanitized_bg_color = '' !== $bg_color ? sanitize_hex_color( $bg_color ) : null;
if ( is_string( $sanitized_bg_color ) && '' !== $sanitized_bg_color ) {
	$section_styles[] = 'background-color: ' . strtoupper( $sanitized_bg_color );
}
$section_style_attr = ! empty( $section_styles ) ? ' style="' . esc_attr( implode( '; ', $section_styles ) . ';' ) . '"' : '';

$aos_header = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if ( $has_content ) : ?>

			<?php if ( $has_header ) : ?>
				<div class="section-header text-center"<?php echo $aos_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php if ( $has_heading ) : ?>
						<h2 class="section-title"><?php echo $formatted_heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
					<?php endif; ?>

					<?php if ( $has_subtitle ) : ?>
						<p><?php echo nl2br( esc_html( $subtitle ) ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $has_steps ) : ?>
				<div class="<?php echo esc_attr( implode( ' ', $timeline_classes ) ); ?>">
					<?php
					foreach ( $steps as $index => $step ) :
						$delay    = 100 * ( $index + 1 );
						$aos_step = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="600" data-aos-delay="' . esc_attr( (string) $delay ) . '"';
						?>
						<div class="realtree-step-card"<?php echo $aos_step; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php if ( '' !== $step['step_number'] ) : ?>
								<div class="realtree-step-number"><?php echo esc_html( $step['step_number'] ); ?></div>
							<?php endif; ?>

							<?php if ( '' !== $step['icon_url'] || '' !== $step['icon_class'] ) : ?>
								<div class="realtree-step-icon">
									<?php if ( '' !== $step['icon_url'] ) : ?>
										<img src="<?php echo esc_url( $step['icon_url'] ); ?>" alt="<?php echo esc_attr( $step['icon_alt'] ); ?>" class="realtree-step-icon-img">
									<?php else : ?>
										<i class="<?php echo esc_attr( $step['icon_class'] ); ?>" aria-hidden="true"></i>
									<?php endif; ?>
								</div>
							<?php endif; ?>

							<?php if ( '' !== $step['title'] ) : ?>
								<h3><?php echo esc_html( $step['title'] ); ?></h3>
							<?php endif; ?>

							<?php if ( '' !== $step['description'] ) : ?>
								<p><?php echo nl2br( esc_html( $step['description'] ) ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Process Steps Timeline Block — Click here to add section heading and process step cards in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>

