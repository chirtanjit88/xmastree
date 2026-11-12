<?php
/**
 * XmasTree Custom Blocks Uninstall Procedure
 *
 * Triggered when the plugin is uninstalled via the WordPress admin.
 *
 * @package XmasTree_Blocks
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Clean up any transient diagnostics or caches.
delete_transient( 'xmastree_blocks_diagnostics' );

