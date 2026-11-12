<?php
/**
 * Plugin Name:       XmasTree Custom Blocks
 * Plugin URI:        https://example.com/xmastree-blocks
 * Description:       Centralized foundation and automated discovery engine for custom Gutenberg and ACF blocks for the XmasTree website.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Christmas Tree Farm Developer Team
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       xmastree-blocks
 * Domain Path:       /languages
 *
 * @package XmasTree_Blocks
 */

declare(strict_types=1);

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin Version constant.
 */
if ( ! defined( 'XMASTREE_BLOCKS_VERSION' ) ) {
	define( 'XMASTREE_BLOCKS_VERSION', '1.0.0' );
}

/**
 * Main Plugin File path.
 */
if ( ! defined( 'XMASTREE_BLOCKS_FILE' ) ) {
	define( 'XMASTREE_BLOCKS_FILE', __FILE__ );
}

/**
 * Plugin Directory path.
 */
if ( ! defined( 'XMASTREE_BLOCKS_DIR' ) ) {
	define( 'XMASTREE_BLOCKS_DIR', plugin_dir_path( __FILE__ ) );
}

/**
 * Plugin Directory URL.
 */
if ( ! defined( 'XMASTREE_BLOCKS_URL' ) ) {
	define( 'XMASTREE_BLOCKS_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Plugin Basename.
 */
if ( ! defined( 'XMASTREE_BLOCKS_BASENAME' ) ) {
	define( 'XMASTREE_BLOCKS_BASENAME', plugin_basename( __FILE__ ) );
}

// -----------------------------------------------------------------------------
// Load Helpers and Component Classes
// -----------------------------------------------------------------------------
require_once XMASTREE_BLOCKS_DIR . 'includes/helpers.php';
require_once XMASTREE_BLOCKS_DIR . 'includes/class-assets.php';
require_once XMASTREE_BLOCKS_DIR . 'includes/class-events.php';
require_once XMASTREE_BLOCKS_DIR . 'includes/class-block-loader.php';
require_once XMASTREE_BLOCKS_DIR . 'includes/class-admin.php';
require_once XMASTREE_BLOCKS_DIR . 'includes/class-plugin.php';

// -----------------------------------------------------------------------------
// Lifecycle Hooks
// -----------------------------------------------------------------------------
register_activation_hook( __FILE__, array( 'XmasTree\\Blocks\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'XmasTree\\Blocks\\Plugin', 'deactivate' ) );

// -----------------------------------------------------------------------------
// Bootstrap Plugin on plugins_loaded
// -----------------------------------------------------------------------------
add_action(
	'plugins_loaded',
	static function (): void {
		XmasTree\Blocks\Plugin::get_instance()->init();
	}
);

