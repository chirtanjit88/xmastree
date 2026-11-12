<?php
/**
 * Block Template: Why Choose Us
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
$block_id = 'why-us-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
	$block_id = (string) $block['anchor'];
}

$class_names = array( 'why-us-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . $block['align'];
}

// -----------------------------------------------------------------------------
// 2. Retrieve ACF Fields
// -----------------------------------------------------------------------------
$image_field  = function_exists( 'get_field' ) ? get_field( 'why_us_image' ) : null;
$title        = function_exists( 'get_field' ) ? (string) get_field( 'why_us_title' ) : '';
$subtitle     = function_exists( 'get_field' ) ? (string) get_field( 'why_us_subtitle' ) : '';
$features_raw = function_exists( 'get_field' ) ? get_field( 'why_us_features' ) : null;

// Resolve features list
$feature_items = array();
if ( function_exists( 'have_rows' ) && have_rows( 'why_us_features' ) ) {
	while ( have_rows( 'why_us_features' ) ) {
		the_row();
		$feature_items[] = array(
			'icon'        => get_sub_field( 'icon' ),
			'title'       => get_sub_field( 'title' ),
			'description' => get_sub_field( 'description' ),
		);
	}
} elseif ( is_array( $features_raw ) && ! empty( $features_raw ) ) {
	foreach ( $features_raw as $item ) {
		if ( is_array( $item ) ) {
			$feature_items[] = array(
				'icon'        => $item['icon'] ?? '',
				'title'       => $item['title'] ?? '',
				'description' => $item['description'] ?? '',
			);
		}
	}
}

// Resolve Left Image URL and Alt
$image_url = '';
$image_alt = get_bloginfo( 'name' );
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

$has_features  = ! empty( $feature_items );
$has_right_col = ! empty( $title ) || ! empty( $subtitle ) || $has_features;
$has_content   = ! empty( $image_url ) || $has_right_col;

// Determine Column Classes
$left_col_class  = 'col-lg-6 mb-5 mb-lg-0';
$right_col_class = 'col-lg-6 ps-lg-5';
if ( empty( $image_url ) && $has_right_col ) {
	$right_col_class = 'col-lg-10 mx-auto';
} elseif ( ! empty( $image_url ) && ! $has_right_col ) {
	$left_col_class = 'col-lg-8 mx-auto';
}

// AOS Animation Helpers (active only on frontend)
$aos_img_attr = $is_preview ? '' : ' data-aos="fade-right" data-aos-duration="800"';
$aos_hdr_attr = $is_preview ? '' : ' data-aos="fade-left" data-aos-duration="800"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>">
	<div class="container">
		<?php if ( $has_content ) : ?>
			<div class="row align-items-center">

				<!-- Left Photo Column -->
				<?php if ( ! empty( $image_url ) ) : ?>
					<div class="<?php echo esc_attr( $left_col_class ); ?>"<?php echo $aos_img_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<div class="why-us-image-card">
							<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>">
						</div>
					</div>
				<?php endif; ?>

				<!-- Right Column: Header & Features List -->
				<?php if ( $has_right_col ) : ?>
					<div class="<?php echo esc_attr( $right_col_class ); ?>">

						<?php if ( ! empty( $title ) || ! empty( $subtitle ) ) : ?>
							<div class="section-header mb-4"<?php echo $aos_hdr_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
								<?php if ( ! empty( $title ) ) : ?>
									<h2 class="section-title">
										<?php echo wp_kses_post( $title ); ?>
									</h2>
								<?php endif; ?>

								<?php if ( ! empty( $subtitle ) ) : ?>
									<p><?php echo esc_html( $subtitle ); ?></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<!-- Features List -->
						<?php if ( $has_features ) : ?>
							<?php
							foreach ( $feature_items as $index => $item ) :
								$f_icon  = $item['icon'] ?? null;
								$f_title = $item['title'] ?? '';
								$f_desc  = $item['description'] ?? '';

								if ( empty( $f_icon ) && empty( $f_title ) && empty( $f_desc ) ) {
									continue;
								}

								$delay    = 100 * ( $index + 1 );
								$aos_card = $is_preview ? '' : ' data-aos="fade-left" data-aos-duration="600" data-aos-delay="' . esc_attr( (string) $delay ) . '"';

								// Process Icon (Image array/string OR icon class)
								$f_icon_url   = '';
								$f_icon_class = '';
								if ( is_array( $f_icon ) && ! empty( $f_icon['url'] ) ) {
									$f_icon_url = (string) $f_icon['url'];
								} elseif ( is_numeric( $f_icon ) && (int) $f_icon > 0 ) {
									$icon_src = wp_get_attachment_image_url( (int) $f_icon, 'full' );
									if ( $icon_src ) {
										$f_icon_url = (string) $icon_src;
									}
								} elseif ( is_string( $f_icon ) && ! empty( $f_icon ) ) {
									if ( str_starts_with( $f_icon, 'http://' ) || str_starts_with( $f_icon, 'https://' ) || str_starts_with( $f_icon, '/' ) ) {
										$f_icon_url = $f_icon;
									} else {
										$f_icon_class = $f_icon;
										if ( str_starts_with( $f_icon_class, 'bi-' ) && ! str_contains( $f_icon_class, 'bi ' ) ) {
											$f_icon_class = 'bi ' . $f_icon_class;
										}
									}
								}
								?>
								<div class="why-feature-card"<?php echo $aos_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
									<?php if ( ! empty( $f_icon_url ) ) : ?>
										<img src="<?php echo esc_url( $f_icon_url ); ?>" alt="<?php echo esc_attr( (string) $f_title ); ?>" class="why-feature-icon">
									<?php elseif ( ! empty( $f_icon_class ) ) : ?>
										<i class="<?php echo esc_attr( $f_icon_class ); ?> why-feature-icon fs-2"></i>
									<?php endif; ?>

									<?php if ( ! empty( $f_title ) || ! empty( $f_desc ) ) : ?>
										<div>
											<?php if ( ! empty( $f_title ) ) : ?>
												<h3 class="why-feature-title"><?php echo esc_html( (string) $f_title ); ?></h3>
											<?php endif; ?>
											<?php if ( ! empty( $f_desc ) ) : ?>
												<?php echo wp_kses_post( wpautop( (string) $f_desc ) ); ?>
											<?php endif; ?>
										</div>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>

					</div>
				<?php endif; ?>

			</div>
		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-4 border border-dashed text-muted">
				<p class="mb-0"><?php esc_html_e( 'Why Choose Us Block — Click here to configure content in the sidebar.', 'xmastree-blocks' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>
