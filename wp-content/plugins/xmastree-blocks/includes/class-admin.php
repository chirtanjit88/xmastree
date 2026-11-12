<?php
/**
 * Admin Diagnostics and Dependency Management
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
 * Handles admin menu integration, dependency notices, and diagnostic reporting.
 */
class Admin {

	/**
	 * Reference to the Block_Loader instance.
	 *
	 * @var Block_Loader
	 */
	private Block_Loader $block_loader;

	/**
	 * Constructor.
	 *
	 * @param Block_Loader $block_loader Block loader instance.
	 */
	public function __construct( Block_Loader $block_loader ) {
		$this->block_loader = $block_loader;
	}

	/**
	 * Hook administrative actions into WordPress.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_notices', array( $this, 'display_acf_notice' ) );
	}

	/**
	 * Display an administrative notice if ACF Pro / Blocks support is missing.
	 *
	 * @return void
	 */
	public function display_acf_notice(): void {
		if ( xmastree_blocks_is_acf_blocks_supported() ) {
			return;
		}

		// Only show to users with permission to install/activate plugins.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'XmasTree Custom Blocks:', 'xmastree-blocks' ); ?></strong>
				<?php esc_html_e( 'Advanced Custom Fields Pro (or Secure Custom Fields with Blocks capability) is required to register and render custom Gutenberg blocks. Please ensure ACF Pro is installed and active.', 'xmastree-blocks' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Register the administrative diagnostic page under Settings.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_options_page(
			esc_html__( 'XmasTree Blocks Diagnostics', 'xmastree-blocks' ),
			esc_html__( 'XmasTree Blocks', 'xmastree-blocks' ),
			'manage_options',
			'xmastree-blocks',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the administrative diagnostic dashboard.
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'xmastree-blocks' ) );
		}

		$acf_active     = xmastree_blocks_is_acf_active();
		$acf_blocks     = xmastree_blocks_is_acf_blocks_supported();
		$acf_version    = xmastree_blocks_get_acf_version() ?? esc_html__( 'Not detected', 'xmastree-blocks' );
		$blocks_dir     = $this->block_loader->get_blocks_dir();
		$blocks_dir_ok  = is_dir( $blocks_dir );
		$discovered     = count( $this->block_loader->get_discovered_blocks() );
		$registered     = count( $this->block_loader->get_registered_blocks() );
		$failed         = count( $this->block_loader->get_failed_blocks() );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'XmasTree Custom Blocks — Architecture Diagnostics', 'xmastree-blocks' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Centralized infrastructure status and block discovery diagnostics for custom Gutenberg blocks.', 'xmastree-blocks' ); ?>
			</p>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-top: 20px;">

