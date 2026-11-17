<?php

/**
 * DUPLICATOR AI READY ADDON - ABILITIES REGISTRAR
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Addons\AiReadyAddon;

use Duplicator\Core\CapMng;
use Duplicator\Core\Models\Api\SiteInfo;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Package\AbstractPackage;
use Duplicator\Package\BackupRequestService;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\ProtectionStatus;
use Duplicator\Utils\Logging\DupLog;
use stdClass;
use WP_Error;

/**
 * Registers the ability category and the backup abilities as a thin adapter over BackupRequestService.
 *
 * @phpstan-import-type Status from ProtectionStatus
 */
class AbilitiesRegistrar
{
    const CATEGORY_SLUG = 'duplicator';

    const BACKUP_LIST              = 'duplicator/backup-list';
    const BACKUP_GET               = 'duplicator/backup-get';
    const BACKUP_QUEUE             = 'duplicator/backup-queue';
    const BACKUP_TRANSFER          = 'duplicator/backup-transfer';
    const BACKUP_PROTECTION_STATUS = 'duplicator/backup-protection-status';
    const BACKUP_CANCEL            = 'duplicator/backup-cancel';
    const BACKUP_LOG               = 'duplicator/backup-log';
    const BACKUP_DELETE            = 'duplicator/backup-delete';
    const BACKUP_INSTALL_GUIDE     = 'duplicator/backup-install-guide';
    const INFO                     = 'duplicator/info';

    /** @var array<string,string> Released names kept as deprecated aliases: current ability by old name */
    const DEPRECATED_ALIASES = [
        'duplicator/list-backups'      => self::BACKUP_LIST,
        'duplicator/get-backup-status' => self::BACKUP_GET,
        'duplicator/create-backup'     => self::BACKUP_QUEUE,
    ];

    /** @var array<string,array<string,mixed>> Inputs a deprecated alias passes when its caller leaves them out, to keep its released behaviour */
    const DEPRECATED_ALIAS_INPUT_DEFAULTS = [
        'duplicator/create-backup' => ['scope' => BuildComponents::COMP_ACTION_ALL],
    ];

    /** @var string Requester of ability-created Backups and channel of the activity log events of every ability call */
    const REQUESTER = 'AI Assistant';

    private BackupRequestService $service;

    private ProtectionStatus $protectionStatus;

    private SiteInfo $siteInfo;

    /**
     * @param BackupRequestService $service          Backup request service
     * @param ProtectionStatus     $protectionStatus Protection verdict
     * @param SiteInfo             $siteInfo         Site overview
     */
    public function __construct(BackupRequestService $service, ProtectionStatus $protectionStatus, SiteInfo $siteInfo)
    {
        $this->service          = $service;
        $this->protectionStatus = $protectionStatus;
        $this->siteInfo         = $siteInfo;
    }

    /**
     * @return void
     */
    public function registerCategory(): void
    {
        wp_register_ability_category(self::CATEGORY_SLUG, [
            'label'       => __('Duplicator', 'duplicator'),
            'description' => SnapString::oneLine(__(
                'Duplicator is the backup plugin of this site. Use these abilities for any question about backups, restoring,
                recovery, backup storage, backup schedules or settings, and whether the site is protected, safe or backed up.
                They are the only reliable source: don\'t look for backup files in folders or database tables.',
                'duplicator'
            )),
        ]);
    }

