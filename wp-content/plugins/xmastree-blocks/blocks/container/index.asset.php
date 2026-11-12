<?php
/**
 * Asset dependencies and version for the xmastree/container editor script.
 *
 * @package XmasTree_Blocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$script_file = __DIR__ . '/index.js';
$version     = is_readable( $script_file ) ? (string) ( filemtime( $script_file ) ?: '1.0.0' ) : '1.0.0';

return array(
	'dependencies' => array(
		'wp-block-editor',
		'wp-blocks',
		'wp-components',
		'wp-data',
		'wp-element',
		'wp-i18n',
	),
	'version'      => $version,
);

