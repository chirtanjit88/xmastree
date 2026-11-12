<?php
/**
 * Bootstrap Icons integration for ACF / Secure Custom Fields (SCF) Icon Picker.
 *
 * Automatically registers a "Bootstrap Icons" tab in the ACF Icon Picker,
 * loads 2,000+ vector icons with search support, enqueues admin assets,
 * and handles value normalization.
 *
 * @package XmasTree_Theme
 * @since 1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Retrieve the list of all Bootstrap Icons available in the theme.
 *
 * Parses assets/css/bootstrap-icons.min.css once and caches the result
 * in a transient for fast retrieval.
 *
 * @return array<int, array{key: string, label: string, url: string}>
 */
function xmastree_get_bootstrap_icons_list(): array {
	$cached_icons = get_transient( 'xmastree_bootstrap_icons_list' );
	if ( is_array( $cached_icons ) && ! empty( $cached_icons ) ) {
		return $cached_icons;
	}

	$css_file = get_template_directory() . '/assets/css/bootstrap-icons.min.css';
	if ( ! file_exists( $css_file ) ) {
		return array();
	}

	$css_content = file_get_contents( $css_file );
	if ( false === $css_content ) {
		return array();
	}

	preg_match_all( '/\.bi-([a-z0-9-]+)::before/', $css_content, $matches );
	if ( empty( $matches[1] ) ) {
		return array();
	}

	$icon_names = array_unique( $matches[1] );
	sort( $icon_names );

	$icons     = array();
	$blank_svg = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';

	// Proper brand name mappings for accessible search and display.
	$brand_names = array(
		'youtube'    => 'YouTube',
		'facebook'   => 'Facebook',
		'instagram'  => 'Instagram',
		'whatsapp'   => 'WhatsApp',
		'tiktok'     => 'TikTok',
		'twitter'    => 'Twitter',
		'twitter-x'  => 'Twitter (X)',
		'linkedin'   => 'LinkedIn',
		'github'     => 'GitHub',
		'pinterest'  => 'Pinterest',
		'snapchat'   => 'Snapchat',
		'spotify'    => 'Spotify',
		'telegram'   => 'Telegram',
		'discord'    => 'Discord',
		'reddit'     => 'Reddit',
		'google'     => 'Google',
		'apple'      => 'Apple',
		'microsoft'  => 'Microsoft',
		'wordpress'  => 'WordPress',
		'yelp'       => 'Yelp',
		'dribbble'   => 'Dribbble',
		'behance'    => 'Behance',
		'twitch'     => 'Twitch',
		'slack'      => 'Slack',
		'medium'     => 'Medium',
		'paypal'     => 'PayPal',
		'stripe'     => 'Stripe',
	);

	foreach ( $icon_names as $name ) {
		if ( isset( $brand_names[ $name ] ) ) {
			$label = $brand_names[ $name ];
		} else {
			$label = ucwords( str_replace( '-', ' ', (string) $name ) );
		}

		$icons[] = array(
			'key'   => 'bi-' . $name,
			'label' => $label,
			'url'   => $blank_svg,
		);
	}

	// Cache for 30 days.
	set_transient( 'xmastree_bootstrap_icons_list', $icons, DAY_IN_SECONDS * 30 );

	return $icons;
}

/**
 * Register "Bootstrap Icons" tab in ACF Icon Picker.
 *
 * @param array<string, string> $tabs Current tabs.
 * @return array<string, string> Updated tabs with Bootstrap Icons at the top.
 */
function xmastree_add_bootstrap_icons_tab( array $tabs ): array {
	$new_tabs = array(
		'bootstrap_icons' => esc_html__( 'Bootstrap Icons', 'xmastree-theme' ),
	);

	foreach ( $tabs as $key => $label ) {
		$new_tabs[ $key ] = $label;
	}

	return $new_tabs;
}
add_filter( 'acf/fields/icon_picker/tabs', 'xmastree_add_bootstrap_icons_tab' );

/**
 * Supply the list of Bootstrap Icons to the ACF Icon Picker tab.
 *
 * @param array<mixed> $icons Current icons array.
 * @param array<mixed> $field ACF field array.
 * @return array<int, array{key: string, label: string, url: string}>
 */
