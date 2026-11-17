<?php

/**
 * Command line entry of installer.php
 *
 * @package   Duplicator\Installer
 * @copyright (c) 2026, Snap Creek LLC
 */

namespace Duplicator\Installer\Cli;

use Duplicator\Installer\Bootstrap\BootstrapRunner;
use Duplicator\Installer\Bootstrap\BootstrapUtils;
use Duplicator\Installer\Bootstrap\BootstrapView;
use Duplicator\Installer\Bootstrap\LogHandler;
use Exception;

/**
 * Runs installer.php from the command line: extracts the installer as the web bootstrap does, then the main installer
 * installs the site in the same process. The parameters come from the options and the --params file and stay in
 * memory; on a terminal the missing values are asked. Inlined in installer.php.
 */
class CliBootstrap
{
    /** @var string URL the request data are built from when --info runs without --url */
    const INFO_URL = 'http://localhost';
    /** @var string[] Folders where the plugin keeps the Backups of a site, the current one and the legacy ones */
    const BACKUPS_FOLDER_NAMES = [
        'duplicator-backups',
        'backups-dup-pro',
        'backups-dup-lite',
    ];

    /**
     * Run the installation
     *
     * @param string[] $argv        Command line arguments, the script name first
     * @param string   $packageHash Hash of the installer package
     *
     * @return int Exit status
     */
    public static function run(array $argv, string $packageHash): int
    {
        if (count($argv) <= 1) {
            CliEnvironment::out(self::usage());
            return CliEnvironment::EXIT_OK;
        }
        try {
            $options = CliEnvironment::parseOptions($argv);
            if ($options['format'] !== null && !in_array($options['format'], CliEnvironment::INFO_FORMATS, true)) {
                throw new Exception('--format takes ' . implode(' or ', CliEnvironment::INFO_FORMATS));
            }
        } catch (Exception $e) {
            CliEnvironment::error($e->getMessage());
            CliEnvironment::err(self::usage());
            return CliEnvironment::EXIT_USAGE;
        }
        if ($options['help']) {
            CliEnvironment::out(self::usage());
            return CliEnvironment::EXIT_OK;
        }

        if (($locationError = self::getLocationError(__FILE__, $packageHash)) !== null) {
            CliEnvironment::error($locationError);
            return CliEnvironment::EXIT_ERROR;
        }
        if (!$options['ignore-owner'] && ($ownerError = CliEnvironment::getOwnerError(dirname(__FILE__))) !== null) {
            CliEnvironment::error($ownerError);
            return CliEnvironment::EXIT_ERROR;
        }

        try {
            $params = CliEnvironment::getInstallerParams($options);
            $info   = ($options['info'] ? ($options['format'] ?? 'text') : null);

            [
                $url,
                $hasUrl,
            ] = self::resolveUrl($params, $info !== null, CliEnvironment::isInteractive());
            if ($hasUrl) {
                $params['url_new']['value'] = $url;
            }
            CliEnvironment::setServerVars($url, basename(__FILE__));
            CliEnvironment::setArchiveLocation($options['archive-path']);

            $boot = BootstrapRunner::getInstance();
            LogHandler::initErrorHandler([$boot, 'log']);
            if ($info === null) {
                CliEnvironment::out(CliEnvironment::style('Extracting the installer...', 'bold'));
            }
            $view = self::runBootstrap($boot, $options['archive-password']);
        } catch (Exception $e) {
            CliEnvironment::error($e->getMessage());
            return CliEnvironment::EXIT_ERROR;
        }

        if ($view !== BootstrapView::VIEW_REDIRECT) {
            CliEnvironment::error(CliEnvironment::toText($boot->getErrorMessage()));
            CliEnvironment::err('Details are in ' . $boot->getBootLogFilePath());
            return CliEnvironment::EXIT_ERROR;
        }

        // Where the browser would be redirected, the main installer takes over and ends the process
        CliEnvironment::startInstallation(
            new CliInstallation($params, (bool) $options['accept-warnings'], (bool) $options['yes'], $url, $hasUrl, $info)
        );
        require $boot->targetDupInst . '/main.installer.php';
        CliEnvironment::error('the main installer did not run.');
        return CliEnvironment::EXIT_ERROR;
    }

    /**
     * Why the installer can't run from its file: a name that does not end with .php, such as the copy the plugin keeps
     * with the Backups, or a file inside the Backups folder of a site, where the installation would land. An installer
     * the plugin deployed there, as for the Recovery Point or an import, can run: the overwrite parameters the bootstrap
     * reads for its package set the site.
     *
     * @param string $installerFile Path of the installer file
     * @param string $packageHash   Hash of the installer package
     *
     * @return ?string Error, null when the installer can run
     */
    public static function getLocationError(string $installerFile, string $packageHash): ?string
    {
        $copyHint = 'copy the installer and the archive to the folder of the destination site and run installer.php there.';
        if (strtolower(substr($installerFile, -4)) !== '.php') {
            return 'the installer file name must end with .php: ' . $copyHint;
        }
        $installerFolder = dirname($installerFile);
        $folders         = explode('/', str_replace('\\', '/', $installerFolder));
        if (
            array_intersect($folders, self::BACKUPS_FOLDER_NAMES) !== [] &&
            BootstrapUtils::readOverwriteData($installerFolder, $packageHash) === []
        ) {
            return 'the installer is inside the Backups folder of a site: ' . $copyHint;
        }
        return null;
    }

