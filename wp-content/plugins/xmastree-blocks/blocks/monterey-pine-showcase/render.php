<?php
/**
 * Block Template: Monterey Pine Showcase
 *
 * Renders the "Meet the Monterey Pine" two-column section from real-christmas-trees.html:
 * left formatted story content + growing highlight stat card, right dual rounded
 * showcase photo cards with bottom dark-green caption pills.
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
$block_id = 'monterey-pine-showcase-' . ( $block['id'] ?? uniqid() );
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

$resolve_image = static function ( $image_raw, string $fallback_alt = '' ): array {
	$url = '';
	$alt = $fallback_alt;

	if ( is_array( $image_raw ) && ! empty( $image_raw['url'] ) ) {
		$url = (string) $image_raw['url'];
		if ( ! empty( $image_raw['alt'] ) ) {
			$alt = (string) $image_raw['alt'];
		} elseif ( ! empty( $image_raw['title'] ) && '' === $alt ) {
			$alt = (string) $image_raw['title'];
		}
	} elseif ( is_numeric( $image_raw ) && (int) $image_raw > 0 ) {
		$id  = (int) $image_raw;
		$src = wp_get_attachment_image_url( $id, 'large' );
		if ( ! $src ) {
			$src = wp_get_attachment_image_url( $id, 'full' );
		}
		if ( $src ) {
			$url      = (string) $src;
			$alt_meta = get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt_meta ) ) {
				$alt = (string) $alt_meta;
			}
		}
	} elseif ( is_string( $image_raw ) && '' !== trim( $image_raw ) ) {
		$url = trim( $image_raw );
	}

	return array(
		'url' => $url,
		'alt' => $alt,
	);
};

// -----------------------------------------------------------------------------
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_monterey_pine_showcase)
// -----------------------------------------------------------------------------
$heading          = function_exists( 'get_field' ) ? trim( (string) get_field( 'showcase_heading' ) ) : '';
$story_content    = function_exists( 'get_field' ) ? trim( (string) get_field( 'showcase_content' ) ) : '';
$stat_value       = function_exists( 'get_field' ) ? trim( (string) get_field( 'showcase_stat_value' ) ) : '';
$stat_label       = function_exists( 'get_field' ) ? trim( (string) get_field( 'showcase_stat_label' ) ) : '';
$stat_title       = function_exists( 'get_field' ) ? trim( (string) get_field( 'showcase_stat_title' ) ) : '';
$stat_description = function_exists( 'get_field' ) ? trim( (string) get_field( 'showcase_stat_description' ) ) : '';
$photos_raw       = function_exists( 'get_field' ) ? get_field( 'showcase_photos' ) : null;
$media_position   = function_exists( 'get_field' ) ? $normalize_select( get_field( 'showcase_media_position' ) ) : '';
$bg_color         = function_exists( 'get_field' ) ? $normalize_select( get_field( 'showcase_background_color' ) ) : '';

if ( ! in_array( $media_position, array( 'right', 'left' ), true ) ) {
	$media_position = 'right';
}

// -----------------------------------------------------------------------------
// 3. Process Repeater Photos
// -----------------------------------------------------------------------------
$raw_photo_rows = array();
if ( function_exists( 'have_rows' ) && have_rows( 'showcase_photos' ) ) {
	while ( have_rows( 'showcase_photos' ) ) {
		the_row();
		$raw_photo_rows[] = array(
			'image'   => get_sub_field( 'image' ),
			'caption' => get_sub_field( 'caption' ),
		);
	}
} elseif ( is_array( $photos_raw ) && ! empty( $photos_raw ) ) {
	foreach ( $photos_raw as $row ) {
		if ( is_array( $row ) ) {
			$raw_photo_rows[] = array(
				'image'   => $row['image'] ?? null,
				'caption' => $row['caption'] ?? '',
			);
		}
	}
}

$photos = array();
foreach ( $raw_photo_rows as $row ) {
	$caption_raw   = trim( (string) ( $row['caption'] ?? '' ) );
	$caption_plain = $extract_plain_text( $caption_raw );
	$img_data      = $resolve_image( $row['image'] ?? null, $caption_plain );

	if ( '' === $img_data['url'] ) {
		continue;
	}

	$photos[] = array(
		'url'     => $img_data['url'],
		'alt'     => $img_data['alt'],
		'caption' => $caption_plain,
	);
}

// -----------------------------------------------------------------------------
// 4. Resolve Section Visibility & Formatting
// -----------------------------------------------------------------------------
$heading_plain   = $extract_plain_text( $heading );
$has_heading     = '' !== $heading_plain;
$story_plain     = $extract_plain_text( $story_content );
$has_story       = '' !== $story_plain;
$has_stat_badge  = '' !== $stat_value || '' !== $stat_label;
$has_stat_text   = '' !== $stat_title || '' !== $stat_description;
$has_stat_card   = $has_stat_badge || $has_stat_text;
$has_left_col    = $has_heading || $has_story || $has_stat_card;
$has_photos      = ! empty( $photos );
$has_any_content = $has_left_col || $has_photos;

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

$formatted_story = '';
if ( $has_story ) {
	$formatted_story = wp_kses_post( wpautop( $story_content ) );
}

$class_names = array( 'realtree-monterey-section' );
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

$row_classes = 'row align-items-center g-4 g-lg-5';
if ( 'left' === $media_position ) {
	$row_classes .= ' flex-lg-row-reverse';
}

$left_col_class  = $has_photos ? 'col-lg-6' : 'col-12';
$right_col_class = $has_left_col ? 'col-lg-6' : 'col-12';
$pair_classes    = 'realtree-photo-pair';
if ( 1 === count( $photos ) ) {
	$pair_classes .= ' realtree-photo-pair--single';
}

$aos_left  = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="700"';
$aos_stat  = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="600" data-aos-delay="150"';
$aos_right = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="700" data-aos-delay="100"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if ( $has_any_content ) : ?>
			<div class="<?php echo esc_attr( $row_classes ); ?>">

				<?php if ( $has_left_col ) : ?>
					<div class="<?php echo esc_attr( $left_col_class ); ?>"<?php echo $aos_left; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<div class="realtree-monterey-content">
							<?php if ( $has_heading ) : ?>
								<h2><?php echo $formatted_heading; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
							<?php endif; ?>

							<?php if ( $has_story ) : ?>
								<div class="realtree-monterey-copy">
									<?php echo $formatted_story; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							<?php endif; ?>

							<?php if ( $has_stat_card ) : ?>
								<div class="realtree-stat-card"<?php echo $aos_stat; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
									<?php if ( $has_stat_badge ) : ?>
										<div class="realtree-stat-badge">
											<?php if ( '' !== $stat_value ) : ?>
												<strong><?php echo esc_html( $stat_value ); ?></strong>
											<?php endif; ?>
											<?php if ( '' !== $stat_label ) : ?>
												<span><?php echo esc_html( $stat_label ); ?></span>
											<?php endif; ?>
										</div>
									<?php endif; ?>

									<?php if ( $has_stat_text ) : ?>
										<div class="realtree-stat-text">
											<?php if ( '' !== $stat_title ) : ?>
												<h4><?php echo esc_html( $stat_title ); ?></h4>
											<?php endif; ?>
											<?php if ( '' !== $stat_description ) : ?>
												<p><?php echo nl2br( esc_html( $stat_description ) ); ?></p>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( $has_photos ) : ?>
					<div class="<?php echo esc_attr( $right_col_class ); ?>"<?php echo $aos_right; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<div class="<?php echo esc_attr( $pair_classes ); ?>">
							<?php foreach ( $photos as $photo ) : ?>
								<div class="realtree-photo-card">
									<img src="<?php echo esc_url( $photo['url'] ); ?>" alt="<?php echo esc_attr( $photo['alt'] ); ?>" loading="lazy">
									<?php if ( '' !== $photo['caption'] ) : ?>
										<div class="realtree-photo-caption"><?php echo esc_html( $photo['caption'] ); ?></div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

			</div>
		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Monterey Pine Showcase Block — Click here to add story copy, growing stat badge, and showcase photos in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>

