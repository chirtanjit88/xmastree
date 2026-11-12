<?php
/**
 * Block Template: Content Section
 *
 * Reusable multi-layout section block for inner pages (Split Content + Image Right,
 * Split Image Left + Content/Badges, and Panoramic Background Banner).
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
$block_id = 'content-section-' . ( $block['id'] ?? uniqid() );
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
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_content_section)
// -----------------------------------------------------------------------------
$layout       = function_exists( 'get_field' ) ? $normalize_select( get_field( 'section_layout' ) ) : '';
$bg_color     = function_exists( 'get_field' ) ? $normalize_select( get_field( 'section_background_color' ) ) : '';
$title        = function_exists( 'get_field' ) ? trim( (string) get_field( 'section_title' ) ) : '';
$description  = function_exists( 'get_field' ) ? trim( (string) get_field( 'section_description' ) ) : '';
$badges_raw   = function_exists( 'get_field' ) ? get_field( 'section_badges' ) : null;
$button       = function_exists( 'get_field' ) ? get_field( 'section_button' ) : null;
$button_style = function_exists( 'get_field' ) ? $normalize_select( get_field( 'section_button_style' ) ) : '';
$button_icon  = function_exists( 'get_field' ) ? get_field( 'section_button_icon' ) : null;
$icon_pos     = function_exists( 'get_field' ) ? $normalize_select( get_field( 'section_button_icon_position' ) ) : '';
$side_image   = function_exists( 'get_field' ) ? get_field( 'section_image' ) : null;
$bg_image     = function_exists( 'get_field' ) ? get_field( 'section_background_image' ) : null;

// -----------------------------------------------------------------------------
// 3. Normalize Layout & Wrapper Classes
// -----------------------------------------------------------------------------
$allowed_layouts = array( 'split_right', 'split_left', 'banner_left' );
if ( '' === $layout || ! in_array( $layout, $allowed_layouts, true ) ) {
	$layout = 'split_right';
}

$layout_section_classes = array(
	'split_right' => 'about-tradition-section',
	'split_left'  => 'about-tree-section',
	'banner_left' => 'about-greener-banner',
);

$class_names = array( $layout_section_classes[ $layout ] );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . (string) $block['align'];
}

// -----------------------------------------------------------------------------
// 4. Resolve Feature Badges Repeater (icon [image], title [text], description [wysiwyg])
// -----------------------------------------------------------------------------
$badge_rows = array();
if ( function_exists( 'have_rows' ) && have_rows( 'section_badges' ) ) {
	while ( have_rows( 'section_badges' ) ) {
		the_row();
		$badge_rows[] = array(
			'icon'        => get_sub_field( 'icon' ),
			'title'       => get_sub_field( 'title' ),
			'description' => get_sub_field( 'description' ),
		);
	}
} elseif ( is_array( $badges_raw ) && ! empty( $badges_raw ) ) {
	foreach ( $badges_raw as $row ) {
		if ( is_array( $row ) ) {
			$badge_rows[] = array(
				'icon'        => $row['icon'] ?? null,
				'title'       => $row['title'] ?? '',
				'description' => $row['description'] ?? '',
			);
		}
	}
}

$rendered_badges = array();
foreach ( $badge_rows as $row ) {
	$b_title       = trim( (string) ( $row['title'] ?? '' ) );
	$b_title_plain = $extract_plain_text( $b_title );
	$has_b_title   = '' !== $b_title_plain;

	$b_desc_raw = trim( (string) ( $row['description'] ?? '' ) );
	$has_b_desc = $has_rich_content( $b_desc_raw );
	$b_desc     = $has_b_desc ? $b_desc_raw : '';

	$b_icon       = $row['icon'] ?? null;
	$b_icon_url   = '';
	$b_icon_class = '';
	$b_icon_alt   = $b_title_plain;

	if ( is_array( $b_icon ) || is_numeric( $b_icon ) ) {
		$resolved_icon = $resolve_image( $b_icon, $b_title_plain );
		$b_icon_url    = $resolved_icon['url'];
		$b_icon_alt    = $resolved_icon['alt'];
	} elseif ( is_string( $b_icon ) && '' !== trim( $b_icon ) ) {
		$icon_str = trim( $b_icon );
		if ( str_starts_with( $icon_str, 'http://' ) || str_starts_with( $icon_str, 'https://' ) || str_starts_with( $icon_str, '/' ) ) {
			$b_icon_url = $icon_str;
		} else {
			$b_icon_class = $icon_str;
			if ( str_starts_with( $b_icon_class, 'bi-' ) && ! str_contains( $b_icon_class, 'bi ' ) ) {
				$b_icon_class = 'bi ' . $b_icon_class;
			} elseif ( str_starts_with( $b_icon_class, 'dashicons-' ) && ! str_contains( $b_icon_class, 'dashicons ' ) ) {
				$b_icon_class = 'dashicons ' . $b_icon_class;
			}
		}
	}

	if ( '' === $b_icon_url && '' === $b_icon_class && ! $has_b_title && ! $has_b_desc ) {
		continue;
	}

	$rendered_badges[] = array(
		'icon_url'    => $b_icon_url,
		'icon_class'  => $b_icon_class,
		'icon_alt'    => $b_icon_alt,
		'title'       => $has_b_title ? $b_title : '',
		'description' => $b_desc,
	);
}

// -----------------------------------------------------------------------------
// 5. Resolve Action Button & Icon Picker Field
// -----------------------------------------------------------------------------
$btn_url    = '';
$btn_title  = '';
$btn_target = '';
if ( is_array( $button ) && ! empty( $button['url'] ) ) {
	$btn_url    = trim( (string) $button['url'] );
	$btn_title  = ! empty( $button['title'] ) ? trim( (string) $button['title'] ) : '';
	$btn_target = ! empty( $button['target'] ) ? trim( (string) $button['target'] ) : '';
}

$allowed_btn_styles = array( 'about-btn-crimson', 'about-btn-forest', 'btn-crimson', 'btn-outline-dark' );
if ( '' === $button_style || ! in_array( $button_style, $allowed_btn_styles, true ) ) {
	$button_style = 'banner_left' === $layout ? 'about-btn-forest' : 'about-btn-crimson';
}

$icon_pos = 'before' === $icon_pos ? 'before' : 'after';

$btn_icon_class = '';
$btn_icon_url   = '';
if ( is_array( $button_icon ) ) {
	$picker_type  = isset( $button_icon['type'] ) ? trim( (string) $button_icon['type'] ) : '';
	$picker_value = $button_icon['value'] ?? null;

	if ( 'media_library' === $picker_type || 'url' === $picker_type ) {
		$resolved_btn_img = $resolve_image( $picker_value );
		$btn_icon_url     = $resolved_btn_img['url'];
	} elseif ( is_string( $picker_value ) && '' !== trim( $picker_value ) ) {
		$raw_picker = trim( $picker_value );
		if ( str_starts_with( $raw_picker, 'http://' ) || str_starts_with( $raw_picker, 'https://' ) || str_starts_with( $raw_picker, '/' ) ) {
			$btn_icon_url = $raw_picker;
		} else {
			$btn_icon_class = $raw_picker;
		}
	} elseif ( ! empty( $button_icon['url'] ) ) {
		$btn_icon_url = trim( (string) $button_icon['url'] );
	}
} elseif ( is_numeric( $button_icon ) && (int) $button_icon > 0 ) {
	$resolved_btn_img = $resolve_image( $button_icon );
	$btn_icon_url     = $resolved_btn_img['url'];
} elseif ( is_string( $button_icon ) && '' !== trim( $button_icon ) ) {
	$raw_icon = trim( $button_icon );
	if ( str_starts_with( $raw_icon, 'http://' ) || str_starts_with( $raw_icon, 'https://' ) || str_starts_with( $raw_icon, '/' ) ) {
		$btn_icon_url = $raw_icon;
	} else {
		$btn_icon_class = $raw_icon;
	}
}

if ( '' !== $btn_icon_class ) {
	if ( str_starts_with( $btn_icon_class, 'bi-' ) && ! str_contains( $btn_icon_class, 'bi ' ) ) {
		$btn_icon_class = 'bi ' . $btn_icon_class;
	} elseif ( str_starts_with( $btn_icon_class, 'dashicons-' ) && ! str_contains( $btn_icon_class, 'dashicons ' ) ) {
		$btn_icon_class = 'dashicons ' . $btn_icon_class;
	}
}

// Theme pill classes .btn-crimson / .btn-outline-dark already define CSS gap: 10px.
$needs_icon_margin = '' !== $btn_title && ! in_array( $button_style, array( 'btn-crimson', 'btn-outline-dark' ), true );
$btn_spacing_cls   = $needs_icon_margin ? ( 'before' === $icon_pos ? 'me-2' : 'ms-2' ) : '';
$btn_icon_html     = '';

if ( '' !== $btn_icon_url ) {
	$img_class_attr = '' !== $btn_spacing_cls ? ' class="' . esc_attr( $btn_spacing_cls ) . '"' : '';
	$btn_icon_html  = '<img src="' . esc_url( $btn_icon_url ) . '" alt=""' . $img_class_attr . ' style="width: 1em; height: 1em; object-fit: contain;" aria-hidden="true">';
} elseif ( '' !== $btn_icon_class ) {
	$icon_classes  = trim( $btn_icon_class . ' ' . $btn_spacing_cls );
	$btn_icon_html = '<i class="' . esc_attr( $icon_classes ) . '" aria-hidden="true"></i>';
}

// -----------------------------------------------------------------------------
// 6. Resolve Media, Heading, Content & Inline Styles
// -----------------------------------------------------------------------------
$title_plain        = $extract_plain_text( $title );
$has_title          = '' !== $title_plain;
$title_alt_fallback = $has_title ? $title_plain : get_bloginfo( 'name' );

$side_img_data = 'banner_left' !== $layout
	? $resolve_image( $side_image, $title_alt_fallback )
	: array(
		'url' => '',
		'alt' => '',
	);
$side_img_url = $side_img_data['url'];
$side_img_alt = '' !== $side_img_data['alt'] ? $side_img_data['alt'] : $title_alt_fallback;

$bg_img_data = $resolve_image( $bg_image );
$bg_img_url  = $bg_img_data['url'];

$has_desc   = $has_rich_content( $description );
$has_badges = ! empty( $rendered_badges );
$has_btn    = '' !== $btn_url && ( '' !== $btn_title || '' !== $btn_icon_html );
$has_text   = $has_title || $has_desc || $has_badges || $has_btn;
$has_side   = '' !== $side_img_url;
$has_bg_img = '' !== $bg_img_url;

$has_content = $has_text || $has_side || $has_bg_img;

if ( ! $has_content && ! $is_preview ) {
	return;
}

// Formatting for Heading (text field supporting <br> and <span>...</span> highlight markup).
$formatted_title = '';
if ( $has_title ) {
	$has_explicit_br = 1 === preg_match( '/<br\b/i', $title );
	$formatted_title = $has_explicit_br ? wp_kses_post( $title ) : nl2br( wp_kses_post( $title ) );
}

// Background & height inline styles.
$section_styles     = array();
$sanitized_bg_color = '' !== $bg_color ? sanitize_hex_color( $bg_color ) : null;
if ( is_string( $sanitized_bg_color ) && '' !== $sanitized_bg_color ) {
	$section_styles[] = 'background-color: ' . strtoupper( $sanitized_bg_color );
}

if ( '' !== $bg_img_url ) {
	$section_styles[] = 'background-image: url(' . esc_url( $bg_img_url ) . ')';
	$section_styles[] = 'background-position: center center';
	$section_styles[] = 'background-size: cover';
	$section_styles[] = 'background-repeat: no-repeat';
} elseif ( 'banner_left' === $layout ) {
	// Suppress static stylesheet background URL when no dynamic background image is selected.
	$section_styles[] = 'background-image: none';
}

if ( 'banner_left' === $layout && ( $has_badges || ( ! $has_content && $is_preview ) ) ) {
	$section_styles[] = 'height: auto';
}

$section_style_attr = ! empty( $section_styles ) ? ' style="' . esc_attr( implode( '; ', $section_styles ) . ';' ) . '"' : '';

// Layout-specific wrapper/heading class names matching assets/css/style.css.
$heading_class_map = array(
	'split_right' => 'about-heading',
	'split_left'  => 'about-tree-heading',
	'banner_left' => 'about-greener-heading',
);
$heading_class = $heading_class_map[ $layout ];

$content_wrap_class = 'split_left' === $layout ? 'about-tree-content' : 'about-tradition-content';
$image_wrap_class   = 'split_left' === $layout ? 'about-tree-img-wrapper' : 'about-tradition-img-wrapper ms-lg-auto';

// AOS animations (active on frontend only so editor blocks remain immediately visible).
$aos_left   = $is_preview ? '' : ' data-aos="fade-right" data-aos-duration="800"';
$aos_right  = $is_preview ? '' : ' data-aos="fade-left" data-aos-duration="800"';
$aos_banner = $is_preview ? '' : ' data-aos="fade-right" data-aos-duration="800"';

// Helper closure to render the text/badges/button stack inside the active content column.
$render_text_stack = static function () use (
	$has_title,
	$formatted_title,
	$heading_class,
	$has_desc,
	$description,
	$has_badges,
	$rendered_badges,
	$is_preview,
	$has_btn,
	$btn_url,
	$btn_title,
	$btn_target,
	$button_style,
	$icon_pos,
	$btn_icon_html
): void {
	if ( $has_title ) :
		?>
		<h2 class="<?php echo esc_attr( $heading_class ); ?>"><?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
		<?php
	endif;

	if ( $has_desc ) :
		echo wp_kses_post( wpautop( $description ) );
	endif;

	if ( $has_badges ) :
		?>
		<div class="about-badges-grid">
			<?php
			foreach ( $rendered_badges as $index => $badge ) :
				$delay     = 100 * ( $index + 1 );
				$aos_badge = $is_preview ? '' : ' data-aos="zoom-in" data-aos-duration="600" data-aos-delay="' . esc_attr( (string) $delay ) . '"';
				?>
				<div class="about-badge-item"<?php echo $aos_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php if ( '' !== $badge['icon_url'] || '' !== $badge['icon_class'] ) : ?>
						<div class="about-badge-icon">
							<?php if ( '' !== $badge['icon_url'] ) : ?>
								<img src="<?php echo esc_url( $badge['icon_url'] ); ?>" alt="<?php echo esc_attr( $badge['icon_alt'] ); ?>">
							<?php else : ?>
								<i class="<?php echo esc_attr( $badge['icon_class'] ); ?>" aria-hidden="true"></i>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( '' !== $badge['title'] || '' !== $badge['description'] ) : ?>
						<div class="about-badge-content">
							<?php if ( '' !== $badge['title'] ) : ?>
								<h3 class="about-badge-title"><?php echo esc_html( $badge['title'] ); ?></h3>
							<?php endif; ?>
							<?php if ( '' !== $badge['description'] ) : ?>
								<?php echo wp_kses_post( wpautop( $badge['description'] ) ); ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	endif;

	if ( $has_btn ) :
		$btn_classes     = $button_style . ( $has_badges ? ' mt-4' : '' );
		$target_attr     = '' !== $btn_target ? ' target="' . esc_attr( $btn_target ) . '"' : '';
		$rel_attr        = '_blank' === $btn_target ? ' rel="noopener noreferrer"' : '';
		$aria_label_attr = '' === $btn_title ? ' aria-label="' . esc_attr__( 'Learn more', 'xmastree-blocks' ) . '"' : '';
		?>
		<a href="<?php echo esc_url( $btn_url ); ?>" class="<?php echo esc_attr( $btn_classes ); ?>"<?php echo $target_attr . $rel_attr . $aria_label_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php if ( 'before' === $icon_pos && '' !== $btn_icon_html ) : ?><?php echo $btn_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?><?php if ( '' !== $btn_title ) : ?><?php echo esc_html( $btn_title ); ?><?php endif; ?><?php if ( 'after' === $icon_pos && '' !== $btn_icon_html ) : ?><?php echo $btn_icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?></a>
		<?php
	endif;
};
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container position-relative z-2">
		<?php if ( $has_content ) : ?>

			<?php if ( 'banner_left' === $layout ) : ?>

				<?php if ( $has_text ) : ?>
					<div class="about-greener-content"<?php echo $aos_banner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php $render_text_stack(); ?>
					</div>
				<?php elseif ( $is_preview ) : ?>
					<div class="text-center p-4 border border-dashed bg-white bg-opacity-75 text-dark rounded">
						<span><?php esc_html_e( 'Content Section (Panoramic Banner) — Add heading, description, or CTA button in the sidebar.', 'xmastree-blocks' ); ?></span>
					</div>
				<?php endif; ?>

			<?php else : ?>

				<?php
				$text_col_class  = $has_side ? 'col-lg-6' : 'col-lg-10 mx-auto';
				$image_col_class = $has_text ? 'col-lg-6' : 'col-lg-8 mx-auto';
				?>
				<div class="row align-items-center g-4 g-lg-5">
					<?php if ( 'split_left' === $layout ) : ?>

						<?php if ( $has_side ) : ?>
							<div class="<?php echo esc_attr( $image_col_class ); ?>"<?php echo $aos_left; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<div class="<?php echo esc_attr( $image_wrap_class ); ?>">
									<img src="<?php echo esc_url( $side_img_url ); ?>" alt="<?php echo esc_attr( $side_img_alt ); ?>">
								</div>
							</div>
						<?php endif; ?>

						<?php if ( $has_text ) : ?>
							<div class="<?php echo esc_attr( $text_col_class ); ?>"<?php echo $has_side ? $aos_right : $aos_left; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<div class="<?php echo esc_attr( $content_wrap_class ); ?>">
									<?php $render_text_stack(); ?>
								</div>
							</div>
						<?php endif; ?>

					<?php else : ?>

						<?php if ( $has_text ) : ?>
							<div class="<?php echo esc_attr( $text_col_class ); ?>"<?php echo $aos_left; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<div class="<?php echo esc_attr( $content_wrap_class ); ?>">
									<?php $render_text_stack(); ?>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( $has_side ) : ?>
							<div class="<?php echo esc_attr( $image_col_class ); ?>"<?php echo $has_text ? $aos_right : $aos_left; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<div class="<?php echo esc_attr( $image_wrap_class ); ?>">
									<img src="<?php echo esc_url( $side_img_url ); ?>" alt="<?php echo esc_attr( $side_img_alt ); ?>">
								</div>
							</div>
						<?php endif; ?>

					<?php endif; ?>
				</div>

			<?php endif; ?>

		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Content Section Block — Click here to choose a layout (Split Left/Right or Panoramic Banner) and configure fields in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>
