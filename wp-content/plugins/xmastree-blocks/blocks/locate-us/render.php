<?php
/**
 * Block Template: Locate Us
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
$block_id = 'locate-us-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
	$block_id = (string) $block['anchor'];
}

$class_names = array( 'locate-us-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . $block['align'];
}

// -----------------------------------------------------------------------------
// 2. Retrieve ACF Fields
// -----------------------------------------------------------------------------
$title         = function_exists( 'get_field' ) ? (string) get_field( 'locate_title' ) : '';
if ( str_contains( strtolower( $title ), 'u003c' ) ) {
	$title = preg_replace( '/\\\\?u003c/i', '<', $title );
	$title = preg_replace( '/\\\\?u003e/i', '>', $title );
	$title = preg_replace( '/\\\\?u002f/i', '/', $title );
}
$locations_raw = function_exists( 'get_field' ) ? get_field( 'locate_locations' ) : null;
$image_field   = function_exists( 'get_field' ) ? get_field( 'locate_image' ) : null;

// Resolve repeater items via have_rows() or raw array fallback.
$location_items = array();
if ( function_exists( 'have_rows' ) && have_rows( 'locate_locations' ) ) {
	while ( have_rows( 'locate_locations' ) ) {
		the_row();
		$location_items[] = array(
			'name'         => get_sub_field( 'name' ),
			'description'  => get_sub_field( 'description' ),
			'opening_date' => get_sub_field( 'opening_date' ),
			'hours'        => get_sub_field( 'hours' ),
			'link'         => get_sub_field( 'link' ),
		);
	}
} elseif ( is_array( $locations_raw ) && ! empty( $locations_raw ) ) {
	foreach ( $locations_raw as $item ) {
		if ( is_array( $item ) ) {
			$location_items[] = array(
				'name'         => $item['name'] ?? '',
				'description'  => $item['description'] ?? '',
				'opening_date' => $item['opening_date'] ?? '',
				'hours'        => $item['hours'] ?? '',
				'link'         => $item['link'] ?? null,
			);
		}
	}
}

// Normalize and filter out empty location rows.
$rendered_locations = array();
foreach ( $location_items as $item ) {
	$loc_name = trim( (string) ( $item['name'] ?? '' ) );
	$loc_desc = trim( (string) ( $item['description'] ?? '' ) );
	$loc_date = trim( (string) ( $item['opening_date'] ?? '' ) );
	$loc_hrs  = trim( (string) ( $item['hours'] ?? '' ) );
	$loc_link = $item['link'] ?? null;

	$link_url    = '';
	$link_title  = '';
	$link_target = '';
	if ( is_array( $loc_link ) && ! empty( $loc_link['url'] ) ) {
		$link_url    = (string) $loc_link['url'];
		$link_title  = ! empty( $loc_link['title'] ) ? (string) $loc_link['title'] : '';
		$link_target = ! empty( $loc_link['target'] ) ? (string) $loc_link['target'] : '';
	} elseif ( is_string( $loc_link ) && '' !== trim( $loc_link ) ) {
		$link_url = trim( $loc_link );
	}

	if ( '' === $loc_name && '' === $loc_desc && '' === $loc_date && '' === $loc_hrs && '' === $link_url ) {
		continue;
	}

	$rendered_locations[] = array(
		'name'         => $loc_name,
		'description'  => $loc_desc,
		'opening_date' => $loc_date,
		'hours'        => $loc_hrs,
		'link_url'     => $link_url,
		'link_title'   => $link_title,
		'link_target'  => $link_target,
	);
}

// Resolve Right-Side Image URL and Alt.
$image_url = '';
$image_alt = '';
if ( is_array( $image_field ) && ! empty( $image_field['url'] ) ) {
	$image_url = (string) $image_field['url'];
	if ( ! empty( $image_field['alt'] ) ) {
		$image_alt = (string) $image_field['alt'];
	}
} elseif ( is_numeric( $image_field ) && (int) $image_field > 0 ) {
	$img = wp_get_attachment_image_url( (int) $image_field, 'full' );
	if ( $img ) {
		$image_url = (string) $img;
		$alt_meta  = get_post_meta( (int) $image_field, '_wp_attachment_image_alt', true );
		if ( ! empty( $alt_meta ) ) {
			$image_alt = (string) $alt_meta;
		}
	}
} elseif ( is_string( $image_field ) && ! empty( $image_field ) ) {
	$image_url = $image_field;
}

if ( '' === $image_alt && ! empty( $image_url ) ) {
	$image_alt = '' !== trim( $title ) ? wp_strip_all_tags( $title ) : get_bloginfo( 'name' );
}

// Determine column visibility.
$has_title     = '' !== trim( $title );
$has_locations = ! empty( $rendered_locations );
$has_left_col  = $has_title || $has_locations;
$has_right_col = ! empty( $image_url );
$has_content   = $has_left_col || $has_right_col;

if ( ! $has_content && ! $is_preview ) {
	return;
}

$left_col_class  = 'col-lg-6';
$right_col_class = 'col-lg-6';
if ( $has_left_col && ! $has_right_col ) {
	$left_col_class = 'col-lg-10 mx-auto';
} elseif ( ! $has_left_col && $has_right_col ) {
	$right_col_class = 'col-lg-8 mx-auto';
}

// AOS Animation Attributes (active only on frontend).
$aos_left  = $is_preview ? '' : ' data-aos="fade-right" data-aos-duration="800"';
$aos_right = $is_preview ? '' : ' data-aos="fade-left" data-aos-duration="800"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>">
	<div class="container">
		<?php if ( $has_content ) : ?>
			<div class="row g-4 align-items-stretch">

				<!-- Left Panel: Locations List -->
				<?php if ( $has_left_col ) : ?>
					<div class="<?php echo esc_attr( $left_col_class ); ?>"<?php echo $aos_left; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<div class="locate-panel">
							<?php if ( $has_title ) : ?>
								<h2 class="locate-title"><?php echo wp_kses_post( $title ); ?></h2>
							<?php endif; ?>

							<?php if ( $has_locations ) : ?>
								<?php foreach ( $rendered_locations as $loc ) : ?>
									<div class="location-item">
										<i class="bi bi-geo-alt-fill location-marker-icon" aria-hidden="true"></i>

										<?php if ( '' !== $loc['name'] ) : ?>
											<h3 class="location-name"><?php echo esc_html( $loc['name'] ); ?></h3>
										<?php endif; ?>

										<?php if ( '' !== $loc['description'] ) : ?>
											<?php echo wp_kses_post( wpautop( $loc['description'] ) ); ?>
										<?php endif; ?>

										<?php if ( '' !== $loc['opening_date'] || '' !== $loc['hours'] || '' !== $loc['link_url'] ) : ?>
											<div class="location-bottom-row">
												<?php if ( '' !== $loc['opening_date'] ) : ?>
													<div class="location-opening-date">
														<?php if ( ! str_contains( $loc['opening_date'], '<i' ) && ! str_contains( $loc['opening_date'], '<svg' ) ) : ?>
															<i class="bi bi-calendar-event-fill" aria-hidden="true"></i>
														<?php endif; ?>
														<span><?php echo wp_kses_post( $loc['opening_date'] ); ?></span>
													</div>
												<?php endif; ?>

												<?php if ( '' !== $loc['hours'] ) : ?>
													<div class="location-hours">
														<i class="bi bi-clock-fill" aria-hidden="true"></i> <?php echo esc_html( $loc['hours'] ); ?>
													</div>
												<?php endif; ?>

												<?php if ( '' !== $loc['link_url'] ) : ?>
													<a href="<?php echo esc_url( $loc['link_url'] ); ?>" class="location-link"<?php echo ! empty( $loc['link_target'] ) ? ' target="' . esc_attr( $loc['link_target'] ) . '" rel="noopener noreferrer"' : ''; ?>><?php if ( '' !== $loc['link_title'] ) : ?><?php echo esc_html( $loc['link_title'] ); ?> <?php endif; ?><i class="bi bi-arrow-right" aria-hidden="true"></i></a>
												<?php endif; ?>
											</div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- Right Side: Location Image -->
				<?php if ( $has_right_col ) : ?>
					<div class="<?php echo esc_attr( $right_col_class ); ?>"<?php echo $aos_right; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<div class="locate-image-card">
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>">
						</div>
					</div>
				<?php endif; ?>

			</div>
		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-4 border border-dashed text-muted">
				<p class="mb-0"><?php esc_html_e( 'Locate Us Block — Click here to configure locations and photo in the sidebar.', 'xmastree-blocks' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>
