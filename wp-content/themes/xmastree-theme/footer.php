<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #primary <main> tag and all content after.
 * Fully dynamic: powered by ACF Theme Options and WordPress Navigation Menus.
 * All static code and hardcoded fallbacks have been removed.
 *
 * @package XmasTree_Theme
 * @since 1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper to safely resolve an ACF image field into an array containing 'url' and 'alt'.
 *
 * @param mixed  $image_field  ACF image value (array, ID int, or URL string).
 * @param string $fallback_alt Alt text fallback.
 * @return array{url: string, alt: string}
 */
$resolve_acf_image = static function ( $image_field, string $fallback_alt = '' ): array {
	$url = '';
	$alt = $fallback_alt;

	if ( is_array( $image_field ) && ! empty( $image_field['url'] ) ) {
		$url = (string) $image_field['url'];
		if ( ! empty( $image_field['alt'] ) ) {
			$alt = (string) $image_field['alt'];
		}
	} elseif ( is_numeric( $image_field ) && (int) $image_field > 0 ) {
		$img_url = wp_get_attachment_image_url( (int) $image_field, 'full' );
		if ( $img_url ) {
			$url      = (string) $img_url;
			$alt_meta = get_post_meta( (int) $image_field, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt_meta ) ) {
				$alt = (string) $alt_meta;
			}
		}
	} elseif ( is_string( $image_field ) && ! empty( $image_field ) ) {
		$url = $image_field;
	}

	return array(
		'url' => $url,
		'alt' => $alt,
	);
};

// -----------------------------------------------------------------------------
// 1. Column 1: Brand Logo & Tagline (ACF: footer_logo, footer_tagline)
// -----------------------------------------------------------------------------
$footer_logo_field = function_exists( 'get_field' ) ? get_field( 'footer_logo', 'option' ) : null;
$footer_logo_data  = $resolve_acf_image( $footer_logo_field, get_bloginfo( 'name' ) );
$footer_tagline    = function_exists( 'get_field' ) ? (string) get_field( 'footer_tagline', 'option' ) : '';

// -----------------------------------------------------------------------------
// 2. Columns 2 & 3: Dynamic Menus & Headings from Menu Names
// -----------------------------------------------------------------------------
$locations = get_nav_menu_locations();

// Quick Links
$quick_menu_title = '';
$has_quick_menu   = has_nav_menu( 'footer-quick-links' );
if ( $has_quick_menu && ! empty( $locations['footer-quick-links'] ) ) {
	$quick_menu_obj = wp_get_nav_menu_object( $locations['footer-quick-links'] );
	if ( $quick_menu_obj && ! is_wp_error( $quick_menu_obj ) ) {
		$quick_menu_title = $quick_menu_obj->name;
	}
}

// Helpful Links
$helpful_menu_title = '';
$has_helpful_menu   = has_nav_menu( 'footer-helpful-links' );
if ( $has_helpful_menu && ! empty( $locations['footer-helpful-links'] ) ) {
	$helpful_menu_obj = wp_get_nav_menu_object( $locations['footer-helpful-links'] );
	if ( $helpful_menu_obj && ! is_wp_error( $helpful_menu_obj ) ) {
		$helpful_menu_title = $helpful_menu_obj->name;
	}
}

// -----------------------------------------------------------------------------
// 3. Column 4: Contact Information (ACF: footer_contact_heading, footer_phone, footer_email, footer_hours)
// -----------------------------------------------------------------------------
$contact_heading = function_exists( 'get_field' ) ? (string) get_field( 'footer_contact_heading', 'option' ) : '';
$phone           = function_exists( 'get_field' ) ? (string) get_field( 'footer_phone', 'option' ) : '';
$email           = function_exists( 'get_field' ) ? (string) get_field( 'footer_email', 'option' ) : '';
$hours           = function_exists( 'get_field' ) ? (string) get_field( 'footer_hours', 'option' ) : '';
$phone_clean     = preg_replace( '/[^0-9+]/', '', $phone );

// -----------------------------------------------------------------------------
// 4. Column 5: Christmas Tree Illustration (ACF: footer_tree_image)
// -----------------------------------------------------------------------------
$tree_field = function_exists( 'get_field' ) ? get_field( 'footer_tree_image', 'option' ) : null;
$tree_data  = $resolve_acf_image( $tree_field, __( 'Illuminated Christmas Tree', 'xmastree-theme' ) );

