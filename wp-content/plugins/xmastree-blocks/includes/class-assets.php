<?php
/**
 * Centralized Asset Management Class
 *
 * @package XmasTree_Blocks
 * @since 1.0.0
 */

declare(strict_types=1);

namespace XmasTree\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles asset registration, block-specific styles/scripts, and conditional loading.
 */
class Assets {

	/**
	 * Registry of block styles.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $registered_styles = array();

	/**
	 * Registry of block scripts.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $registered_scripts = array();

	/**
	 * Track enqueued handles to prevent duplicates.
	 *
	 * @var array<string, bool>
	 */
	private array $enqueued_handles = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Initialization handled via init() method.
	}

	/**
	 * Hook asset registration into WordPress.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ), 10 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ), 10 );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_shared_block_assets' ), 10 );
	}

	/**
	 * Pre-register shared plugin assets on frontend without loading them prematurely.
	 *
	 * @return void
	 */
	public function register_frontend_assets(): void {
		// Future shared block utilities (e.g. carousels, modals) will be registered here.
		// No unnecessary styles or scripts are enqueued at this stage.
	}

	/**
	 * Enqueue assets specific to the Gutenberg block editor environment.
	 *
	 * @return void
	 */
	public function enqueue_editor_assets(): void {
		// Editor-only adjustments and block inserter UI enhancements will be enqueued here.
		// Only enqueued inside wp-admin block editor screens.
	}

	/**
	 * Enqueue shared block assets rendered on both frontend and editor.
	 *
	 * @return void
	 */
	public function enqueue_shared_block_assets(): void {
		// Shared layout and typography constraints for custom blocks.
	}

	/**
	 * Register a block-specific stylesheet in WordPress.
	 *
	 * @param string      $handle Unique handle for the style.
	 * @param string      $src    Source URL.
	 * @param array<string> $deps Array of dependencies.
	 * @param string|null $ver    Version string (defaults to plugin version).
	 * @param string      $media  Media type.
	 * @return bool True if registered successfully.
	 */
	public function register_block_style(
		string $handle,
		string $src,
		array $deps = array(),
		?string $ver = null,
		string $media = 'all'
	): bool {
		$version = $ver ?? ( defined( 'XMASTREE_BLOCKS_VERSION' ) ? XMASTREE_BLOCKS_VERSION : '1.0.0' );

		$this->registered_styles[ $handle ] = array(
			'src'   => $src,
			'deps'  => $deps,
			'ver'   => $version,
			'media' => $media,
		);

		return wp_register_style( $handle, $src, $deps, $version, $media );
	}

	/**
	 * Register a block-specific script in WordPress.
	 *
	 * @param string        $handle    Unique handle for the script.
	 * @param string        $src       Source URL.
	 * @param array<string> $deps      Array of dependencies.
	 * @param string|null   $ver       Version string (defaults to plugin version).
	 * @param bool          $in_footer Whether to enqueue in footer.
	 * @return bool True if registered successfully.
	 */
	public function register_block_script(
		string $handle,
		string $src,
		array $deps = array(),
		?string $ver = null,
		bool $in_footer = true
	): bool {
		$version = $ver ?? ( defined( 'XMASTREE_BLOCKS_VERSION' ) ? XMASTREE_BLOCKS_VERSION : '1.0.0' );

		$this->registered_scripts[ $handle ] = array(
			'src'       => $src,
			'deps'      => $deps,
			'ver'       => $version,
			'in_footer' => $in_footer,
		);

		return wp_register_script( $handle, $src, $deps, $version, $in_footer );
	}

	/**
	 * Helper to check whether a specific block is present on the current post/page.
	 *
	 * @param string $block_name Full block name including namespace (e.g. 'acf/hero-banner').
	 * @return bool True if the block is present in post content.
	 */
	public function is_block_on_page( string $block_name ): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return has_block( $block_name, $post->post_content );
	}

	/**
	 * Get the list of all registered custom block styles.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_registered_styles(): array {
		return $this->registered_styles;
	}

	/**
	 * Get the list of all registered custom block scripts.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_registered_scripts(): array {
		return $this->registered_scripts;
	}
}