    /**
     * URL the site answers on: from the parameters, asked on a terminal, or a placeholder for --info
     *
     * @param array<string,mixed> $params      Installer parameters of the options
     * @param bool                $info        Whether the command describes the Backup without installing
     * @param bool                $interactive Whether the URL can be asked on a terminal
     *
     * @return array{string,bool} The URL, and whether it is the URL of the site rather than the placeholder
     */
    public static function resolveUrl(array $params, bool $info, bool $interactive): array
    {
        $url = (string) ($params['url_new']['value'] ?? '');
        if (strlen($url) > 0) {
            return [
                $url,
                true,
            ];
        }
        if ($info) {
            // The description of the Backup does not depend on the URL
            return [
                self::INFO_URL,
                false,
            ];
        }
        if (!$interactive) {
            throw new Exception('--url is required.');
        }
        while (strlen($url = trim(CliEnvironment::ask('Site URL'))) === 0) {
            continue;
        }
        return [
            $url,
            true,
        ];
    }

    /**
     * Run the bootstrap; on a terminal the password of an encrypted archive is asked, three times at most
     *
     * @param BootstrapRunner $boot     Bootstrap
     * @param ?string         $password Password of --archive-password, null when not passed
     *
     * @return string View the bootstrap ends on, never the password view
     */
    private static function runBootstrap(BootstrapRunner $boot, ?string $password): string
    {
        if ($password !== null) {
            $_REQUEST[BootstrapRunner::NAME_PWD] = $password;
        }
        $view = $boot->run();
        for ($attempt = 0; $view === BootstrapView::VIEW_PASSWORD && $attempt < 3 && CliEnvironment::isInteractive(); $attempt++) {
            if ($attempt > 0 || $password !== null) {
                CliEnvironment::error('invalid archive password.');
            }
            $password                            = CliEnvironment::ask('Archive password', '', true);
            $_REQUEST[BootstrapRunner::NAME_PWD] = $password;
            $view = $boot->run();
        }
        if ($view === BootstrapView::VIEW_PASSWORD) {
            throw new Exception(
                $password === null ?
                'the archive is encrypted, pass its password with --archive-password.' :
                'invalid archive password.'
            );
        }
        return $view;
    }

    /**
     * @return string
     */
    private static function usage(): string
    {
        // Built from lines: the code inlined in installer.php loses its empty lines
        return implode("\n", [
            'Usage: php installer.php [options]',
            '',
            'Installs the Backup whose archive is in the same folder. On a terminal it asks the missing values and a',
            'confirmation; otherwise it never asks and fails on a missing value.',
            'Run php installer.php --info first: it describes the Backup and the parameters it can be installed with.',
            '',
            '  --url=<url>                URL the site answers on (url_new)',
            '  --dbaction=<action>        What to do with the existing database, such as empty or rename (dbaction)',
            '  --dbhost=<host>            Database host, with :port when needed (dbhost)',
            '  --dbname=<name>            Database name (dbname)',
            '  --dbuser=<user>            Database user (dbuser)',
            '  --dbpass=<password>        Database password (dbpass)',
            '  --params=<file>            JSON file of installer parameters, described below; only read; the options',
            '                             above take precedence over it',
            '  --archive-path=<path>      Folder of the archive, or the archive itself; default: the installer folder',
            '  --archive-password=<pwd>   Password of an encrypted archive',
            '  --accept-warnings          Install even when the validation reports warnings that need to be accepted',
            '  --yes                      Install without asking for confirmation; required without a terminal when',
            '                             the installation overwrites an existing site or database',
            '  --ignore-owner             Run even when the user is not the owner of the installer folder; by default the',
            '                             command stops, as the site could not write the files it installs',
            '  --info                     Describe the Backup and the parameters meant for the command line, with their',
            '                             values and defaults for this Backup, then exit without installing; with --params,',
            '                             it also checks the file',
            '  --format=<format>          Output of --info: text, the default, or json',
            '  --help                     Show this help',
            '',
            'The --params file is a JSON object of installer parameters by key, each with its value, for example:',
            '  {"dbaction": {"value": "empty"}, "blogname": {"value": "My site"}}',
            '',
            'Command line arguments are visible to the other users of the server: pass passwords in the --params file.',
            '',
            'Exit status: 0 installed or described, 1 failed, 2 invalid usage.',
        ]);
    }
}