				<!-- Card 1: ACF Dependency Status -->
				<div class="postbox" style="padding: 20px; margin-bottom: 0;">
					<h2 class="hndle" style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ccd0d4;">
						<?php esc_html_e( 'ACF Dependency Status', 'xmastree-blocks' ); ?>
					</h2>
					<table class="widefat striped" style="border: none;">
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'ACF / SCF Active:', 'xmastree-blocks' ); ?></strong></td>
								<td>
									<?php if ( $acf_active ) : ?>
										<span style="color: #46b450; font-weight: bold;">&#10004; <?php esc_html_e( 'Active', 'xmastree-blocks' ); ?></span>
									<?php else : ?>
										<span style="color: #dc3232; font-weight: bold;">&#10008; <?php esc_html_e( 'Inactive / Missing', 'xmastree-blocks' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'ACF Blocks API:', 'xmastree-blocks' ); ?></strong></td>
								<td>
									<?php if ( $acf_blocks ) : ?>
										<span style="color: #46b450; font-weight: bold;">&#10004; <?php esc_html_e( 'Available', 'xmastree-blocks' ); ?></span>
									<?php else : ?>
										<span style="color: #dc3232; font-weight: bold;">&#10008; <?php esc_html_e( 'Unavailable', 'xmastree-blocks' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Detected Version:', 'xmastree-blocks' ); ?></strong></td>
								<td><code><?php echo esc_html( $acf_version ); ?></code></td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Card 2: Block Discovery Status -->
				<div class="postbox" style="padding: 20px; margin-bottom: 0;">
					<h2 class="hndle" style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ccd0d4;">
						<?php esc_html_e( 'Block Discovery Status', 'xmastree-blocks' ); ?>
					</h2>
					<table class="widefat striped" style="border: none;">
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Blocks Directory:', 'xmastree-blocks' ); ?></strong></td>
								<td>
									<?php if ( $blocks_dir_ok ) : ?>
										<span style="color: #46b450; font-weight: bold;">&#10004; <?php esc_html_e( 'Present', 'xmastree-blocks' ); ?></span>
									<?php else : ?>
										<span style="color: #666; font-style: italic;"><?php esc_html_e( 'Not Created Yet (Expected at Stage 1)', 'xmastree-blocks' ); ?></span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Discovered Blocks:', 'xmastree-blocks' ); ?></strong></td>
								<td><strong><?php echo esc_html( (string) $discovered ); ?></strong></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Registered Blocks:', 'xmastree-blocks' ); ?></strong></td>
								<td><strong><?php echo esc_html( (string) $registered ); ?></strong></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Failed Blocks:', 'xmastree-blocks' ); ?></strong></td>
								<td><strong><?php echo esc_html( (string) $failed ); ?></strong></td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Card 3: System Environment -->
				<div class="postbox" style="padding: 20px; margin-bottom: 0;">
					<h2 class="hndle" style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ccd0d4;">
						<?php esc_html_e( 'Environment & Details', 'xmastree-blocks' ); ?>
					</h2>
					<table class="widefat striped" style="border: none;">
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Plugin Version:', 'xmastree-blocks' ); ?></strong></td>
								<td><code><?php echo esc_html( defined( 'XMASTREE_BLOCKS_VERSION' ) ? XMASTREE_BLOCKS_VERSION : '1.0.0' ); ?></code></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'WordPress Version:', 'xmastree-blocks' ); ?></strong></td>
								<td><code><?php echo esc_html( get_bloginfo( 'version' ) ); ?></code></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'PHP Version:', 'xmastree-blocks' ); ?></strong></td>
								<td><code><?php echo esc_html( PHP_VERSION ); ?></code></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'WP_DEBUG Mode:', 'xmastree-blocks' ); ?></strong></td>
								<td><?php echo ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? '<span style="color: #ff9800;">Enabled</span>' : 'Disabled'; ?></td>
							</tr>
						</tbody>
					</table>
				</div>

			</div>

			<!-- Card 4: Future Block Architecture Guide -->
			<div class="postbox" style="padding: 20px; margin-top: 20px;">
				<h2 class="hndle" style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #ccd0d4;">
					<?php esc_html_e( 'How to Add Custom Blocks in Future Phases', 'xmastree-blocks' ); ?>
				</h2>
				<p>
					<?php esc_html_e( 'When developing a new custom block, create a new subfolder inside the blocks directory. The plugin block loader will automatically discover and register it without touching the main plugin files:', 'xmastree-blocks' ); ?>
				</p>
				<pre style="background: #f0f0f1; padding: 15px; border-radius: 4px; overflow-x: auto;">
wp-content/plugins/xmastree-blocks/blocks/{block-slug}/
├── block.json       (Required: standard WordPress &amp; ACF block metadata)
├── render.php       (Optional: server-side PHP template for block frontend output)
├── fields.php       (Optional: programmatic ACF local field group definition array)
├── style.css        (Optional: block-specific frontend styling)
├── editor.css       (Optional: block-specific editor styling)
└── script.js        (Optional: block-specific front-end behavior)
				</pre>
			</div>
		</div>
		<?php
	}
}

