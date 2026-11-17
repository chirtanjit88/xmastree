<?php

/**
 * Maintenance operations of Tools > General exposed by the API interfaces
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Core\Models\Api;

use Duplicator\Core\CapMng;
use Duplicator\Core\MigrationMng;
use Duplicator\Package\PackageUtils;
use WP_Error;

/**
 * Purge orphaned Backup files, empty the temporary folder and remove the installer files, as Tools > General does.
 * The site availability of the capability is enforced here; user authorization belongs to the caller.
 */
class MaintenanceApi
{
    /**
     * Delete the Backup files no Backup record owns
     *
     * @return array{deleted:string[],failed:string[],message:string}|WP_Error
     */
    public function purgeOrphans()
    {
        $unavailable = self::getUnavailableError();
        if ($unavailable !== null) {
            return $unavailable;
        }

        $result = self::splitByResult(PackageUtils::purgeOrphanedPackageFiles());
        if ($result['deleted'] === [] && $result['failed'] === []) {
            $result['message'] = __('No orphaned backup files were found.', 'duplicator');
        } else {
            $result['message'] = sprintf(
                /* translators: 1: number of deleted files, 2: number of files that could not be deleted */
                __('%1$d orphaned backup files deleted, %2$d could not be deleted.', 'duplicator'),
                count($result['deleted']),
                count($result['failed'])
            );
        }
        return $result;
    }

    /**
     * Empty the temporary build folder
     *
     * @return array{message:string}|WP_Error
     */
    public function cleanTemp()
    {
        $unavailable = self::getUnavailableError();
        if ($unavailable !== null) {
            return $unavailable;
        }

        $result = PackageUtils::tmpCleanupAllLocked();
        if ($result === null) {
            return new WP_Error(
                'duplicator_backup_conflict',
                __('Another backup process is running. Wait for it to finish and try again.', 'duplicator'),
                ['status' => 409]
            );
        }
        if ($result === false) {
            return new WP_Error(
                'duplicator_backup_failed',
                __('The temporary files could not be removed: a backup may be running.', 'duplicator'),
                ['status' => 500]
            );
        }
        return ['message' => __('The temporary build files were removed.', 'duplicator')];
    }

    /**
     * Remove the installer files left after an installation and purge the caches
     *
     * @return array{removed:string[],failed:string[],cache_purge:string[],manual_purge:string[],message:string}|WP_Error
     */
    public function removeInstallerFiles()
    {
        $unavailable = self::getUnavailableError();
        if ($unavailable !== null) {
            return $unavailable;
        }

        $removal = MigrationMng::removeInstallerFiles();
        $result  = self::splitByResult($removal['files']);
        $manual  = array_map(
            static fn(array $notice): string => $notice['name'] . ': ' . wp_strip_all_tags($notice['description']),
            MigrationMng::getManualPurgeNotices()
        );

        return [
            'removed'      => $result['deleted'],
            'failed'       => $result['failed'],
            'cache_purge'  => array_values(array_map('wp_strip_all_tags', $removal['purge'])),
            'manual_purge' => array_values($manual),
            'message'      => self::installerMessage($result['deleted'], $result['failed']),
        ];
    }

    /**
     * @return ?WP_Error The refusal when the site does not allow the Duplicator tools, null otherwise
     */
    private static function getUnavailableError(): ?WP_Error
    {
        if (CapMng::isCapAvailableOnSite(CapMng::CAP_BASIC)) {
            return null;
        }
        return new WP_Error(
            'duplicator_backup_unavailable',
            __('The Duplicator tools are not available on this site.', 'duplicator'),
            ['status' => 403]
        );
    }

    /**
     * @param string[] $removed Removed files
     * @param string[] $failed  Files that could not be removed
     *
     * @return string
     */
    private static function installerMessage(array $removed, array $failed): string
    {
        if ($failed !== []) {
            return __('Some installer files could not be removed; remove them by hand.', 'duplicator');
        }
        if ($removed === []) {
            return __('No installer files were found; the caches were purged.', 'duplicator');
        }
        return __('The installer files were removed and the caches purged.', 'duplicator');
    }

    /**
     * @param array<string,bool> $files Whether each file, by path, was removed
     *
     * @return array{deleted:string[],failed:string[]} File names, without their folder
     */
    private static function splitByResult(array $files): array
    {
        $deleted = [];
        $failed  = [];
        foreach ($files as $path => $removed) {
            if ($removed) {
                $deleted[] = basename((string) $path);
            } else {
                $failed[] = basename((string) $path);
            }
        }
        return [
            'deleted' => $deleted,
            'failed'  => $failed,
        ];
    }
}
