<?php
/**
 * Block Template: Special Christmas Offers
 *
 * Renders the "Special Christmas Offers" 3-card section from mas-corner.html
 * with a centered two-tone heading, intro subtitle, and a responsive grid of offer cards
 * (top image, icon, uppercase title, description, and bold crimson bottom offer label).
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
$block_id = 'special-offers-' . ( $block['id'] ?? uniqid() );
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
			} else {
				$att_title = get_the_title( $id );
				if ( ! empty( $att_title ) && '' === $alt ) {
					$alt = (string) $att_title;
				}
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
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_special_offers)
// -----------------------------------------------------------------------------
$title     = function_exists( 'get_field' ) ? trim( (string) get_field( 'offers_title' ) ) : '';
$subtitle  = function_exists( 'get_field' ) ? trim( (string) get_field( 'offers_subtitle' ) ) : '';
$items_raw = function_exists( 'get_field' ) ? get_field( 'offers_items' ) : null;
$columns   = function_exists( 'get_field' ) ? $normalize_select( get_field( 'offers_columns' ) ) : '';
$bg_color  = function_exists( 'get_field' ) ? $normalize_select( get_field( 'offers_background_color' ) ) : '';

// -----------------------------------------------------------------------------
// 3. Resolve Section Classes, Grid Columns & Optional Inline Background
// -----------------------------------------------------------------------------
$class_names = array( 'mas-offers-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . (string) $block['align'];
}

$col_class_map = array(
	'3' => array(
		'class' => 'col-lg-4 col-md-6',
		'count' => 3,
	),
	'4' => array(
		'class' => 'col-lg-3 col-md-6',
		'count' => 4,
	),
	'2' => array(
		'class' => 'col-md-6',
		'count' => 2,
	),
);

if ( '' === $columns || ! isset( $col_class_map[ $columns ] ) ) {
	$columns = '3';
}
$col_class = $col_class_map[ $columns ]['class'];
$col_count = $col_class_map[ $columns ]['count'];

$section_style_attr = '';
$sanitized_bg_color = '' !== $bg_color ? sanitize_hex_color( $bg_color ) : null;
if ( is_string( $sanitized_bg_color ) && '' !== $sanitized_bg_color ) {
	$section_style_attr = ' style="' . esc_attr( 'background-color: ' . strtoupper( $sanitized_bg_color ) . ';' ) . '"';
}

// -----------------------------------------------------------------------------
// 4. Resolve Offer Cards Repeater
// -----------------------------------------------------------------------------
$offer_rows = array();
if ( function_exists( 'have_rows' ) && have_rows( 'offers_items' ) ) {
	while ( have_rows( 'offers_items' ) ) {
		the_row();
		$offer_rows[] = array(
			'image'       => get_sub_field( 'image' ),
			'icon'        => get_sub_field( 'icon' ),
			'title'       => get_sub_field( 'title' ),
			'description' => get_sub_field( 'description' ),
			'offer_label' => get_sub_field( 'offer_label' ),
		);
	}
} elseif ( is_array( $items_raw ) && ! empty( $items_raw ) ) {
	foreach ( $items_raw as $row ) {
		if ( is_array( $row ) ) {
			$offer_rows[] = array(
				'image'       => $row['image'] ?? null,
				'icon'        => $row['icon'] ?? null,
				'title'       => $row['title'] ?? '',
				'description' => $row['description'] ?? '',
				'offer_label' => $row['offer_label'] ?? '',
			);
		}
	}
}

$title_plain    = $extract_plain_text( $title );
$has_title      = '' !== $title_plain;
$has_subtitle   = $has_rich_content( $subtitle );
$rendered_cards = array();

foreach ( $offer_rows as $row ) {
	$o_title       = trim( (string) ( $row['title'] ?? '' ) );
	$o_title_plain = $extract_plain_text( $o_title );
	$has_o_title   = '' !== $o_title_plain;

	$o_desc_raw   = trim( (string) ( $row['description'] ?? '' ) );
	$has_o_desc   = $has_rich_content( $o_desc_raw );
	$o_desc       = $has_o_desc ? $o_desc_raw : '';
	$o_desc_plain = $extract_plain_text( $o_desc );

	$o_label       = trim( (string) ( $row['offer_label'] ?? '' ) );
	$o_label_plain = $extract_plain_text( $o_label );
	$has_o_label   = '' !== $o_label_plain;

	$fallback_alt = $has_o_title
		? $o_title_plain
		: ( '' !== $o_desc_plain ? wp_trim_words( $o_desc_plain, 8, '' ) : $title_plain );
	$resolved_img = $resolve_image( $row['image'] ?? null, $fallback_alt );
	$o_img_url    = $resolved_img['url'];
	$o_img_alt    = $resolved_img['alt'];

	$o_icon       = $row['icon'] ?? null;
	$o_icon_url   = '';
	$o_icon_class = '';
	$o_icon_alt   = '';

	if ( is_array( $o_icon ) || is_numeric( $o_icon ) ) {
		$resolved_icon = $resolve_image( $o_icon, '' );
		$o_icon_url    = $resolved_icon['url'];
		$o_icon_alt    = $resolved_icon['alt'];
	} elseif ( is_string( $o_icon ) && '' !== trim( $o_icon ) ) {
		$icon_str = trim( $o_icon );
		if ( str_starts_with( $icon_str, 'http://' ) || str_starts_with( $icon_str, 'https://' ) || str_starts_with( $icon_str, '/' ) ) {
			$o_icon_url = $icon_str;
		} else {
			$o_icon_class = $icon_str;
			if ( str_starts_with( $o_icon_class, 'bi-' ) && ! str_contains( $o_icon_class, 'bi ' ) ) {
				$o_icon_class = 'bi ' . $o_icon_class;
			} elseif ( str_starts_with( $o_icon_class, 'dashicons-' ) && ! str_contains( $o_icon_class, 'dashicons ' ) ) {
				$o_icon_class = 'dashicons ' . $o_icon_class;
			}
		}
	}

	if (
		'' === $o_img_url &&
		'' === $o_icon_url &&
		'' === $o_icon_class &&
		! $has_o_title &&
		! $has_o_desc &&
		! $has_o_label
	) {
		continue;
	}

	$rendered_cards[] = array(
		'img_url'     => $o_img_url,
		'img_alt'     => $o_img_alt,
		'icon_url'    => $o_icon_url,
		'icon_class'  => $o_icon_class,
		'icon_alt'    => $o_icon_alt,
		'title'       => $has_o_title ? $o_title : '',
		'description' => $o_desc,
		'offer_label' => $has_o_label ? $o_label : '',
	);
}

// -----------------------------------------------------------------------------
// 5. Resolve Visibility & Formatted Heading
// -----------------------------------------------------------------------------
$has_header  = $has_title || $has_subtitle;
$has_cards   = ! empty( $rendered_cards );
$has_content = $has_header || $has_cards;

if ( ! $has_content && ! $is_preview ) {
	return;
}

$formatted_title = '';
if ( $has_title ) {
	$has_explicit_br = 1 === preg_match( '/<br\b/i', $title );
	$formatted_title = $has_explicit_br ? wp_kses_post( $title ) : nl2br( wp_kses_post( $title ) );
}

// AOS animations active on frontend only so editor preview remains immediately visible.
$aos_header = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $section_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if ( $has_content ) : ?>

			<?php if ( $has_header ) : ?>
				<div class="section-header text-center mas-offers-header"<?php echo $aos_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<?php if ( $has_title ) : ?>
						<h2 class="section-title"><?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
					<?php endif; ?>

					<?php if ( $has_subtitle ) : ?>
						<?php echo wp_kses_post( wpautop( $subtitle ) ); ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $has_cards ) : ?>
				<div class="row g-4 justify-content-center">
					<?php
					foreach ( $rendered_cards as $index => $card ) :
						$delay    = 100 * ( ( $index % $col_count ) + 1 );
						$aos_card = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="600" data-aos-delay="' . esc_attr( (string) $delay ) . '"';
						$has_body = '' !== $card['icon_url'] || '' !== $card['icon_class'] || '' !== $card['title'] || '' !== $card['description'] || '' !== $card['offer_label'];
						?>
						<div class="<?php echo esc_attr( $col_class ); ?>"<?php echo $aos_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<div class="mas-offer-card">
								<?php if ( '' !== $card['img_url'] ) : ?>
									<img src="<?php echo esc_url( $card['img_url'] ); ?>" alt="<?php echo esc_attr( $card['img_alt'] ); ?>" class="mas-offer-img">
								<?php endif; ?>

								<?php if ( $has_body ) : ?>
									<div class="mas-offer-body">
										<?php if ( '' !== $card['icon_url'] ) : ?>
											<img src="<?php echo esc_url( $card['icon_url'] ); ?>" alt="<?php echo esc_attr( $card['icon_alt'] ); ?>" class="mas-offer-icon-img">
										<?php elseif ( '' !== $card['icon_class'] ) : ?>
											<i class="<?php echo esc_attr( $card['icon_class'] ); ?> mas-offer-icon-img d-inline-flex align-items-center justify-content-start fs-1" style="color: #AB0030;" aria-hidden="true"></i>
										<?php endif; ?>

										<?php if ( '' !== $card['title'] ) : ?>
											<h4 class="mas-offer-title"><?php echo wp_kses_post( $card['title'] ); ?></h4>
										<?php endif; ?>

										<?php if ( '' !== $card['description'] ) : ?>
											<?php echo wp_kses_post( wpautop( $card['description'] ) ); ?>
										<?php endif; ?>

										<?php if ( '' !== $card['offer_label'] ) : ?>
											<div class="mas-offer-text"><?php echo wp_kses_post( $card['offer_label'] ); ?></div>
										<?php endif; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Special Christmas Offers Block — Click here to add section heading, intro text, and offer cards in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>

