<?php

/**
 * Overview of the site and of Duplicator
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Core\Models\Api;

use Duplicator\Libs\Snap\SnapServer;
use Duplicator\Libs\WpUtils\WpDbUtils;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Package\BackupRequestService;
use Duplicator\Utils\UsageStatistics\Telemetry\TelemetrySnapshot;

/**
 * Versions, server, last Backup and storages; addons add their sections through the info filter.
 */
final class SiteInfo
{
    /** @var string Filter addons use to add sections to the overview */
    const INFO_FILTER = 'duplicator_api_info';

    private BackupRequestService $backupService;

    /**
     * @param BackupRequestService $backupService Source of the last Backup
     */
    public function __construct(BackupRequestService $backupService)
    {
        $this->backupService = $backupService;
    }

    /**
     * @return array<string,mixed> Sections of the overview
     */
    public function get(): array
    {
        global $wp_version;

        $backups    = $this->backupService->listBackups(1);
        $lastBackup = (is_array($backups) ? ($backups[0] ?? null) : null);
        $storages   = AbstractStorageEntity::getAll();

        $info = [
            'duplicator'  => [
                'version' => DUPLICATOR_VERSION,
                'variant' => TelemetrySnapshot::getVariant(),
            ],
            'wordpress'   => [
                'version'   => (string) $wp_version,
                'multisite' => is_multisite(),
                'url'       => home_url(),
            ],
            'php'         => [
                'version'            => PHP_VERSION,
                'memory_limit'       => (string) ini_get('memory_limit'),
                'max_execution_time' => (int) ini_get('max_execution_time'),
            ],
            'database'    => [
                'engine'  => WpDbUtils::getDbEngine(),
                'version' => (string) WpDbUtils::getVersion(),
            ],
            'web_server'  => SnapServer::getWebServer(),
            'last_backup' => $lastBackup,
            'storages'    => is_array($storages) ? count(array_filter($storages, [AbstractStorageEntity::class, 'apiExposes'])) : 0,
        ];

        /**
         * Add sections to the overview
         *
         * @param array<string,mixed> $info Sections
         */
        return (array) apply_filters(self::INFO_FILTER, $info);
    }
}
