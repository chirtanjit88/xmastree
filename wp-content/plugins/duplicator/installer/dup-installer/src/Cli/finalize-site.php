<?php

/**
 * Finalization of the installed site, run by CliFinalizer in a PHP process of its own: it loads the site in the admin,
 * with the request data of the environment variable DUPX_CLI_FINALIZE, runs the actions of the first administrator
 * login and prints their result on the last line. The installer and the plugin have classes with the same names, so
 * the installer process can't load the site.
 *
 * @package   Duplicator\Installer
 * @copyright (c) 2026, Snap Creek LLC
 */

// phpcs:disable PSR1.Files.SideEffects -- a script run by itself, like the tests script of the final tests

use Duplicator\Core\MigrationMng;

// Never run from the web: the folder of the installer is reachable from it
if (PHP_SAPI !== 'cli') {
    exit;
}

$data    = json_decode((string) getenv('DUPX_CLI_FINALIZE'), true);
$_SERVER = array_merge($_SERVER, $data['server']);
define('WP_ADMIN', true);
require $data['wpLoad'];

try {
    $result = [
        'success' => true,
        'files'   => MigrationMng::finalizeCliInstall(),
        'stored'  => array_keys(MigrationMng::getStoredMigrationLists()),
    ];
} catch (Throwable $e) {
    $result = [
        'success' => false,
        'error'   => $e->getMessage(),
    ];
}
// The site can print its own output: the result is the last line
echo "\n" . $data['resultPrefix'] . json_encode($result) . "\n";
