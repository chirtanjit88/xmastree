<?php

/**
 * Storage settings exposed through the settings API
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Models\Settings;

use Duplicator\Core\Models\Api\AbstractApiSettings;
use Duplicator\Core\Models\Api\ApiField;
use Duplicator\Models\ActivityLog\LogEventSettingsChange;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\ActivityLog\SettingsChangeTracker;
use Exception;

/**
 * The settings of the Storage settings page. setFromArray() also takes what only the page sets, because it weakens
 * the protection of the Backups: storage_htaccess_off and storage_ssl_disable_verify.
 */
final class StorageApiSettings extends AbstractApiSettings
{
    const PURGE_MODES = [
        'archive_removed'   => 0,
        'local_max_reached' => 1,
        'never'             => 2,
    ];

    /**
     * @return ApiField[]
     */
    public static function apiFields(): array
    {
        return [
            new ApiField('storage_ipv4_only', ApiField::TYPE_BOOLEAN, 'Whether storages are reached over IPv4 only.'),
            new ApiField(
                'storage_ssl_use_server_certs',
                ApiField::TYPE_BOOLEAN,
                'Whether storages use the server certificate authorities instead of the bundled ones.'
            ),
            (new ApiField(
                'backup_records_purge',
                ApiField::TYPE_STRING,
                'When a Backup record is removed from the Backups list: when its archive is in no storage, when the default local storage is full, or never.'
            ))
                ->enum(array_keys(self::PURGE_MODES)),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $global = GlobalEntity::getInstance();
        return [
            'storage_ipv4_only'            => $global->isIpv4Only(),
            'storage_ssl_use_server_certs' => $global->isUsingServerCerts(),
            'backup_records_purge'         => array_search($global->getPurgeBackupRecords(), self::PURGE_MODES, true),
        ];
    }

    /**
     * @param array<string,mixed> $values Values by key
     *
     * @return void
     */
    public function validate(array $values): void
    {
        if (array_key_exists('backup_records_purge', $values)) {
            self::mapValue('backup_records_purge', $values['backup_records_purge'], self::PURGE_MODES);
        }
    }

    /**
     * @param array<string,mixed> $values Values by key
     *
     * @return void
     */
    public function setFromArray(array $values): void
    {
        $this->validate($values);
        $tracker = new SettingsChangeTracker();
        $global  = GlobalEntity::getInstance();

        $switches = [
            'storage_htaccess_off'         => [
                GlobalEntity::STORAGE_HTACCESS_OFF_KEY,
                [
                    $global,
                    'isStorageHtaccessOff',
                ],
                [
                    $global,
                    'setStorageHtaccessOff',
                ],
            ],
            'storage_ssl_use_server_certs' => [
                GlobalEntity::SSL_USE_SERVER_CERTS_KEY,
                [
                    $global,
                    'isUsingServerCerts',
                ],
                [
                    $global,
                    'setSslUseServerCerts',
                ],
            ],
            'storage_ssl_disable_verify'   => [
                GlobalEntity::SSL_DISABLE_VERIFY_KEY,
                static fn(): bool => !$global->isSslVerifyEnabled(),
                [
                    $global,
                    'setSslDisableVerify',
                ],
            ],
            'storage_ipv4_only'            => [
                GlobalEntity::IPV4_ONLY_KEY,
                [
                    $global,
                    'isIpv4Only',
                ],
                [
                    $global,
                    'setIpv4Only',
                ],
            ],
        ];
        foreach ($switches as $key => [$logKey, $getter, $setter]) {
            self::setSwitch($tracker, $values, $key, $logKey, $getter, static fn(bool $value) => $setter($value, false));
        }
        if (array_key_exists('backup_records_purge', $values)) {
            $mode = self::mapValue('backup_records_purge', $values['backup_records_purge'], self::PURGE_MODES);
            $tracker->addChange(
                GlobalEntity::PURGE_BACKUP_RECORDS_KEY,
                $global->getPurgeBackupRecords(),
                $mode,
                'optionChanged',
                [
                    0 => __('When the backup archive is removed from all storages', 'duplicator'),
                    1 => __('When maximum is reached for Default Local Storage', 'duplicator'),
                    2 => __('Never', 'duplicator'),
                ]
            );
            $global->setPurgeBackupRecords($mode, false);
        }

        if (!DynamicGlobalEntity::getInstance()->save() || !$global->save()) {
            throw new Exception('Unable to save the storage settings.');
        }
        do_action('duplicator_update_global_storage_settings');
        $tracker->createLog(LogEventSettingsChange::SUB_TYPE_STORAGE);
    }
}
