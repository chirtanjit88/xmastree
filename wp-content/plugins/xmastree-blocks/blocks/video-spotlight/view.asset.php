<?php
/**
 * Asset dependencies and cache version for xmastree/video-spotlight popup script.
 *
 * @package XmasTree_Blocks
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$script_file = __DIR__ . '/view.js';
$version     = is_readable( $script_file ) ? (string) ( filemtime( $script_file ) ?: '1.0.0' ) : '1.0.0';

return array(
	'dependencies' => array(),
	'version'      => $version,
);

