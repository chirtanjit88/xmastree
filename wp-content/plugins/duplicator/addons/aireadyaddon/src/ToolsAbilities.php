<?php

/**
 * Abilities of AutoTune and of the Tools maintenance operations
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Addons\AiReadyAddon;

use Duplicator\Core\CapMng;
use Duplicator\Core\Models\Api\AutoTuneApi;
use Duplicator\Core\Models\Api\MaintenanceApi;
use Duplicator\Libs\Snap\SnapString;
use stdClass;

/**
 * Registers autotune-status, autotune-start, autotune-abort and the tools maintenance abilities.
 */
final class ToolsAbilities
{
    const AUTOTUNE_STATUS              = 'duplicator/autotune-status';
    const AUTOTUNE_START               = 'duplicator/autotune-start';
    const AUTOTUNE_ABORT               = 'duplicator/autotune-abort';
    const TOOLS_PURGE_ORPHANS          = 'duplicator/tools-purge-orphans';
    const TOOLS_CLEAN_TEMP             = 'duplicator/tools-clean-temp';
    const TOOLS_REMOVE_INSTALLER_FILES = 'duplicator/tools-remove-installer-files';

    private AutoTuneApi $autoTune;

    private MaintenanceApi $maintenance;

    /**
     * @param AutoTuneApi    $autoTune    AutoTune service
     * @param MaintenanceApi $maintenance Maintenance service
     */
    public function __construct(AutoTuneApi $autoTune, MaintenanceApi $maintenance)
    {
        $this->autoTune    = $autoTune;
        $this->maintenance = $maintenance;
    }

