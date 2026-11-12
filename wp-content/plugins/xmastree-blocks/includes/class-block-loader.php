<?php
/**
 * Automated Block Discovery and Registration Engine
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
 * Discovers future block directories, validates configurations, loads programmatic
 * ACF fields, and registers blocks using the modern WordPress / ACF block.json API.
 */
class Block_Loader {

	/**
	 * Path to the blocks directory.
	 *
	 * @var string
	 */
	private string $blocks_dir;

	/**
	 * URL to the blocks directory.
	 *
	 * @var string
	 */
	private string $blocks_url;

	/**
	 * Discovered block directories.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $discovered_blocks = array();

	/**
	 * Successfully registered blocks.
	 *
	 * @var array<string, \WP_Block_Type>
	 */
	private array $registered_blocks = array();

	/**
	 * Blocks that failed validation or registration.
	 *
	 * @var array<string, string>
	 */
	private array $failed_blocks = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->blocks_dir = xmastree_blocks_get_blocks_dir();
		$this->blocks_url = xmastree_blocks_get_blocks_url();
	}

	/**
	 * Initialize the block registration hook.
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook late in 'init' to ensure ACF and all custom post types/taxonomies are ready.
		add_action( 'init', array( $this, 'register_blocks' ), 20 );
	}

	/**
	 * Scan the blocks directory and register all discovered Gutenberg blocks.
	 *
	 * @return void
	 */
	public function register_blocks(): void {
		// Check whether ACF Blocks API is available.
		if ( ! xmastree_blocks_is_acf_blocks_supported() ) {
			xmastree_blocks_log( 'ACF Blocks API not detected. Block registration skipped.', 'warning' );
			return;
		}

		// If blocks directory does not exist yet, exit silently without error.
		if ( ! is_dir( $this->blocks_dir ) || ! is_readable( $this->blocks_dir ) ) {
			xmastree_blocks_log( 'Blocks directory not found or unreadable: ' . $this->blocks_dir, 'info' );
			return;
		}

		$entries = scandir( $this->blocks_dir );
		if ( false === $entries ) {
			xmastree_blocks_log( 'Failed to read blocks directory: ' . $this->blocks_dir, 'error' );
			return;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$block_path = trailingslashit( $this->blocks_dir ) . $entry;

			if ( ! is_dir( $block_path ) ) {
				continue;
			}

			$block_meta = $this->validate_block( $block_path, $entry );

			if ( null !== $block_meta ) {
				$this->discovered_blocks[ $entry ] = $block_meta;
				$this->register_single_block( $block_path, $entry, $block_meta );
			}
		}
	}

	/**
	 * Validate a block directory and its block.json configuration.
	 *
	 * @param string $block_path Full directory path for the block.
	 * @param string $slug       Directory name / slug.
	 * @return array<string, mixed>|null Block metadata array if valid, null on failure.
	 */
	public function validate_block( string $block_path, string $slug ): ?array {
		$json_file = trailingslashit( $block_path ) . 'block.json';

		if ( ! file_exists( $json_file ) || ! is_readable( $json_file ) ) {
			$this->failed_blocks[ $slug ] = 'Missing or unreadable block.json';
			xmastree_blocks_log( sprintf( 'Block [%s] is missing block.json file.', $slug ), 'debug' );
			return null;
		}

		$json_content = file_get_contents( $json_file );
		if ( false === $json_content || '' === trim( $json_content ) ) {
			$this->failed_blocks[ $slug ] = 'Empty block.json';
			xmastree_blocks_log( sprintf( 'Block [%s] block.json is empty.', $slug ), 'warning' );
			return null;
		}

		$data = json_decode( $json_content, true );
		if ( ! is_array( $data ) ) {
			$this->failed_blocks[ $slug ] = 'Malformed JSON in block.json: ' . json_last_error_msg();
			xmastree_blocks_log( sprintf( 'Block [%s] has invalid JSON: %s', $slug, json_last_error_msg() ), 'error' );
			return null;
		}

		if ( empty( $data['name'] ) || ! is_string( $data['name'] ) ) {
			$this->failed_blocks[ $slug ] = 'block.json missing required "name" property';
			xmastree_blocks_log( sprintf( 'Block [%s] missing "name" in block.json.', $slug ), 'warning' );
			return null;
		}

		return $data;
	}

	/**
	 * Register an individual validated block with WordPress and ACF.
	 *
	 * @param string               $block_path Full filesystem path to the block directory.
	 * @param string               $slug       Block folder slug.
	 * @param array<string, mixed> $block_meta Decoded block.json data.
	 * @return bool True if registered successfully, false otherwise.
	 */
	public function register_single_block( string $block_path, string $slug, array $block_meta ): bool {
		$block_name = $block_meta['name'];

		// Prevent duplicate registration.
		if ( \WP_Block_Type_Registry::get_instance()->is_registered( $block_name ) ) {
			xmastree_blocks_log( sprintf( 'Block [%s] is already registered. Skipping.', $block_name ), 'notice' );
			return false;
		}

		// 1. Programmatic ACF field registration (if fields.php exists).
		$this->load_block_fields( $block_path, $block_name );

		// 2. Validate PHP rendering template if defined.
		$this->validate_render_template( $block_path, $block_meta );

		// 3. Register the block using core WordPress register_block_type.
		// ACF Pro 6+ automatically hooks into register_block_type() to handle ACF blocks.
		$registered = register_block_type( $block_path );

		if ( $registered instanceof \WP_Block_Type ) {
			$this->registered_blocks[ $block_name ] = $registered;
			xmastree_blocks_log( sprintf( 'Successfully registered block: %s (%s)', $block_name, $slug ), 'info' );
			return true;
		}

		$this->failed_blocks[ $slug ] = 'register_block_type() failed to return a WP_Block_Type instance';
		xmastree_blocks_log( sprintf( 'Failed to register block: %s', $block_name ), 'error' );
		return false;
	}

	/**
	 * Load programmatic ACF field groups from a block's fields.php file if present.
	 *
	 * @param string $block_path Directory path of the block.
	 * @param string $block_name Name of the block (e.g. 'acf/hero-banner').
	 * @return void
	 */
	private function load_block_fields( string $block_path, string $block_name ): void {
		$fields_file = trailingslashit( $block_path ) . 'fields.php';

		if ( ! file_exists( $fields_file ) || ! is_readable( $fields_file ) ) {
			return;
		}

		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		$field_group = include $fields_file;

		if ( is_array( $field_group ) && ! empty( $field_group['key'] ) && ! empty( $field_group['fields'] ) ) {
			// Ensure location matches this block if not explicitly defined.
			if ( empty( $field_group['location'] ) ) {
				$field_group['location'] = array(
					array(
						array(
							'param'    => 'block',
							'operator' => '==',
							'value'    => $block_name,
						),
					),
				);
			}

			acf_add_local_field_group( $field_group );
			xmastree_blocks_log( sprintf( 'Loaded programmatic field group for [%s]', $block_name ), 'debug' );
		}
	}

	/**
	 * Validate that the specified renderTemplate exists if defined in block.json.
	 *
	 * @param string               $block_path Directory path of the block.
	 * @param array<string, mixed> $block_meta Decoded block metadata.
	 * @return void
	 */
	private function validate_render_template( string $block_path, array $block_meta ): void {
		if ( isset( $block_meta['acf']['renderTemplate'] ) && is_string( $block_meta['acf']['renderTemplate'] ) ) {
			$template_file = trailingslashit( $block_path ) . ltrim( $block_meta['acf']['renderTemplate'], '/\\' );
			if ( ! file_exists( $template_file ) ) {
				xmastree_blocks_log(
					sprintf(
						'Render template "%s" specified in block.json for block "%s" does not exist.',
						$block_meta['acf']['renderTemplate'],
						$block_meta['name']
					),
					'warning'
				);
			}
		}
	}

	/**
	 * Check whether any blocks were discovered.
	 *
	 * @return bool True if at least one valid block directory was discovered.
	 */
	public function has_blocks(): bool {
		return ! empty( $this->discovered_blocks );
	}

	/**
	 * Get the list of all discovered blocks.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_discovered_blocks(): array {
		return $this->discovered_blocks;
	}

	/**
	 * Get the list of successfully registered block types.
	 *
	 * @return array<string, \WP_Block_Type>
	 */
	public function get_registered_blocks(): array {
		return $this->registered_blocks;
	}

	/**
	 * Get the list of blocks that failed discovery or registration.
	 *
	 * @return array<string, string>
	 */
	public function get_failed_blocks(): array {
		return $this->failed_blocks;
	}

	/**
	 * Get the blocks directory path.
	 *
	 * @return string
	 */
	public function get_blocks_dir(): string {
		return $this->blocks_dir;
	}
}