function xmastree_populate_bootstrap_icons( array $icons, array $field ): array {
	return xmastree_get_bootstrap_icons_list();
}
add_filter( 'acf/fields/icon_picker/bootstrap_icons/icons', 'xmastree_populate_bootstrap_icons', 10, 2 );

/**
 * Ensure the "bootstrap_icons" tab is enabled on existing and newly loaded Icon Picker fields.
 *
 * @param array<string, mixed> $field ACF field array.
 * @return array<string, mixed>
 */
function xmastree_enable_bootstrap_icons_on_field( array $field ): array {
	if ( isset( $field['tabs'] ) && is_array( $field['tabs'] ) ) {
		if ( ! in_array( 'bootstrap_icons', $field['tabs'], true ) ) {
			array_unshift( $field['tabs'], 'bootstrap_icons' );
		}
	}
	return $field;
}
add_filter( 'acf/load_field/type=icon_picker', 'xmastree_enable_bootstrap_icons_on_field' );

/**
 * Normalize the field value structure before rendering to prevent SCF PHP warnings.
 *
 * @param array<string, mixed> $field ACF field array.
 * @return array<string, mixed>
 */
function xmastree_prepare_icon_picker_field( array $field ): array {
	if ( ! is_array( $field['value'] ?? null ) ) {
		$raw_val         = $field['value'] ?? null;
		$is_valid_str    = is_string( $raw_val ) && '' !== $raw_val;
		$field['value']  = array(
			'type'  => $is_valid_str ? 'bootstrap_icons' : null,
			'value' => $is_valid_str ? $raw_val : null,
		);
	}
	return $field;
}
add_filter( 'acf/prepare_field/type=icon_picker', 'xmastree_prepare_icon_picker_field' );

/**
 * Enqueue Bootstrap Icons CSS and styling for the ACF Icon Picker in WP Admin.
 *
 * @return void
 */
function xmastree_admin_icon_picker_assets(): void {
	wp_enqueue_style(
		'bootstrap-icons',
		get_template_directory_uri() . '/assets/css/bootstrap-icons.min.css',
		array(),
		defined( 'XMASTREE_THEME_VERSION' ) ? XMASTREE_THEME_VERSION : '1.11.3'
	);

	$custom_admin_css = '
		/* ACF Icon Picker - Bootstrap Icons Tab Grid */
		.acf-icon-picker-tabs[data-tab="bootstrap_icons"] .acf-icon-list {
			display: flex;
			flex-wrap: wrap;
			gap: 6px;
			max-height: 280px;
			overflow-y: auto;
			padding: 8px;
			background: #fafafa;
			border: 1px solid #dcdcde;
			border-radius: 4px;
		}
		.acf-icon-picker-tabs[data-tab="bootstrap_icons"] .acf-icon-picker-list-icon {
			background: #ffffff !important;
			display: inline-flex !important;
			align-items: center !important;
			justify-content: center !important;
			width: 36px !important;
			height: 36px !important;
			font-size: 18px !important;
			line-height: 1 !important;
			color: #3c434a !important;
			border: 1px solid #c3c4c7 !important;
			border-radius: 4px !important;
			cursor: pointer;
			transition: all 0.15s ease;
		}
		.acf-icon-picker-tabs[data-tab="bootstrap_icons"] .acf-icon-picker-list-icon:hover {
			border-color: #0783be !important;
			color: #0783be !important;
			background: #f0f6fc !important;
			transform: scale(1.08);
			z-index: 2;
		}
		.acf-icon-picker-tabs[data-tab="bootstrap_icons"] .acf-icon-picker-list-icon.active {
			border-color: #0783be !important;
			background: #ebf5fa !important;
			color: #0783be !important;
			box-shadow: 0 0 0 1px #0783be;
		}
		.acf-icon-picker-tabs[data-tab="bootstrap_icons"] .acf-icon-picker-list-icon::before {
			font-family: "bootstrap-icons" !important;
			font-style: normal;
			font-weight: normal !important;
			font-variant: normal;
			text-transform: none;
			line-height: 1;
		}
	';

	wp_add_inline_style( 'bootstrap-icons', $custom_admin_css );
}
add_action( 'admin_enqueue_scripts', 'xmastree_admin_icon_picker_assets' );