    /**
     * @return void
     */
    public function registerAbilities(): void
    {
        AbilityRegistration::register(self::BACKUP_LIST, [
            'label'               => __('List Backups', 'duplicator'),
            'description'         => sprintf(
                /* translators: %d: default number of backups returned */
                __(
                    'Returns the most recent backups, %d unless limit says otherwise, optionally only those with a status:
                    id, name, status, date, size. This is the list of the site\'s backups: don\'t look for backup files on disk
                    or in old folders instead. Use id with backup-get to check progress.',
                    'duplicator'
                ),
                BackupRequestService::LIST_LIMIT
            ),
            'execute_callback'    => [
                $this,
                'handleListBackups',
            ],
            'permission_callback' => [
                $this,
                'readPermissionCallback',
            ],
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'limit'  => [
                        'type'        => 'integer',
                        'minimum'     => 1,
                        'maximum'     => BackupRequestService::LIST_MAX,
                        'default'     => BackupRequestService::LIST_LIMIT,
                        'description' => 'Maximum number of backups to return.',
                    ],
                    'status' => [
                        'type'        => 'string',
                        'enum'        => BackupRequestService::STATUS_KEYS,
                        'description' => 'Return only the backups with this status.',
                    ],
                    'type'   => [
                        'type'        => 'string',
                        'enum'        => array_keys($this->service->getListTypes()),
                        'default'     => BackupRequestService::LIST_TYPE_STANDARD,
                        'description' => 'Type of backups to list, standard by default; the other values list the backups an add-on creates, '
                            . 'such as the snapshots of incremental backups.',
                    ],
                ],
                'additionalProperties' => false,
                'default'              => new stdClass(),
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'count'   => [
                        'type'        => 'integer',
                        'description' => 'Total number of backups returned.',
                    ],
                    'backups' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'id'      => [
                                    'type'        => 'integer',
                                    'description' => 'Unique backup id. Pass to backup-get to poll progress.',
                                ],
                                'name'    => [
                                    'type'        => 'string',
                                    'description' => 'Human-readable backup name.',
                                ],
                                'created' => [
                                    'type'        => 'string',
                                    'description' => 'Creation timestamp.',
                                ],
                                'status'  => [
                                    'type'        => 'string',
                                    'enum'        => BackupRequestService::STATUS_KEYS,
                                    'description' => 'Current status of the backup.',
                                ],
                                'size'    => [
                                    'type'        => 'string',
                                    'description' => 'Human-readable backup archive size (e.g. "214 MB"); a placeholder while still building.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'annotations'         => ['readonly' => true],
        ]);

        AbilityRegistration::register(self::BACKUP_PROTECTION_STATUS, [
            'label'               => __('Get Protection Status', 'duplicator'),
            'description'         => SnapString::oneLine(sprintf(
                /* translators: %d: number of days */
                __(
                    'Answers "is my site protected?", "is my site safe?" and "is my site backed up?": call it first for these
                    questions, before any security check. The site is protected when a complete backup created in the last %d days
                    has a finished copy on remote storage; a backup kept only on this server does not count. Quote summary to the user.
                    The check reads what Duplicator recorded: a copy deleted directly at the storage provider is not detected, a backup
                    being transferred or downloaded does not count until the transfer ends, and an incremental schedule that skips runs
                    because nothing changed reports too_old once the window passes. Use backup-list and backup-get for details
                    of a failed backup.',
                    'duplicator'
                ),
                ProtectionStatus::WINDOW_DAYS
            )),
            'execute_callback'    => [
                $this,
                'handleGetProtectionStatus',
            ],
            'permission_callback' => [
                $this,
                'readPermissionCallback',
            ],
            'input_schema'        => [
                'type'       => 'object',
                'properties' => new stdClass(),
                'default'    => new stdClass(),
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'protected'          => [
                        'type'        => 'boolean',
                        'description' => 'True when a recent backup has a finished copy on remote storage.',
                    ],
                    'reason'             => [
                        'type'        => 'string',
                        'enum'        => ProtectionStatus::REASONS,
                        'description' => 'Why the site is or is not protected.',
                    ],
                    'summary'            => [
                        'type'        => 'string',
                        'description' => 'One sentence to quote to the user.',
                    ],
                    'last_remote_backup' => [
                        'type'        => [
                            'object',
                            'null',
                        ],
                        'description' => 'Newest complete backup with a remote copy; null when there is none.',
                        'properties'  => [
                            'id'             => [
                                'type'        => 'integer',
                                'description' => 'Backup id. Pass to backup-get for details.',
                            ],
                            'created'        => [
                                'type'        => 'string',
                                'description' => 'Creation time, GMT.',
                            ],
                            'age'            => [
                                'type'        => 'string',
                                'description' => 'Time since creation, human readable (e.g. "2 days").',
                            ],
                            'storages'       => [
                                'type'        => 'array',
                                'items'       => ['type' => 'string'],
                                'description' => 'Names of the remote storages holding a finished copy.',
                            ],
                            'is_full_backup' => [
                                'type'        => 'boolean',
                                'description' => 'False when the backup leaves out part of the site, such as a database-only backup.',
                            ],
                        ],
                    ],
                ],
            ],
            'annotations'         => ['readonly' => true],
        ]);