// -----------------------------------------------------------------------------
// 5. Bottom Bar: Copyright Text (ACF: footer_copyright)
// -----------------------------------------------------------------------------
$copyright = function_exists( 'get_field' ) ? (string) get_field( 'footer_copyright', 'option' ) : '';
?>

<!-- ==========================================================================
     Footer
     ========================================================================== -->
<footer class="site-footer">
	<div class="container">
		<div class="row g-4 gy-5 gy-lg-4 align-items-start justify-content-center justify-content-md-start footer-main-row">

			<!-- Col 1: Brand & Socials -->
			<?php
			$has_social_icons = function_exists( 'have_rows' ) && have_rows( 'social_icons', 'option' );
			$has_col_1        = ! empty( $footer_logo_data['url'] ) || ! empty( $footer_tagline ) || $has_social_icons;
			if ( $has_col_1 ) :
			?>
				<div class="col-12 col-md-6 col-lg-3 footer-col-brand" data-aos="fade-up" data-aos-duration="600" data-aos-delay="100">
					<?php if ( ! empty( $footer_logo_data['url'] ) ) : ?>
						<img src="<?php echo esc_url( $footer_logo_data['url'] ); ?>" alt="<?php echo esc_attr( $footer_logo_data['alt'] ); ?>" class="footer-logo">
					<?php endif; ?>

					<?php if ( ! empty( $footer_tagline ) ) : ?>
						<div class="footer-tagline">
							<?php echo wp_kses_post( wpautop( $footer_tagline ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $has_social_icons ) : ?>
						<div class="footer-social-links">
							<?php
							while ( have_rows( 'social_icons', 'option' ) ) :
								the_row();
								$icon_raw  = get_sub_field( 'icon' );
								$icon_link = get_sub_field( 'icon_link' );

								if ( empty( $icon_raw ) && empty( $icon_link ) ) {
									continue;
								}

								$icon_value = '';
								if ( is_array( $icon_raw ) ) {
									$icon_value = $icon_raw['value'] ?? ( $icon_raw['url'] ?? '' );
								} elseif ( is_string( $icon_raw ) ) {
									$icon_value = trim( $icon_raw );
								}

								$is_image_url = false;
								if ( is_string( $icon_value ) && (
									str_starts_with( $icon_value, 'http://' ) ||
									str_starts_with( $icon_value, 'https://' ) ||
									str_starts_with( $icon_value, '/' ) ||
									(bool) preg_match( '/\.(svg|png|webp|jpg|jpeg|gif)$/i', $icon_value )
								) ) {
									$is_image_url = true;
								}

								// Derive a descriptive aria-label for accessibility.
								$aria_label = '';
								if ( ! empty( $icon_value ) && is_string( $icon_value ) && ! $is_image_url ) {
									$clean_name = preg_replace( '/^(bi|dashicons|fa|fab|fas)[- ]+/', '', $icon_value );
									$clean_name = str_replace( array( '-', '_' ), ' ', (string) $clean_name );
									$aria_label = ucwords( trim( $clean_name ) );
								}
								if ( empty( $aria_label ) && ! empty( $icon_link ) ) {
									$host = parse_url( (string) $icon_link, PHP_URL_HOST );
									if ( $host ) {
										$host       = preg_replace( '/^www\./i', '', $host );
										$parts      = explode( '.', $host );
										$aria_label = ucwords( $parts[0] ?? '' );
									}
								}
								if ( empty( $aria_label ) ) {
									$aria_label = __( 'Social Link', 'xmastree-theme' );
								}

								$icon_class = (string) $icon_value;
								if ( str_starts_with( $icon_class, 'bi-' ) && ! str_contains( $icon_class, 'bi ' ) ) {
									$icon_class = 'bi ' . $icon_class;
								} elseif ( str_starts_with( $icon_class, 'dashicons-' ) && ! str_contains( $icon_class, 'dashicons ' ) ) {
									$icon_class = 'dashicons ' . $icon_class;
								}
								?>
								<a href="<?php echo ! empty( $icon_link ) ? esc_url( (string) $icon_link ) : '#'; ?>" target="_blank" rel="noopener noreferrer" class="social-btn" aria-label="<?php echo esc_attr( $aria_label ); ?>">
									<?php if ( $is_image_url ) : ?>
										<img src="<?php echo esc_url( $icon_value ); ?>" alt="<?php echo esc_attr( $aria_label ); ?>" class="social-icon-img" style="max-width: 20px; max-height: 20px; object-fit: contain;">
									<?php else : ?>
										<i class="<?php echo esc_attr( $icon_class ); ?>"></i>
									<?php endif; ?>
								</a>
							<?php endwhile; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Col 2: Quick Links (Heading dynamically derived from Menu Name) -->
			<?php if ( $has_quick_menu ) : ?>
				<div class="col-6 col-md-6 col-lg-2 footer-col-links" data-aos="fade-up" data-aos-duration="600" data-aos-delay="200">
					<?php if ( ! empty( $quick_menu_title ) ) : ?>
						<h4 class="footer-heading"><?php echo esc_html( $quick_menu_title ); ?></h4>
					<?php endif; ?>
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer-quick-links',
							'container'      => false,
							'menu_class'     => 'footer-links',
							'fallback_cb'    => false,
							'depth'          => 1,
						)
					);
					?>
				</div>
			<?php endif; ?>

			<!-- Col 3: Helpful Links (Heading dynamically derived from Menu Name) -->
			<?php if ( $has_helpful_menu ) : ?>
				<div class="col-6 col-md-6 col-lg-2 footer-col-links" data-aos="fade-up" data-aos-duration="600" data-aos-delay="300">
					<?php if ( ! empty( $helpful_menu_title ) ) : ?>
						<h4 class="footer-heading"><?php echo esc_html( $helpful_menu_title ); ?></h4>
					<?php endif; ?>
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'footer-helpful-links',
							'container'      => false,
							'menu_class'     => 'footer-links',
							'fallback_cb'    => false,
							'depth'          => 1,
						)
					);
					?>
				</div>
			<?php endif; ?>

			<!-- Col 4: Get In Touch (ACF Contact Info) -->
			<?php
			$has_contact_info = ! empty( $contact_heading ) || ! empty( $phone ) || ! empty( $email ) || ! empty( $hours );
			if ( $has_contact_info ) :
			?>
				<div class="col-12 col-md-6 col-lg-3 footer-col-contact" data-aos="fade-up" data-aos-duration="600" data-aos-delay="400">
					<?php if ( ! empty( $contact_heading ) ) : ?>
						<h4 class="footer-heading"><?php echo esc_html( $contact_heading ); ?></h4>
					<?php endif; ?>

					<?php if ( ! empty( $phone ) ) : ?>
						<div class="footer-contact-item">
							<i class="bi bi-telephone-fill" aria-hidden="true"></i>
							<span class="footer-contact-text">
								<?php if ( ! empty( $phone_clean ) ) : ?>
									<a href="tel:<?php echo esc_attr( $phone_clean ); ?>"><?php echo esc_html( $phone ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $phone ); ?>
								<?php endif; ?>
							</span>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $email ) ) : ?>
						<div class="footer-contact-item">
							<i class="bi bi-envelope-fill" aria-hidden="true"></i>
							<span class="footer-contact-text">
								<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
							</span>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $hours ) ) : ?>
						<div class="footer-contact-item">
							<i class="bi bi-clock-fill" aria-hidden="true"></i>
							<span class="footer-contact-text"><?php echo esc_html( $hours ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<!-- Col 5: Christmas Tree Illustration (ACF: footer_tree_image) -->
			<?php if ( ! empty( $tree_data['url'] ) ) : ?>
				<div class="col-lg-2 text-center d-none d-lg-block footer-col-tree align-self-lg-center" data-aos="zoom-in" data-aos-duration="800" data-aos-delay="500">
					<img src="<?php echo esc_url( $tree_data['url'] ); ?>" alt="<?php echo esc_attr( $tree_data['alt'] ); ?>" class="footer-tree-img">
				</div>
			<?php endif; ?>

		</div>

		<!-- Bottom Bar (ACF: footer_copyright) -->
		<?php if ( ! empty( $copyright ) ) : ?>
			<div class="footer-bottom-bar text-center">
				<?php echo wp_kses_post( wpautop( $copyright ) ); ?>
			</div>
		<?php endif; ?>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
