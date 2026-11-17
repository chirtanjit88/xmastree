<?php

/**
 * Site protection verdict: a recent backup with a finished remote copy.
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Package;

use Duplicator\Core\Models\TypeRegistry;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StorageAuthInterface;
use Duplicator\Models\Storages\UnknownStorage;

/**
 * The site is protected when a complete backup created within the window has a finished copy on remote storage.
 *
 * @phpstan-type RemoteBackup array{id: int, created: string, age: string, storages: string[], is_full_backup: bool}
 * @phpstan-type Status array{protected: bool, reason: string, summary: string, last_remote_backup: RemoteBackup|null}
 */
class ProtectionStatus
{
    const WINDOW_DAYS = 7;

    const REASON_PROTECTED         = 'protected';
    const REASON_TOO_OLD           = 'too_old';
    const REASON_NO_BACKUPS        = 'no_backups';
    const REASON_NO_REMOTE_STORAGE = 'no_remote_storage';
    const REASON_LOCAL_ONLY        = 'local_only';

    const REASONS = [
        self::REASON_PROTECTED,
        self::REASON_TOO_OLD,
        self::REASON_NO_BACKUPS,
        self::REASON_NO_REMOTE_STORAGE,
        self::REASON_LOCAL_ONLY,
    ];

    /** @var string Filter applied to the verdict before it is returned */
    const FILTER = 'duplicator_protection_status';

    /**
     * Return the protection verdict, passed through the protection status filter.
     *
     * @return Status
     */
    public function get(): array
    {
        /**
         * Filter the protection verdict.
         *
         * @param Status $status Verdict
         */
        return apply_filters(self::FILTER, $this->verdict());
    }

    /**
     * Compute the verdict with one reason, checked in a fixed order.
     *
     * @return Status
     */
    private function verdict(): array
    {
        global $wpdb;

        $noBackups = $this->status(
            self::REASON_NO_BACKUPS,
            __('Not protected: this site has no completed backups.', 'duplicator')
        );

        $types = $this->registeredBackupTypes();
        if ($types === []) {
            return $noBackups;
        }

        $where    = $wpdb->prepare(
            '`status` = %d AND FIND_IN_SET(%s, `flags`) > 0',
            AbstractPackage::STATUS_COMPLETE,
            AbstractPackage::FLAG_HAVE_REMOTE
        );
        $packages = DupPackage::dbSelect($where, 1, 0, '`created` DESC', 'objs', $types);
        if (($packages[0] ?? null) instanceof DupPackage) {
            return $this->remoteBackupStatus($packages[0]);
        }

        if (!$this->hasCompleteBackup($types)) {
            return $noBackups;
        }

        if (!$this->hasRemoteStorageSetUp()) {
            return $this->status(
                self::REASON_NO_REMOTE_STORAGE,
                __('Not protected: no remote storage is set up, so all backups stay on this server.', 'duplicator')
            );
        }

        return $this->status(
            self::REASON_LOCAL_ONLY,
            __('Not protected: backups exist, but none has a finished copy on remote storage.', 'duplicator')
        );
    }

    /**
     * @param DupPackage $package Newest complete backup with a remote copy
     *
     * @return Status
     */
    private function remoteBackupStatus(DupPackage $package): array
    {
        $storages = [];
        foreach ($package->upload_infos as $uploadInfo) {
            if ($uploadInfo->isRemoteCopy()) {
                $storages[] = $uploadInfo->getStorage()->getName();
            }
        }
        $storages = array_values(array_unique($storages));
        $isFull   = BackupRequestService::isFullBackup($package);
        $age      = (string) $package->getPackageLife('human');

        $backup = [
            'id'             => $package->getId(),
            'created'        => $package->getCreated(),
            'age'            => $age,
            'storages'       => $storages,
            'is_full_backup' => $isFull,
        ];

        if ($package->getPackageLife() > self::WINDOW_DAYS * DAY_IN_SECONDS) {
            return $this->status(
                self::REASON_TOO_OLD,
                sprintf(
                    /* translators: %s: time since the backup, e.g. "2 weeks" */
                    __('Not protected: the last backup with a remote copy was %s ago.', 'duplicator'),
                    $age
                ),
                $backup
            );
        }

        $summary = sprintf(
            /* translators: 1: time since the backup, e.g. "2 days", 2: storage names */
            __('Protected: the last backup with a remote copy was %1$s ago, on %2$s.', 'duplicator'),
            $age,
            implode(', ', $storages)
        );
        if (!$isFull) {
            $summary .= ' ' . __('It is not a full backup, so part of the site is not in it.', 'duplicator');
        }

        return $this->status(self::REASON_PROTECTED, $summary, $backup);
    }

    /**
     * @param string            $reason  One of self::REASONS
     * @param string            $summary Sentence the assistant can quote
     * @param RemoteBackup|null $backup  Newest backup with a remote copy
     *
     * @return Status
     */
    private function status(string $reason, string $summary, ?array $backup = null): array
    {
        return [
            'protected'          => $reason === self::REASON_PROTECTED,
            'reason'             => $reason,
            'summary'            => $summary,
            'last_remote_backup' => $backup,
        ];
    }

    /**
     * Backup types in the table that this request can load, so rows of a disabled addon don't hide the others.
     *
     * @return string[]
     */
    private function registeredBackupTypes(): array
    {
        global $wpdb;

        $types = $wpdb->get_col('SELECT DISTINCT `type` FROM `' . DupPackage::getTableName() . '`');

        return array_values(array_filter($types, [TypeRegistry::class, 'isRegistered']));
    }

    /**
     * @param string[] $types Backup types to include
     *
     * @return bool
     */
    private function hasCompleteBackup(array $types): bool
    {
        global $wpdb;
        $where = $wpdb->prepare('`status` = %d', AbstractPackage::STATUS_COMPLETE);

        return DupPackage::dbSelect($where, 1, 0, '', 'ids', $types) !== [];
    }

    /**
     * Whether a remote storage a backup could upload to exists. A storage that needs a connection counts once connected.
     *
     * @return bool
     */
    private function hasRemoteStorageSetUp(): bool
    {
        $storages = AbstractStorageEntity::getAll();
        if (!is_array($storages)) {
            return false;
        }

        foreach ($storages as $storage) {
            if (
                $storage->isLocal() ||
                $storage instanceof UnknownStorage ||
                !$storage::isSupported() ||
                !$storage::isEnabled()
            ) {
                continue;
            }
            if ($storage instanceof StorageAuthInterface && !$storage->isAuthorized()) {
                continue;
            }
            return true;
        }

        return false;
    }
}
