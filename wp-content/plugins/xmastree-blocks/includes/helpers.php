<?php
/**
 * Helper functions for XmasTree Custom Blocks
 *
 * @package XmasTree_Blocks
 * @since 1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'xmastree_blocks_is_acf_active' ) ) {
	/**
	 * Check if Advanced Custom Fields (ACF) or Secure Custom Fields (SCF) is active.
	 *
	 * @return bool True if ACF is active, false otherwise.
	 */
	function xmastree_blocks_is_acf_active(): bool {
		return class_exists( 'ACF' ) || function_exists( 'acf' );
	}
}

if ( ! function_exists( 'xmastree_blocks_is_acf_blocks_supported' ) ) {
	/**
	 * Check if ACF Blocks capability is available in the current environment.
	 *
	 * @return bool True if ACF Blocks can be registered, false otherwise.
	 */
	function xmastree_blocks_is_acf_blocks_supported(): bool {
		return function_exists( 'acf_register_block_type' );
	}
}

if ( ! function_exists( 'xmastree_blocks_get_acf_version' ) ) {
	/**
	 * Get the installed ACF or SCF version string.
	 *
	 * @return string|null Version string or null if unavailable.
	 */
	function xmastree_blocks_get_acf_version(): ?string {
		if ( defined( 'ACF_VERSION' ) ) {
			return (string) ACF_VERSION;
		}

		if ( class_exists( 'ACF' ) && function_exists( 'acf' ) ) {
			$acf_instance = acf();
			if ( isset( $acf_instance->version ) ) {
				return (string) $acf_instance->version;
			}
		}

		return null;
	}
}

if ( ! function_exists( 'xmastree_blocks_get_dir' ) ) {
	/**
	 * Get the absolute filesystem path to the plugin directory or a subpath.
	 *
	 * @param string $subpath Optional relative subpath.
	 * @return string Full filesystem path.
	 */
	function xmastree_blocks_get_dir( string $subpath = '' ): string {
		$base = defined( 'XMASTREE_BLOCKS_DIR' ) ? XMASTREE_BLOCKS_DIR : plugin_dir_path( dirname( __DIR__ ) . '/xmastree-blocks.php' );
		$subpath = ltrim( $subpath, '/\\' );
		return $subpath !== '' ? $base . $subpath : $base;
	}
}

if ( ! function_exists( 'xmastree_blocks_get_url' ) ) {
	/**
	 * Get the URL to the plugin directory or a subpath.
	 *
	 * @param string $subpath Optional relative subpath.
	 * @return string Full URL.
	 */
	function xmastree_blocks_get_url( string $subpath = '' ): string {
		$base = defined( 'XMASTREE_BLOCKS_URL' ) ? XMASTREE_BLOCKS_URL : plugin_dir_url( dirname( __DIR__ ) . '/xmastree-blocks.php' );
		$subpath = ltrim( $subpath, '/\\' );
		return $subpath !== '' ? $base . $subpath : $base;
	}
}

if ( ! function_exists( 'xmastree_blocks_get_blocks_dir' ) ) {
	/**
	 * Get the filesystem path where future custom blocks will reside.
	 *
	 * @return string Blocks directory path with trailing slash.
	 */
	function xmastree_blocks_get_blocks_dir(): string {
		return trailingslashit( xmastree_blocks_get_dir( 'blocks' ) );
	}
}

if ( ! function_exists( 'xmastree_blocks_get_blocks_url' ) ) {
	/**
	 * Get the URL pointing to the blocks directory.
	 *
	 * @return string Blocks URL with trailing slash.
	 */
	function xmastree_blocks_get_blocks_url(): string {
		return trailingslashit( xmastree_blocks_get_url( 'blocks' ) );
	}
}

if ( ! function_exists( 'xmastree_blocks_is_valid_block_dir' ) ) {
	/**
	 * Validate if a given path is a valid future block directory.
	 * A valid block directory must exist and contain a valid block.json file.
	 *
	 * @param string $dir_path Filesystem path to validate.
	 * @return bool True if directory is a valid block candidate, false otherwise.
	 */
	function xmastree_blocks_is_valid_block_dir( string $dir_path ): bool {
		if ( ! is_dir( $dir_path ) || ! is_readable( $dir_path ) ) {
			return false;
		}

		$block_json = trailingslashit( $dir_path ) . 'block.json';
		return file_exists( $block_json ) && is_readable( $block_json );
	}
}

if ( ! function_exists( 'xmastree_blocks_log' ) ) {
	/**
	 * Safe diagnostic logging for the plugin when WP_DEBUG is active.
	 *
	 * @param string $message Diagnostic message to log.
	 * @param string $level   Log level ('info', 'warning', 'error', 'debug').
	 * @return void
	 */
	function xmastree_blocks_log( string $message, string $level = 'info' ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$prefix = sprintf( '[XmasTree Blocks] [%s]: ', strtoupper( $level ) );
			error_log( $prefix . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}

