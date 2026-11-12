<?php
/**
 * Block Template: Custom Container
 *
 * Wraps nested Gutenberg inner blocks inside a selectable theme container
 * (.container, .container-fluid, .stand-container) or custom pixel max-width.
 * Native Gutenberg block controlled via block attributes (zero SCF fields).
 *
 * @package XmasTree_Blocks
 * @param array     $attributes Block attributes from block.json.
 * @param string    $content    Rendered InnerBlocks HTML content.
 * @param \WP_Block $block      Block instance.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( '' === trim( (string) $content ) ) {
	return;
}

$allowed_containers = array( 'container', 'container-fluid', 'stand-container', 'custom' );
$container_type     = isset( $attributes['containerType'] ) && is_string( $attributes['containerType'] )
	? $attributes['containerType']
	: 'container';

if ( ! in_array( $container_type, $allowed_containers, true ) ) {
	$container_type = 'container';
}

$allowed_tags = array( 'div', 'section' );
$tag_name     = isset( $attributes['tagName'] ) && is_string( $attributes['tagName'] )
	? $attributes['tagName']
	: 'div';

if ( ! in_array( $tag_name, $allowed_tags, true ) ) {
	$tag_name = 'div';
}

$inner_style_attr = '';

if ( 'custom' === $container_type ) {
	$raw_max_width    = isset( $attributes['customMaxWidth'] ) ? (int) $attributes['customMaxWidth'] : 1200;
	$safe_max_width   = $raw_max_width > 0 ? max( 200, min( 2400, $raw_max_width ) ) : 1200;
	$inner_class      = 'xmastree-custom-container xmastree-container-inner';
	$inner_style_attr = ' style="' . esc_attr( 'max-width: ' . $safe_max_width . 'px;' ) . '"';
} else {
	$inner_class = $container_type . ' xmastree-container-inner';
}

$wrapper_attributes = function_exists( 'get_block_wrapper_attributes' )
	? get_block_wrapper_attributes()
	: 'class="wp-block-xmastree-container"';
?>
<<?php echo $tag_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Whitelisted tag name ?> <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes() ?>>
	<div class="<?php echo esc_attr( $inner_class ); ?>"<?php echo $inner_style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above ?>>
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered InnerBlocks HTML ?>
	</div>
</<?php echo $tag_name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Whitelisted tag name ?>>

