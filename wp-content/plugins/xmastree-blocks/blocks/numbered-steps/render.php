<?php
/**
 * Block Template: Numbered Steps Section
 *
 * Renders the "Start Fresh. Keep It Beautiful" split section from tree-care.html
 * with a two-tone heading, intro copy, dark-green numbered steps list, and rounded side photo.
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
$block_id = 'numbered-steps-' . ( $block['id'] ?? uniqid() );
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
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_numbered_steps)
// -----------------------------------------------------------------------------
$title       = function_exists( 'get_field' ) ? trim( (string) get_field( 'steps_title' ) ) : '';
$description = function_exists( 'get_field' ) ? trim( (string) get_field( 'steps_description' ) ) : '';
$steps_raw   = function_exists( 'get_field' ) ? get_field( 'steps_items' ) : null;
$side_image  = function_exists( 'get_field' ) ? get_field( 'steps_image' ) : null;
$image_pos   = function_exists( 'get_field' ) ? $normalize_select( get_field( 'steps_image_position' ) ) : '';
$bg_color    = function_exists( 'get_field' ) ? $normalize_select( get_field( 'steps_background_color' ) ) : '';

// -----------------------------------------------------------------------------
// 3. Resolve Section Classes, Image Position & Optional Inline Background
// -----------------------------------------------------------------------------
$class_names = array( 'treecare-start-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . (string) $block['align'];
}

if ( 'left' !== $image_pos ) {
	$image_pos = 'right';
}

$section_styles     = array();
$sanitized_bg_color = '' !== $bg_color ? sanitize_hex_color( $bg_color ) : null;
if ( is_string( $sanitized_bg_color ) && '' !== $sanitized_bg_color ) {
	$section_styles[] = 'background-color: ' . strtoupper( $sanitized_bg_color );
}
$section_style_attr = ! empty( $section_styles ) ? ' style="' . esc_attr( implode( '; ', $section_styles ) . ';' ) . '"' : '';

// -----------------------------------------------------------------------------
// 4. Resolve Steps Repeater (number [text], title [text], description [wysiwyg])
// -----------------------------------------------------------------------------
$step_rows = array();
if ( function_exists( 'have_rows' ) && have_rows( 'steps_items' ) ) {
	while ( have_rows( 'steps_items' ) ) {
		the_row();
		$step_rows[] = array(
			'number'      => get_sub_field( 'number' ),
			'title'       => get_sub_field( 'title' ),
			'description' => get_sub_field( 'description' ),
		);
	}
} elseif ( is_array( $steps_raw ) && ! empty( $steps_raw ) ) {
	foreach ( $steps_raw as $row ) {
		if ( is_array( $row ) ) {
			$step_rows[] = array(
				'number'      => $row['number'] ?? '',
				'title'       => $row['title'] ?? '',
				'description' => $row['description'] ?? '',
			);
		}
	}
}

$rendered_steps = array();
$step_counter   = 0;
foreach ( $step_rows as $row ) {
	$s_num_raw   = trim( (string) ( $row['number'] ?? '' ) );
	$s_num_plain = $extract_plain_text( $s_num_raw );
	$has_s_num   = '' !== $s_num_plain;

	$s_title       = trim( (string) ( $row['title'] ?? '' ) );
	$s_title_plain = $extract_plain_text( $s_title );
	$has_s_title   = '' !== $s_title_plain;

	$s_desc_raw = trim( (string) ( $row['description'] ?? '' ) );
	$has_s_desc = $has_rich_content( $s_desc_raw );
	$s_desc     = $has_s_desc ? $s_desc_raw : '';

	if ( ! $has_s_num && ! $has_s_title && ! $has_s_desc ) {
		continue;
	}

	++$step_counter;
	$rendered_steps[] = array(
		'number'      => $has_s_num ? $s_num_plain : (string) $step_counter,
		'title'       => $has_s_title ? $s_title : '',
		'description' => $s_desc,
	);
}

// -----------------------------------------------------------------------------
// 5. Resolve Title, Description, Side Image & Column Visibility
// -----------------------------------------------------------------------------
$title_plain = $extract_plain_text( $title );
$has_title   = '' !== $title_plain;
$has_desc    = $has_rich_content( $description );
$has_steps   = ! empty( $rendered_steps );

$img_default_alt = $has_title ? $title_plain : '';
$resolved_image  = $resolve_image( $side_image, $img_default_alt );
$image_url       = $resolved_image['url'];
$image_alt       = $resolved_image['alt'];
$has_image       = '' !== $image_url;

$has_text_col = $has_title || $has_desc || $has_steps;
$has_content  = $has_text_col || $has_image;

if ( ! $has_content && ! $is_preview ) {
	return;
}

$formatted_title = '';
if ( $has_title ) {
	$has_explicit_br = 1 === preg_match( '/<br\b/i', $title );
	$formatted_title = $has_explicit_br ? wp_kses_post( $title ) : nl2br( wp_kses_post( $title ) );
}

// Responsive column classes when one side is omitted vs split layout.
$text_col_class = ( $has_text_col && ! $has_image ) ? 'col-lg-8 mx-auto' : ( 'left' === $image_pos ? 'col-lg-6 ps-lg-4' : 'col-lg-6 mb-5 mb-lg-0 pe-lg-5' );
$img_col_class  = ( ! $has_text_col && $has_image ) ? 'col-lg-8 mx-auto text-center' : ( 'left' === $image_pos ? 'col-lg-6 mb-5 mb-lg-0 pe-lg-5 text-center text-lg-start' : 'col-lg-6 text-center text-lg-end' );

// AOS animations active on frontend only so editor blocks remain immediately visible.
$aos_text = '';
$aos_img  = '';
if ( ! $is_preview ) {
	$aos_text = 'left' === $image_pos
		? ' data-aos="fade-left" data-aos-duration="800"'
		: ' data-aos="fade-right" data-aos-duration="800"';
	$aos_img  = 'left' === $image_pos
		? ' data-aos="fade-right" data-aos-duration="800"'
		: ' data-aos="fade-left" data-aos-duration="800"';
}

$render_text_col = static function () use ( $text_col_class, $aos_text, $has_title, $formatted_title, $has_desc, $description, $has_steps, $rendered_steps, $is_preview ): void {
	?>
	<div class="<?php echo esc_attr( $text_col_class ); ?>"<?php echo $aos_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php if ( $has_title ) : ?>
			<h2 class="treecare-start-title mb-3"><?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
		<?php endif; ?>

		<?php if ( $has_desc ) : ?>
			<?php echo wp_kses_post( wpautop( $description ) ); ?>
		<?php endif; ?>

		<?php if ( $has_steps ) : ?>
			<ul class="treecare-steps-list">
				<?php
				foreach ( $rendered_steps as $idx => $step ) :
					$delay    = 100 * ( $idx + 1 );
					$aos_step = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="600" data-aos-delay="' . esc_attr( (string) $delay ) . '"';
					?>
					<li class="treecare-step-item"<?php echo $aos_step; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<span><?php echo esc_html( $step['number'] ); ?></span>
						<div class="treecare-step-content">
							<?php if ( '' !== $step['title'] ) : ?>
								<h3 class="treecare-step-title"><?php echo esc_html( $step['title'] ); ?></h3>
							<?php endif; ?>
							<?php if ( '' !== $step['description'] ) : ?>
								<?php echo wp_kses_post( wpautop( $step['description'] ) ); ?>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
};

$render_img_col = static function () use ( $img_col_class, $aos_img, $image_url, $image_alt ): void {
	?>
	<div class="<?php echo esc_attr( $img_col_class ); ?>"<?php echo $aos_img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" class="treecare-hero-photo img-fluid">
	</div>
	<?php
};
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if ( $has_content ) : ?>
			<div class="row align-items-center">
				<?php
				if ( 'left' === $image_pos ) {
					if ( $has_image ) {
						$render_img_col();
					}
					if ( $has_text_col ) {
						$render_text_col();
					}
				} else {
					if ( $has_text_col ) {
						$render_text_col();
					}
					if ( $has_image ) {
						$render_img_col();
					}
				}
				?>
			</div>
		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Numbered Steps Block — Click here to add heading, numbered steps, and side photo in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>