        // Add-ons offer more options, such as the template, only where they run
        $options         = $this->service->getRequestOptions();
        $queueProperties = [
            'scope'       => [
                'type'        => 'string',
                'enum'        => BuildComponents::COMPONENTS_ACTIONS,
                'description' => 'Make the backup the full backup of the template, ignoring its filters: "all" for the whole '
                    . 'site, "database" for the database only. Leave it out to use the template as it is.',
            ],
            'password'    => [
                'type'        => 'string',
                'minLength'   => 1,
                'description' => 'Installer password protecting the backup; a template that encrypts the archive keeps '
                    . 'encrypting it. Leave it out to keep the template protection.',
            ],
            'reason'      => [
                'type'        => 'string',
                'default'     => '',
                'maxLength'   => BackupRequestService::REASON_MAX_LENGTH,
                'description' => 'Why the backup is being created; shown to the user as attribution.',
            ],
            'storage_ids' => [
                'type'        => 'array',
                'minItems'    => 1,
                'items'       => [
                    'type'    => 'integer',
                    'minimum' => 1,
                ],
                'description' => 'Ids of the storages to keep the backup in, from storage-list.',
            ],
        ];
        foreach ($options as $name => $option) {
            $queueProperties[$name] = $option->getSchema(true);
        }

        AbilityRegistration::register(self::BACKUP_QUEUE, [
            'label'               => __('Create Backup', 'duplicator'),
            'description'         => SnapString::oneLine(__(
                'Starts a new backup from the default template. Without scope the template is used as it is, file and table
                filters included; with scope the backup is the full backup of the template: its filters are ignored and scope
                sets what it contains. With password the backup is protected by that installer password. Without storage_ids
                the backup is kept on the default local storage; storage ids come from storage-list. Returns an id for polling
                with backup-get. When the backup completes, the storage retention setting may remove the oldest backups.',
                'duplicator'
            )),
            'execute_callback'    => [
                $this,
                'handleCreateBackup',
            ],
            'permission_callback' => [
                $this,
                'writePermissionCallback',
            ],
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => $queueProperties,
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'id'      => [
                        'type'        => 'integer',
                        'description' => 'Unique backup id. Pass to backup-get to poll progress.',
                    ],
                    'message' => [
                        'type'        => 'string',
                        'description' => 'Confirmation message.',
                    ],
                ],
            ],
            'annotations'         => [
                'readonly'    => false,
                'destructive' => false,
            ],
        ]);

        AbilityRegistration::register(self::BACKUP_TRANSFER, [
            'label'               => __('Transfer Backup', 'duplicator'),
            'description'         => SnapString::oneLine(__(
                'Copies a complete backup between the default local storage and another storage, either way: from the default
                local storage to a storage, or from a storage that holds the backup to the default local storage. One of the two
                must be the default local storage (is_default in storage-list); to copy between two other storages, transfer to
                the default local storage first, then from it. Returns the backup id for polling with backup-get. When the
                transfer completes, the storage retention setting may remove the oldest backups.',
                'duplicator'
            )),
            'execute_callback'    => [
                $this,
                'handleTransferBackup',
            ],
            'permission_callback' => [
                $this,
                'writePermissionCallback',
            ],
            'input_schema'        => [
                'type'                 => 'object',
                'required'             => [
                    'id',
                    'from_storage_id',
                    'to_storage_id',
                ],
                'properties'           => [
                    'id'              => [
                        'type'        => 'integer',
                        'minimum'     => 1,
                        'description' => 'The backup id from backup-list or backup-queue.',
                    ],
                    'from_storage_id' => [
                        'type'        => 'integer',
                        'minimum'     => 1,
                        'description' => 'Id of the storage that holds the backup, from storage-list.',
                    ],
                    'to_storage_id'   => [
                        'type'        => 'integer',
                        'minimum'     => 1,
                        'description' => 'Id of the storage to copy the backup to, from storage-list.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'id'      => [
                        'type'        => 'integer',
                        'description' => 'Backup id. Pass to backup-get to poll progress.',
                    ],
                    'message' => [
                        'type'        => 'string',
                        'description' => 'Confirmation message.',
                    ],
                ],
            ],
            'annotations'         => [
                'readonly'    => false,
                'destructive' => false,
            ],
        ]);

        AbilityRegistration::register(self::BACKUP_GET, [
            'label'               => __('Get Backup Status', 'duplicator'),
            'description'         => __('Returns backup status by id. Poll until status is complete, failed, cancelled, or missing.', 'duplicator'),
            'execute_callback'    => [
                $this,
                'handleGetBackupStatus',
            ],
            'permission_callback' => [
                $this,
                'readPermissionCallback',
            ],
            'input_schema'        => [
                'type'       => 'object',
                'required'   => ['id'],
                'properties' => [
                    'id' => [
                        'type'        => 'integer',
                        'minimum'     => 1,
                        'description' => 'The backup id from backup-list or backup-queue.',
                    ],
                ],
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'id'             => [
                        'type'        => 'integer',
                        'description' => 'Backup id.',
                    ],
                    'status'         => [
                        'type'        => 'string',
                        'enum'        => array_merge([BackupRequestService::STATUS_MISSING], BackupRequestService::STATUS_KEYS),
                        'description' => 'Current status of the backup; poll until it reaches a terminal value.',
                    ],
                    'name'           => [
                        'type'        => 'string',
                        'description' => 'Human-readable backup name; empty when the backup is missing.',
                    ],
                    'created'        => [
                        'type'        => 'string',
                        'description' => 'Creation timestamp; empty when the backup is missing.',
                    ],
                    'size'           => [
                        'type'        => 'string',
                        'description' => 'Human-readable backup archive size (e.g. "214 MB"); a placeholder while still building, empty when missing.',
                    ],
                    'can_proceed'    => [
                        'type'        => 'boolean',
                        'description' => 'True when a usable backup created through this API exists locally and no warning '
                            . 'reports files missing from the archive. Other build warnings do not clear it.',
                    ],
                    'message'        => [
                        'type'        => 'string',
                        'description' => 'Detailed status message for the current operation.',
                    ],
                    'details_url'    => [
                        'type'        => 'string',
                        'description' => 'Admin URL of the backup details page.',
                    ],
                    'is_full_backup' => [
                        'type'        => 'boolean',
                        'description' => 'True when no configured component, table, subsite or archive filter excludes anything from the backup.',
                    ],
                    'exclusions'     => [
                        'type'        => 'array',
                        'items'       => ['type' => 'string'],
                        'description' => 'What the backup does not contain; empty when is_full_backup is true.',
                    ],
                    'warnings'       => [
                        'type'        => 'array',
                        'description' => 'Non-critical conditions detected during the build. The backup completed; '
                            . 'report them to the user rather than treating them as a failure.',
                        'items'       => [
                            'type'       => 'object',
                            'properties' => [
                                'code'    => [
                                    'type'        => 'integer',
                                    'description' => 'Duplicator warning code, matching an AbstractPackage::WARNING_* constant.',
                                ],
                                'label'   => [
                                    'type'        => 'string',
                                    'description' => 'Short summary of the condition.',
                                ],
                                'message' => [
                                    'type'        => 'string',
                                    'description' => 'Full explanation for the user.',
                                ],
                            ],
                        ],
                    ],
                    'failure'        => [
                        'type'        => 'object',
                        'description' => 'Why a failed or cancelled backup stopped and how to fix it, as shown in the Activity Log. '
                            . 'Relay the reason and the fixes to the user; a fix may belong to an earlier failure, so present the fixes '
                            . 'as suggestions. Empty when no failure was recorded.',
                        'properties'  => [
                            'reason' => [
                                'type'        => 'string',
                                'description' => 'Failure cause; may be empty even when fixes are listed.',
                            ],
                            'fixes'  => [
                                'type'  => 'array',
                                'items' => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'error'           => [
                                            'type'        => 'string',
                                            'description' => 'What went wrong.',
                                        ],
                                        'troubleshooting' => [
                                            'type'        => 'array',
                                            'items'       => ['type' => 'string'],
                                            'description' => 'Steps the user can take to fix it.',
                                        ],
                                        'code_snippet'    => [
                                            'type'        => 'string',
                                            'description' => 'Configuration example for the fix; empty when none.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'storages'       => [
                        'type'        => 'array',
                        'description' => 'The copy of the backup on each storage it was sent to, from the latest transfer to it. '
                            . 'A complete backup can have a failed copy: has_copy is the only proof that a storage holds the backup. '
                            . 'Tell the user about a failed copy and its reason.',
                        'items'       => [
                            'type'       => 'object',
                            'properties' => [
                                'id'       => [
                                    'type'        => 'integer',
                                    'description' => 'Storage id.',
                                ],
                                'name'     => [
                                    'type'        => 'string',
                                    'description' => 'Storage name.',
                                ],
                                'status'   => [
                                    'type'        => 'string',
                                    'enum'        => BackupRequestService::STORAGE_STATUS_KEYS,
                                    'description' => 'Status of the latest transfer to the storage.',
                                ],
                                'has_copy' => [
                                    'type'        => 'boolean',
                                    'description' => 'True when the storage holds a finished copy of the backup.',
                                ],
                                'reason'   => [
                                    'type'        => 'string',
                                    'description' => 'Why the copy failed, was cancelled or is gone; empty otherwise.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'annotations'         => ['readonly' => true],
        ]);

        $this->registerCancel();

        AbilityRegistration::register(self::BACKUP_LOG, [
            'label'               => __('Get Backup Log', 'duplicator'),
            'description'         => SnapString::oneLine(__(
                'Returns the build log of a backup, to explain a failure or check what the build did. A very long log returns only its end.',
                'duplicator'
            )),
            'execute_callback'    => fn($input) => $this->service->getLog(self::getInputId($input)),
            'permission_callback' => [
                $this,
                'readPermissionCallback',
            ],
            'input_schema'        => [
                'type'                 => 'object',
                'required'             => ['id'],
                'properties'           => [
                    'id' => [
                        'type'        => 'integer',
                        'minimum'     => 1,
                        'description' => 'The backup id from backup-list or backup-queue.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'id'        => ['type' => 'integer'],
                    'log'       => [
                        'type'        => 'string',
                        'description' => 'Log text; empty when the log is not available.',
                    ],
                    'truncated' => [
                        'type'        => 'boolean',
                        'description' => 'True when only the end of a long log is returned.',
                    ],
                    'message'   => ['type' => 'string'],
                ],
            ],
            'annotations'         => ['readonly' => true],
        ]);

        AbilityRegistration::register(self::BACKUP_INSTALL_GUIDE, [
            'label'               => __('Get Backup Install Guide', 'duplicator'),
            'description'         => __('Explains how a backup is installed, to tell the user what to do.', 'duplicator'),
            'execute_callback'    => fn($input) => $this->service->getInstallGuide(),
            'permission_callback' => [
                $this,
                'readPermissionCallback',
            ],
            'input_schema'        => [
                'type'       => 'object',
                'properties' => new stdClass(),
                'default'    => new stdClass(),
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'instructions' => [
                        'type'        => 'string',
                        'description' => 'How to install a backup, to tell the user.',
                    ],
                ],
            ],
            'annotations'         => ['readonly' => true],
        ]);

        AbilityRegistration::register(self::BACKUP_DELETE, [
            'label'               => __('Delete Backup', 'duplicator'),
            'description'         => SnapString::oneLine(__(
                'Permanently deletes a backup and its files on this server; copies on remote storages are kept. Works only when
                sensitive actions are allowed in Duplicator > Settings > General.',
                'duplicator'
            )),
            'execute_callback'    => fn($input) => $this->service->delete(self::getInputId($input)),
            'permission_callback' => [
                $this,
                'writePermissionCallback',
            ],
            'input_schema'        => [
                'type'                 => 'object',
                'required'             => ['id'],
                'properties'           => [
                    'id' => [
                        'type'        => 'integer',
                        'minimum'     => 1,
                        'description' => 'The backup id from backup-list.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'id'      => ['type' => 'integer'],
                    'message' => ['type' => 'string'],
                ],
            ],
            'annotations'         => [
                'readonly'    => false,
                'destructive' => true,
            ],
        ]);

        AbilityRegistration::register(self::INFO, [
            'label'               => __('Site Info', 'duplicator'),
            'description'         => SnapString::oneLine(__(
                'Returns an overview of the site and of Duplicator: Duplicator, WordPress, PHP and database versions,
                web server, last backup, number of storages; with schedules, how many are active and the next run; with
                incremental backups, whether they are on, whether one can run now and why not, the snapshots and the
                Duplicator Cloud space.',
                'duplicator'
            )),
            'execute_callback'    => fn($input): array => $this->siteInfo->get(),
            'permission_callback' => [
                $this,
                'readPermissionCallback',
            ],
            'input_schema'        => [
                'type'       => 'object',
                'properties' => new stdClass(),
                'default'    => new stdClass(),
            ],
            'output_schema'       => ['type' => 'object'],
            'annotations'         => ['readonly' => true],
        ]);

        foreach (self::DEPRECATED_ALIASES as $oldName => $name) {
            self::registerDeprecatedAlias($oldName, $name);
        }
    }

    /**
     * Register a released ability name that delegates to the ability that replaced it
     *
     * @param string $oldName Deprecated name
     * @param string $name    Current name
     *
     * @return void
     */
    private static function registerDeprecatedAlias(string $oldName, string $name): void
    {
        $ability = wp_get_ability($name);
        if ($ability === null) {
            return;
        }

        wp_register_ability($oldName, [
            'label'               => $ability->get_label(),
            'description'         => sprintf('Deprecated: use %s. ', $name) . self::getAliasDefaultsNote($oldName, $name)
                . $ability->get_description(),
            'category'            => $ability->get_category(),
            'input_schema'        => self::getAliasInputSchema($oldName, $name, $ability->get_input_schema()),
            'output_schema'       => $ability->get_output_schema(),
            'execute_callback'    => static function ($input = null) use ($oldName, $name) {
                DupLog::trace("AI ability {$oldName} is deprecated, use {$name}");
                $current = wp_get_ability($name);
                if (isset(self::DEPRECATED_ALIAS_INPUT_DEFAULTS[$oldName])) {
                    $input = (array) $input + self::DEPRECATED_ALIAS_INPUT_DEFAULTS[$oldName];
                }
                return $current === null ? new WP_Error('duplicator_failed', 'Ability not found.') : $current->execute($input);
            },
            'permission_callback' => static function ($input = null) use ($name) {
                $current = wp_get_ability($name);
                return $current === null ? false : $current->check_permissions($input);
            },
            'meta'                => $ability->get_meta(),
        ]);
    }

    /**
     * The inputs the alias passes when they are left out, for the start of its description: they override what the
     * current ability's description says about leaving them out
     *
     * @param string $oldName Deprecated name
     * @param string $name    Current name
     *
     * @return string The note, empty when the alias passes no input
     */
    private static function getAliasDefaultsNote(string $oldName, string $name): string
    {
        $note = '';
        foreach (self::DEPRECATED_ALIAS_INPUT_DEFAULTS[$oldName] ?? [] as $key => $value) {
            $note .= sprintf(
                'Unlike %1$s, without %2$s this ability uses %2$s %3$s, whatever the description below says about leaving it out. ',
                $name,
                $key,
                (string) wp_json_encode($value)
            );
        }
        return $note;
    }

    /**
     * The current ability's input schema, with the inputs the alias passes when they are left out declared as their
     * default and stated in their description, which otherwise describes leaving them out in the current ability
     *
     * @param string              $oldName Deprecated name
     * @param string              $name    Current name
     * @param array<string,mixed> $schema  Input schema of the current ability
     *
     * @return array<string,mixed>
     */
    private static function getAliasInputSchema(string $oldName, string $name, array $schema): array
    {
        foreach (self::DEPRECATED_ALIAS_INPUT_DEFAULTS[$oldName] ?? [] as $key => $value) {
            if (!is_array($schema['properties'] ?? null) || !is_array($schema['properties'][$key] ?? null)) {
                continue;
            }
            $property                   = $schema['properties'][$key];
            $property['default']        = $value;
            $property['description']    = sprintf(
                'Unlike %1$s, when it is left out this ability uses %2$s, whatever the rest of this description says. ',
                $name,
                (string) wp_json_encode($value)
            ) . (is_string($property['description'] ?? null) ? $property['description'] : '');
            $schema['properties'][$key] = $property;
        }
        return $schema;
    }

    /**
     * @return void
     */
    private function registerCancel(): void
    {
        AbilityRegistration::register(self::BACKUP_CANCEL, [
            'label'               => __('Cancel Backup', 'duplicator'),
            'description'         => SnapString::oneLine(__(
                'Stops what a backup is doing now, at the next step of the worker; a backup that finishes before then stays
                complete. A backup being created is stopped and its partial files discarded: poll backup-get until its status
                is cancelled or complete. A transfer is stopped and the backup goes back to complete, its existing copies kept.',
                'duplicator'
            )),
            'execute_callback'    => fn($input) => $this->service->cancel(self::getInputId($input)),
            'permission_callback' => [
                $this,
                'writePermissionCallback',
            ],
            'input_schema'        => [
                'type'                 => 'object',
                'required'             => ['id'],
                'properties'           => [
                    'id' => [
                        'type'        => 'integer',
                        'minimum'     => 1,
                        'description' => 'The backup id from backup-list or backup-queue.',
                    ],
                ],
                'additionalProperties' => false,
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'id'      => ['type' => 'integer'],
                    'status'  => [
                        'type'        => 'string',
                        'description' => 'cancelling until the worker stops the backup.',
                    ],
                    'message' => ['type' => 'string'],
                ],
            ],
            'annotations'         => [
                'readonly'    => false,
                'destructive' => false,
            ],
        ]);
    }

    /**
     * Permission check of the read abilities: requires CAP_BASIC; staging-guarded.
     *
     * @param mixed $input Ability input (unused).
     *
     * @return bool|WP_Error
     */
    public function readPermissionCallback($input)
    {
        return AbilityPermission::check(CapMng::CAP_BASIC);
    }

    /**
     * Permission check of the write abilities: requires CAP_CREATE; staging-guarded.
     *
     * @param mixed $input Ability input (unused).
     *
     * @return bool|WP_Error
     */
    public function writePermissionCallback($input)
    {
        return AbilityPermission::check(CapMng::CAP_CREATE);
    }

    /**
     * @param mixed $input Ability input; accepts optional `limit`, `status` and `type`.
     *
     * @return array{count: int, backups: list<array{id: int, name: string, created: string, status: string, size: string}>}|WP_Error
     */
    public function handleListBackups($input)
    {
        $input   = (array) $input;
        $limit   = is_numeric($input['limit'] ?? null) ? (int) $input['limit'] : BackupRequestService::LIST_LIMIT;
        $status  = is_string($input['status'] ?? null) ? $input['status'] : '';
        $type    = is_string($input['type'] ?? null) ? $input['type'] : BackupRequestService::LIST_TYPE_STANDARD;
        $backups = $this->service->listBackups($limit, $status, $type);
        if (is_wp_error($backups)) {
            return $backups;
        }

        return [
            'count'   => count($backups),
            'backups' => $backups,
        ];
    }

    /**
     * @param mixed $input Ability input (no parameters for this ability).
     *
     * @return Status
     */
    public function handleGetProtectionStatus($input): array
    {
        return $this->protectionStatus->get();
    }

    /**
     * @param mixed $input Ability input; accepts optional `scope`, `reason` and `password` strings, `template_id` and `storage_ids`.
     *
     * @return array{id: int, message: string}|WP_Error
     */
    public function handleCreateBackup($input)
    {
        $input      = (array) $input;
        $scope      = is_string($input['scope'] ?? null) ? $input['scope'] : null;
        $reason     = is_string($input['reason'] ?? null) ? $input['reason'] : '';
        $templateId = is_numeric($input['template_id'] ?? null) ? (int) $input['template_id'] : 0;
        $storageIds = is_array($input['storage_ids'] ?? null) ? array_map('intval', $input['storage_ids']) : [];
        $password   = is_string($input['password'] ?? null) ? $input['password'] : '';

        $id = $this->service->request(
            self::REQUESTER,
            $reason,
            $scope,
            AbstractPackage::EXECUTION_TYPE_API,
            $templateId,
            $storageIds,
            $password
        );
        if (is_wp_error($id)) {
            return $id;
        }

        return [
            'id'      => $id,
            'message' => __('Backup started. Poll backup-get with the id to track progress.', 'duplicator'),
        ];
    }

    /**
     * Start copying a complete Backup between the default local storage and another storage.
     *
     * @param mixed $input Ability input; requires the integers `id`, `from_storage_id` and `to_storage_id`.
     *
     * @return array{id: int, message: string}|WP_Error
     */
    public function handleTransferBackup($input)
    {
        $input = (array) $input;
        $id    = self::getInputId($input);
        $from  = is_numeric($input['from_storage_id'] ?? null) ? (int) $input['from_storage_id'] : 0;
        $to    = is_numeric($input['to_storage_id'] ?? null) ? (int) $input['to_storage_id'] : 0;

        $result = $this->service->transfer($id, $from, $to);
        if (is_wp_error($result)) {
            return $result;
        }

        return [
            'id'      => $id,
            'message' => __('Transfer started. Poll backup-get with the id to track progress.', 'duplicator'),
        ];
    }

    /**
     * @param mixed $input Ability input; requires a positive integer `id`.
     *
     * @return array{
     *   id: int, status: string, name: string, created: string, size: string, can_proceed: bool,
     *   message: string, details_url: string, is_full_backup: bool, exclusions: string[],
     *   warnings: array<int, array{code: int, label: string, message: string}>,
     *   failure: array{reason: string, fixes: list<array{error: string, troubleshooting: string[], code_snippet: string}>},
     *   storages: list<array{id: int, name: string, status: string, has_copy: bool, reason: string}>
     * }|WP_Error
     */
    public function handleGetBackupStatus($input)
    {
        $id = self::getInputId($input);

        $status = $this->service->getStatus($id);
        if (is_wp_error($status)) {
            return $status;
        }

        return ['id' => $id] + $status;
    }

    /**
     * @param mixed $input Ability input
     *
     * @return int The id passed, 0 when missing or not numeric
     */
    public static function getInputId($input): int
    {
        $input = (array) $input;
        return (is_numeric($input['id'] ?? null) ? (int) $input['id'] : 0);
    }
}
