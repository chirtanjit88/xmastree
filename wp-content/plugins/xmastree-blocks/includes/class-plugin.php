<?php
/**
 * Main Plugin Orchestrator Class
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
 * Singleton class that coordinates initialization, class loading, block categories,
 * and component lifecycle.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Asset management component.
	 *
	 * @var Assets
	 */
	public Assets $assets;

	/**
	 * Block discovery and loader component.
	 *
	 * @var Block_Loader
	 */
	public Block_Loader $block_loader;

	/**
	 * Custom Events CPT and taxonomy component.
	 *
	 * @var Events
	 */
	public Events $events;

	/**
	 * Admin component.
	 *
	 * @var Admin
	 */
	public Admin $admin;

	/**
	 * Prevent duplicate initialization flag.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton pattern.
	 */
	private function __construct() {
		$this->assets       = new Assets();
		$this->events       = new Events();
		$this->block_loader = new Block_Loader();
		$this->admin        = new Admin( $this->block_loader );
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @throws \Exception When attempting to unserialize singleton.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Initialize the plugin components and WordPress hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;

		// Register hooks.
		add_action( 'init', array( $this, 'load_textdomain' ), 5 );
		add_filter( 'block_categories_all', array( $this, 'register_block_category' ), 10, 2 );

		// Boot components.
		$this->assets->init();
		$this->events->init();
		$this->block_loader->init();

		if ( is_admin() ) {
			$this->admin->init();
		}
	}

	/**
	 * Load plugin translation domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'xmastree-blocks',
			false,
			dirname( plugin_basename( XMASTREE_BLOCKS_FILE ) ) . '/languages'
		);
	}

	/**
	 * Register dedicated custom Gutenberg block category for XmasTree blocks.
	 *
	 * @param array<int, array<string, mixed>> $categories Existing block categories.
	 * @param \WP_Block_Editor_Context|null    $context    Current block editor context.
	 * @return array<int, array<string, mixed>> Filtered categories.
	 */
	public function register_block_category( array $categories, ?\WP_Block_Editor_Context $context = null ): array {
		// Prepend custom category to ensure prominent placement in the block inserter.
		array_unshift(
			$categories,
			array(
				'slug'  => 'xmastree-blocks',
				'title' => esc_html__( 'XmasTree Blocks', 'xmastree-blocks' ),
				'icon'  => 'palmtree',
			)
		);

		return $categories;
	}

	/**
	 * Plugin activation lifecycle callback.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Verify minimum PHP requirement.
		if ( version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
			deactivate_plugins( plugin_basename( XMASTREE_BLOCKS_FILE ) );
			wp_die(
				esc_html__( 'XmasTree Custom Blocks requires PHP 8.1 or higher.', 'xmastree-blocks' ),
				esc_html__( 'Plugin Activation Error', 'xmastree-blocks' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Plugin deactivation lifecycle callback.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Clean up any transient cache if necessary.
	}
}
