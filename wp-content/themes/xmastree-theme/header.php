<?php
/**
 * The header for our theme
 *
 * Displays the dynamic <head> section, document title,
 * and the navigation header powered by ACF Theme Options.
 *
 * @package XmasTree_Theme
 * @since 1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// -----------------------------------------------------------------------------
// 1. Retrieve Main Logo from ACF Theme Options or WordPress Custom Logo
// -----------------------------------------------------------------------------
$logo_field = function_exists( 'get_field' ) ? get_field( 'logo', 'option' ) : null;
$logo_url   = '';
$logo_alt   = get_bloginfo( 'name' );

if ( is_array( $logo_field ) && ! empty( $logo_field['url'] ) ) {
	$logo_url = (string) $logo_field['url'];
	if ( ! empty( $logo_field['alt'] ) ) {
		$logo_alt = (string) $logo_field['alt'];
	}
} elseif ( is_numeric( $logo_field ) ) {
	$logo_url = (string) wp_get_attachment_image_url( (int) $logo_field, 'full' );
	$alt_meta = get_post_meta( (int) $logo_field, '_wp_attachment_image_alt', true );
	if ( ! empty( $alt_meta ) ) {
		$logo_alt = (string) $alt_meta;
	}
} elseif ( is_string( $logo_field ) && ! empty( $logo_field ) ) {
	$logo_url = $logo_field;
} elseif ( has_custom_logo() ) {
	$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
	$logo_url       = (string) wp_get_attachment_image_url( $custom_logo_id, 'full' );
	$alt_meta       = get_post_meta( $custom_logo_id, '_wp_attachment_image_alt', true );
	if ( ! empty( $alt_meta ) ) {
		$logo_alt = (string) $alt_meta;
	}
}

// -----------------------------------------------------------------------------
// 2. Retrieve Christmas Corner Logo and Link from ACF Theme Options
// -----------------------------------------------------------------------------
$corner_field = function_exists( 'get_field' ) ? get_field( 'christmas_corner_logo', 'option' ) : null;
$corner_url   = '';
$corner_alt   = "Ma's Christmas Corner";

if ( is_array( $corner_field ) && ! empty( $corner_field['url'] ) ) {
	$corner_url = (string) $corner_field['url'];
	if ( ! empty( $corner_field['alt'] ) ) {
		$corner_alt = (string) $corner_field['alt'];
	}
} elseif ( is_numeric( $corner_field ) ) {
	$corner_url = (string) wp_get_attachment_image_url( (int) $corner_field, 'full' );
	$alt_meta   = get_post_meta( (int) $corner_field, '_wp_attachment_image_alt', true );
	if ( ! empty( $alt_meta ) ) {
		$corner_alt = (string) $alt_meta;
	}
} elseif ( is_string( $corner_field ) && ! empty( $corner_field ) ) {
	$corner_url = $corner_field;
}

$corner_link = function_exists( 'get_field' ) ? get_field( 'mas_corner_link', 'option' ) : '';
if ( empty( $corner_link ) ) {
	$corner_link = '#';
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- ==========================================================================
     Header & Navigation Bar
     ========================================================================== -->
<header class="site-header sticky-top">
	<div class="header-container py-2">
		<nav class="navbar navbar-expand-lg navbar-light p-0">
			<!-- Logo -->
			<a class="navbar-brand py-1 order-1" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<?php if ( ! empty( $logo_url ) ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $logo_alt ); ?>" class="navbar-brand-logo">
				<?php else : ?>
					<span class="navbar-brand-text fs-4 fw-bold text-dark"><?php bloginfo( 'name' ); ?></span>
				<?php endif; ?>
			</a>

			<!-- Right Side Brand Logo: Ma's Christmas Corner (Dynamic ACF Logo & Link) -->
			<?php if ( ! empty( $corner_url ) ) : ?>
				<div class="mas-corner-wrap order-2 order-lg-3 ms-auto me-2 me-sm-3 me-lg-0">
					<a href="<?php echo esc_url( (string) $corner_link ); ?>" class="mas-corner-link" title="<?php echo esc_attr( $corner_alt ); ?>">
						<img src="<?php echo esc_url( $corner_url ); ?>" alt="<?php echo esc_attr( $corner_alt ); ?>" class="mas-corner-badge">
					</a>
				</div>
			<?php endif; ?>

			<!-- Mobile Toggle Button -->
			<button class="navbar-toggler border-0 order-3 order-lg-4<?php echo empty( $corner_url ) ? ' ms-auto' : ''; ?>" type="button" data-bs-toggle="offcanvas" data-bs-target="#mainNavbar"
				aria-controls="mainNavbar" aria-label="<?php esc_attr_e( 'Toggle navigation', 'xmastree-theme' ); ?>">
				<span class="navbar-toggler-icon"></span>
			</button>

			<!-- Navbar Links (Dynamic WordPress Navigation Menu / Right Offcanvas on Mobile) -->
			<span id="mainNavbarAnchor" class="d-none" aria-hidden="true"></span>
			<div class="offcanvas offcanvas-end mobile-offcanvas-nav flex-lg-row align-items-lg-center justify-content-lg-center order-4 order-lg-2" tabindex="-1" id="mainNavbar" aria-labelledby="mainNavbarLabel">
				<div class="offcanvas-header">
					<?php if ( ! empty( $logo_url ) ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $logo_alt ); ?>" class="offcanvas-brand-logo" id="mainNavbarLabel">
					<?php else : ?>
						<span class="offcanvas-title" id="mainNavbarLabel"><?php bloginfo( 'name' ); ?></span>
					<?php endif; ?>
					<button type="button" class="btn-close offcanvas-close-btn" data-bs-dismiss="offcanvas" data-bs-target="#mainNavbar" aria-label="<?php esc_attr_e( 'Close navigation', 'xmastree-theme' ); ?>"></button>
				</div>
				<div class="offcanvas-body">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'primary-menu',
							'container'      => false,
							'menu_class'     => 'navbar-nav main-nav mb-2 mb-lg-0 text-uppercase',
							'fallback_cb'    => false,
							'depth'          => 2,
						)
					);
					?>
				</div>
			</div>
		</nav>
	</div>
</header>