    /**
     * @return void
     */
    public function registerAbilities(): void
    {
        $noInput = [
            'type'       => 'object',
            'properties' => new stdClass(),
            'default'    => new stdClass(),
        ];
        $status  = self::getStatusSchema();

        AbilityRegistration::register(self::AUTOTUNE_STATUS, [
            'label'               => __('Get AutoTune Status', 'duplicator'),
            'description'         => SnapString::oneLine(__(
                'Returns the AutoTune session: AutoTune runs test backups to find the build settings that work on this server.
                status is none when it never ran; each attempt has its backup, the configuration it tried and its outcome.
                The build settings it chose are in settings-list.',
                'duplicator'
            )),
            'execute_callback'    => fn($input): array => $this->autoTune->getStatus(),
            'permission_callback' => static fn($input) => AbilityPermission::check(CapMng::CAP_SETTINGS),
            'input_schema'        => $noInput,
            'output_schema'       => $status,
            'annotations'         => ['readonly' => true],
        ]);

        AbilityRegistration::register(self::AUTOTUNE_START, [
            'label'               => __('Start AutoTune', 'duplicator'),
            'description'         => SnapString::oneLine(__(
                'Starts an AutoTune session: it runs test backups on the default local storage, changing the build settings
                until a backup succeeds, and pauses the active schedules meanwhile. Poll autotune-status until status is no
                longer running. Works only when sensitive actions are allowed in Duplicator > Settings > General.',
                'duplicator'
            )),
            'execute_callback'    => fn($input) => $this->autoTune->start(),
            'permission_callback' => static function ($input) {
                $settings = AbilityPermission::check(CapMng::CAP_SETTINGS);
                return ($settings === true ? AbilityPermission::check(CapMng::CAP_CREATE) : $settings);
            },
            'input_schema'        => $noInput,
            'output_schema'       => $status,
            'annotations'         => [
                'readonly'    => false,
                'destructive' => false,
            ],
        ], true);

        AbilityRegistration::register(self::AUTOTUNE_ABORT, [
            'label'               => __('Abort AutoTune', 'duplicator'),
            'description'         => SnapString::oneLine(__(
                'Stops the running AutoTune session and cancels its test backup at the next step of the worker: until then
                status stays running with stop_reason set. Poll autotune-status until status is aborted.',
                'duplicator'
            )),
            'execute_callback'    => fn($input) => $this->autoTune->abort(),
            'permission_callback' => static fn($input) => AbilityPermission::check(CapMng::CAP_SETTINGS),
            'input_schema'        => $noInput,
            'output_schema'       => $status,
            'annotations'         => [
                'readonly'    => false,
                'destructive' => false,
            ],
        ]);

        $fileList = static fn(string $description): array => [
            'type'        => 'array',
            'items'       => ['type' => 'string'],
            'description' => $description,
        ];

        AbilityRegistration::register(self::TOOLS_PURGE_ORPHANS, [
            'label'               => __('Purge Orphaned Backup Files', 'duplicator'),
            'description'         => SnapString::oneLine(__(
                'Deletes the backup files in the default local storage that no backup owns, as Tools > General does.
                Works only when sensitive actions are allowed in Duplicator > Settings > General.',
                'duplicator'
            )),
            'execute_callback'    => fn($input) => $this->maintenance->purgeOrphans(),
            'permission_callback' => static fn($input) => AbilityPermission::check(CapMng::CAP_BASIC),
            'input_schema'        => $noInput,
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'deleted' => $fileList('Names of the deleted files.'),
                    'failed'  => $fileList('Names of the files that could not be deleted.'),
                    'message' => ['type' => 'string'],
                ],
            ],
            'annotations'         => [
                'readonly'    => false,
                'destructive' => true,
            ],
        ]);

        AbilityRegistration::register(self::TOOLS_CLEAN_TEMP, [
            'label'               => __('Clean Temporary Files', 'duplicator'),
            'description'         => __('Removes the temporary build files, as Tools > General does. Refused while a backup runs.', 'duplicator'),
            'execute_callback'    => fn($input) => $this->maintenance->cleanTemp(),
            'permission_callback' => static fn($input) => AbilityPermission::check(CapMng::CAP_BASIC),
            'input_schema'        => $noInput,
            'output_schema'       => [
                'type'       => 'object',
                'properties' => ['message' => ['type' => 'string']],
            ],
            'annotations'         => [
                'readonly'    => false,
                'destructive' => false,
            ],
        ]);

        AbilityRegistration::register(self::TOOLS_REMOVE_INSTALLER_FILES, [
            'label'               => __('Remove Installer Files', 'duplicator'),
            'description'         => SnapString::oneLine(__(
                'Removes the installer files left on the site after an installation and purges the caches, as Tools > General
                does. manual_purge lists the caches to regenerate by hand.',
                'duplicator'
            )),
            'execute_callback'    => fn($input) => $this->maintenance->removeInstallerFiles(),
            'permission_callback' => static fn($input) => AbilityPermission::check(CapMng::CAP_BASIC),
            'input_schema'        => $noInput,
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'removed'      => $fileList('Names of the removed files.'),
                    'failed'       => $fileList('Names of the files that could not be removed.'),
                    'cache_purge'  => $fileList('Caches purged.'),
                    'manual_purge' => $fileList('Caches to regenerate by hand, with how.'),
                    'message'      => ['type' => 'string'],
                ],
            ],
            'annotations'         => [
                'readonly'    => false,
                'destructive' => false,
            ],
        ]);
    }

    /**
     * @return array<string,mixed> JSON schema of the AutoTune status
     */
    private static function getStatusSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'status'      => [
                    'type'        => 'string',
                    'enum'        => AutoTuneApi::STATUS_NAMES,
                    'description' => 'Session status; none when AutoTune never ran.',
                ],
                'stop_reason' => [
                    'type'        => 'string',
                    'description' => 'Why the session stopped, or is stopping after an abort; empty while it runs.',
                ],
                'message'     => [
                    'type'        => 'string',
                    'description' => 'What went wrong when the session failed or stopped with an error.',
                ],
                'started_at'  => ['type' => 'string'],
                'ended_at'    => ['type' => 'string'],
                'deadline_at' => [
                    'type'        => 'string',
                    'description' => 'When a running session stops at the latest; empty otherwise.',
                ],
                'attempts'    => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'number'        => ['type' => 'integer'],
                            'backup_id'     => ['type' => 'integer'],
                            'configuration' => [
                                'type'        => 'string',
                                'description' => 'The build settings tried.',
                            ],
                            'outcome'       => [
                                'type' => 'string',
                                'enum' => [
                                    'running',
                                    'success',
                                    'failed',
                                    'cancelled',
                                ],
                            ],
                            'failure'       => [
                                'type'        => 'string',
                                'description' => 'Why the attempt failed; empty otherwise.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
