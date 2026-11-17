<?php

/**
 * Backup settings exposed through the settings API
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Models\Settings;

use Duplicator\Core\Constants;
use Duplicator\Core\Models\Api\AbstractApiSettings;
use Duplicator\Core\Models\Api\ApiField;
use Duplicator\Core\Models\Api\EntityApiException;
use Duplicator\Core\Options\OptionsManager;
use Duplicator\Core\Options\Requirements\OptionValidationFailure;
use Duplicator\Core\Options\Requirements\RequirementDefs;
use Duplicator\Core\Options\Rules\ArchiveEngineRule;
use Duplicator\Core\Options\Rules\CompressionRule;
use Duplicator\Core\Options\Rules\DbDumpEngineRule;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\WpUtils\WpDbUtils;
use Duplicator\Models\ActivityLog\LogEventSettingsChange;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\GlobalEntity;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Package\ClientSideKick;
use Duplicator\Utils\ActivityLog\SettingsChangeTracker;
use Duplicator\Utils\AsyncSetupActions;
use Exception;

/**
 * The settings of the Backup settings page. setFromArray() also takes what only the page sets: mysqldump_path,
 * mysqldump_options (enabled by option name), ajax_url, basic_auth_mode, basic_auth_user and basic_auth_password.
 */
final class BackupApiSettings extends AbstractApiSettings
{
    const ARCHIVE_ENGINES  = [
        'dup_archive' => PackageArchive::BUILD_MODE_DUP_ARCHIVE,
        'shell_zip'   => PackageArchive::BUILD_MODE_SHELL_EXEC,
        'ziparchive'  => PackageArchive::BUILD_MODE_ZIP_ARCHIVE,
    ];
    const DATABASE_ENGINES = [
        'mysqldump' => DbDumpEngineRule::VALUE_MYSQLDUMP,
        'php'       => DbDumpEngineRule::VALUE_PHP,
    ];
    const THREAD_MODES     = [
        'multi_threaded'  => 0,
        'single_threaded' => 1,
    ];
    const SERVER_LOADS     = [
        'off'    => 0,
        'low'    => 1,
        'medium' => 2,
        'high'   => 3,
    ];
    const INSTALLER_NAMES  = [
        'standard' => GlobalEntity::INSTALLER_NAME_MODE_SIMPLE,
        'hashed'   => GlobalEntity::INSTALLER_NAME_MODE_WITH_HASH,
    ];
    const CLEANUP_MODES    = [
        'off'   => GlobalEntity::CLEANUP_MODE_OFF,
        'email' => GlobalEntity::CLEANUP_MODE_MAIL,
        'auto'  => GlobalEntity::CLEANUP_MODE_AUTO,
    ];
    const KICKOFF_MODES    = [
        'auto'     => 'auto',
        'enabled'  => 'client',
        'disabled' => 'server',
    ];
    /** @var string[] Keys whose change reruns the server detection */
    const DETECTION_KEYS = [
        'client_side_kickoff',
        'ajax_protocol',
        'ajax_url',
        'basic_auth_mode',
        'basic_auth_user',
        'basic_auth_password',
    ];

    /** @var bool Replace an unavailable engine with the first available one instead of refusing it */
    private bool $adjustUnavailable = false;
    /** @var array<string,OptionValidationFailure> Engines replaced by the last setFromArray() */
    private array $adjusted = [];
    /** @var ?array<string,mixed> Result of the server detection the last setFromArray() ran */
    private ?array $detection = null;

    /**
     * @return ApiField[]
     */
    public static function apiFields(): array
    {
        return [
            (new ApiField('archive_engine', ApiField::TYPE_STRING, 'Engine that builds the archive.'))
                ->enum(array_keys(self::ARCHIVE_ENGINES)),
            new ApiField('archive_compression', ApiField::TYPE_BOOLEAN, 'Whether the archive is compressed.'),
            (new ApiField('ziparchive_mode', ApiField::TYPE_STRING, 'How the ZipArchive engine runs.'))
                ->enum(array_keys(self::THREAD_MODES)),
            new ApiField('ziparchive_validate', ApiField::TYPE_BOOLEAN, 'Whether ZipArchive validates the archive after the build.'),
            (new ApiField('ziparchive_buffer_mb', ApiField::TYPE_INTEGER, 'ZipArchive buffer size in MB.'))
                ->range(1),
            (new ApiField('database_engine', ApiField::TYPE_STRING, 'Engine that exports the database.'))
                ->enum(array_keys(self::DATABASE_ENGINES)),
            (new ApiField('php_dump_mode', ApiField::TYPE_STRING, 'How the PHP database export runs.'))
                ->enum(array_keys(self::THREAD_MODES)),
            (new ApiField('mysqldump_query_limit', ApiField::TYPE_STRING, 'Size of the mysqldump queries.'))
                ->enum(array_values(Constants::MYSQL_DUMP_CHUNK_SIZES)),
            (new ApiField('max_build_minutes', ApiField::TYPE_INTEGER, 'Minutes a Backup build may run before it is stopped.'))
                ->range(0),
            (new ApiField('max_transfer_minutes', ApiField::TYPE_INTEGER, 'Minutes a storage transfer may run before it is stopped.'))
                ->range(0),
            (new ApiField('server_load_reduction', ApiField::TYPE_STRING, 'How much the build pauses to reduce the server load.'))
                ->enum(array_keys(self::SERVER_LOADS)),
            (new ApiField('installer_name', ApiField::TYPE_STRING, 'Installer file name: standard installer.php or hashed.'))
                ->enum(array_keys(self::INSTALLER_NAMES)),
            new ApiField('skip_archive_scan', ApiField::TYPE_BOOLEAN, 'Whether the archive scan is skipped.'),
            new ApiField('use_home_path_as_root', ApiField::TYPE_BOOLEAN, 'Whether the WordPress home path is used as the archive root.'),
            (new ApiField('cleanup_mode', ApiField::TYPE_STRING, 'What happens to leftover installer files: nothing, an email or an automatic cleanup.'))
                ->enum(array_keys(self::CLEANUP_MODES)),
            new ApiField('cleanup_email', ApiField::TYPE_STRING, 'Email address of the cleanup notice; empty uses the site admin email.'),
            (new ApiField('cleanup_after_hours', ApiField::TYPE_INTEGER, 'Hours before the automatic cleanup.'))
                ->range(1),
            (new ApiField('client_side_kickoff', ApiField::TYPE_STRING, 'Whether Backups start from the browser instead of the server; auto detects it.'))
                ->enum(array_keys(self::KICKOFF_MODES)),
            (new ApiField('ajax_protocol', ApiField::TYPE_STRING, 'Protocol of the worker requests; auto detects it.'))
                ->enum(['auto', 'http', 'https']),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $global  = GlobalEntity::getInstance();
        $dGlobal = DynamicGlobalEntity::getInstance();
        return [
            'archive_engine'        => array_search($global->getBuildMode(), self::ARCHIVE_ENGINES, true),
            'archive_compression'   => $global->isArchiveCompressionEnabled(),
            'ziparchive_mode'       => array_search($global->getZipArchiveMode(), self::THREAD_MODES, true),
            'ziparchive_validate'   => $global->isZipArchiveValidationEnabled(),
            'ziparchive_buffer_mb'  => $global->getZipArchiveChunkSize(),
            'database_engine'       => $global->isMysqldumpEnabled() ? 'mysqldump' : 'php',
            'php_dump_mode'         => array_search($global->getPhpDumpMode(), self::THREAD_MODES, true),
            'mysqldump_query_limit' => Constants::MYSQL_DUMP_CHUNK_SIZES[$global->getMysqldumpQueryLimit()] ?? '',
            'max_build_minutes'     => $global->getMaxPackageRuntime(),
            'max_transfer_minutes'  => $global->getMaxPackageTransferTime(),
            'server_load_reduction' => array_search($global->getServerLoadReduction(), self::SERVER_LOADS, true),
            'installer_name'        => array_search($global->getInstallerNameMode(), self::INSTALLER_NAMES, true),
            'skip_archive_scan'     => $global->isArchiveScanSkipped(),
            'use_home_path_as_root' => $global->isHomePathAsAbsolute(),
            'cleanup_mode'          => array_search($global->getCleanupMode(), self::CLEANUP_MODES, true),
            'cleanup_email'         => $global->getCleanupEmail(),
            'cleanup_after_hours'   => $global->getAutoCleanupHours(),
            'client_side_kickoff'   => array_search($dGlobal->getValString(ClientSideKick::KICKOFF_OVERRIDE_KEY), self::KICKOFF_MODES, true) ?: 'auto',
            'ajax_protocol'         => $dGlobal->getValString(ClientSideKick::AJAX_PROTOCOL_OVERRIDE_KEY) ?: 'auto',
        ];
    }

    /**
     * @return array<string,array<string,string>>
     */
    public function apiUnavailableValues(): array
    {
        $optionsMng = OptionsManager::getInstance();
        $result     = [];
        foreach (
            [
                'archive_engine'      => [
                    ArchiveEngineRule::OPTION_KEY,
                    self::ARCHIVE_ENGINES,
                ],
                'database_engine'     => [
                    DbDumpEngineRule::OPTION_KEY,
                    self::DATABASE_ENGINES,
                ],
                'archive_compression' => [
                    CompressionRule::OPTION_KEY,
                    [
                        'true'  => true,
                        'false' => false,
                    ],
                ],
            ] as $key => [$optionKey, $map]
        ) {
            $availability = $optionsMng->availability($optionKey);
            foreach ($availability->getUnavailableValues() as $value) {
                $name = array_search($value, $map, true);
                if ($name !== false) {
                    $result[$key][(string) $name] = implode(' ', $availability->getReasons($value));
                }
            }
        }
        return $result;
    }

    /**
     * Set the values from the settings page: an unavailable engine is replaced by the first available one
     *
     * @param array<string,mixed> $values Values by key
     *
     * @return array<string,OptionValidationFailure> Engines replaced
     */
    public function setFromForm(array $values): array
    {
        $this->adjustUnavailable = true;
        try {
            $this->setFromArray($values);
        } finally {
            $this->adjustUnavailable = false;
        }
        return $this->adjusted;
    }

    /**
     * Result of the server detection the last setFromArray() ran, null when it ran none
     *
     * @return ?array<string,mixed>
     */
    public function getDetection(): ?array
    {
        return $this->detection;
    }

    /**
     * @param array<string,mixed> $values Values by key
     *
     * @return void
     */
    public function validate(array $values): void
    {
        foreach (self::enumMaps() as $key => $map) {
            if (array_key_exists($key, $values)) {
                self::mapValue($key, $values[$key], $map);
            }
        }
        self::validateEmail($values, 'cleanup_email');
        if ($this->adjustUnavailable) {
            return;
        }

        $engines    = self::getEngineValues($values);
        $optionsMng = OptionsManager::getInstance();
        $messages   = [];
        foreach ($engines as $optionKey => $value) {
            $availability = $optionsMng->availability($optionKey, $engines);
            if (!$availability->isAvailable($value)) {
                $messages[] = implode(' ', $availability->getReasons($value));
            }
        }
        if (count($messages) > 0) {
            throw new EntityApiException(EntityApiException::CODE_UNAVAILABLE, implode(' ', $messages));
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
        $tracker         = new SettingsChangeTracker();
        $this->adjusted  = [];
        $this->detection = null;

        $this->setEngines($values, $tracker);
        $this->setLimits($values, $tracker);
        $this->setCleanup($values, $tracker);
        $this->setWorkerRequests($values, $tracker);

        $tracker->createLog(LogEventSettingsChange::SUB_TYPE_BACKUP);
        if (!DynamicGlobalEntity::getInstance()->save() || !GlobalEntity::getInstance()->save()) {
            throw new Exception('Unable to save the Backup settings.');
        }
        if (array_key_exists('cleanup_mode', $values) || array_key_exists('cleanup_after_hours', $values)) {
            GlobalEntity::cleanupScheduleSetup();
        }
        if (count(array_intersect(array_keys($values), self::DETECTION_KEYS)) > 0) {
            $this->detection = AsyncSetupActions::runDetection(true);
        }
    }

    /**
     * Internal value by API value of every enum setting
     *
     * @return array<string,array<string|int,mixed>>
     */
    private static function enumMaps(): array
    {
        return [
            'archive_engine'        => self::ARCHIVE_ENGINES,
            'database_engine'       => self::DATABASE_ENGINES,
            'ziparchive_mode'       => self::THREAD_MODES,
            'php_dump_mode'         => self::THREAD_MODES,
            'mysqldump_query_limit' => array_flip(Constants::MYSQL_DUMP_CHUNK_SIZES),
            'server_load_reduction' => self::SERVER_LOADS,
            'installer_name'        => self::INSTALLER_NAMES,
            'cleanup_mode'          => self::CLEANUP_MODES,
            'client_side_kickoff'   => self::KICKOFF_MODES,
        ];
    }

    /**
     * The passed engine values by option rule key
     *
     * @param array<string,mixed> $values Values by key
     *
     * @return array<string,scalar>
     */
    private static function getEngineValues(array $values): array
    {
        $engines = [];
        if (array_key_exists('archive_engine', $values)) {
            $engines[ArchiveEngineRule::OPTION_KEY] = self::mapValue('archive_engine', $values['archive_engine'], self::ARCHIVE_ENGINES);
        }
        if (array_key_exists('archive_compression', $values)) {
            $engines[CompressionRule::OPTION_KEY] = (bool) $values['archive_compression'];
        }
        if (array_key_exists('database_engine', $values)) {
            $engines[DbDumpEngineRule::OPTION_KEY] = self::mapValue('database_engine', $values['database_engine'], self::DATABASE_ENGINES);
        }
        return $engines;
    }

    /**
     * Set the archive and database engines, their modes and the mysqldump settings
     *
     * @param array<string,mixed>   $values  Values by key
     * @param SettingsChangeTracker $tracker Change tracker
     *
     * @return void
     */
    private function setEngines(array $values, SettingsChangeTracker $tracker): void
    {
        $global     = GlobalEntity::getInstance();
        $optionsMng = OptionsManager::getInstance();
        $engines    = self::getEngineValues($values);

        if (isset($engines[ArchiveEngineRule::OPTION_KEY])) {
            $tracker->addChange(
                GlobalEntity::ARCHIVE_BUILD_MODE_KEY,
                $global->getBuildMode(),
                $engines[ArchiveEngineRule::OPTION_KEY],
                'optionChanged',
                self::labels(ArchiveEngineRule::OPTION_KEY, self::ARCHIVE_ENGINES)
            );
        }
        if (isset($engines[CompressionRule::OPTION_KEY])) {
            $tracker->addChange(
                GlobalEntity::ARCHIVE_COMPRESSION_KEY,
                $global->isArchiveCompressionEnabled(),
                $engines[CompressionRule::OPTION_KEY],
                $engines[CompressionRule::OPTION_KEY] ? 'enabled' : 'disabled'
            );
        }
        if (isset($engines[DbDumpEngineRule::OPTION_KEY])) {
            $tracker->addChange(
                'package_dbmode',
                $global->isMysqldumpEnabled() ? 'mysql' : 'php',
                $values['database_engine'] === 'mysqldump' ? 'mysql' : 'php',
                'optionChanged'
            );
        }
        if (array_key_exists('ziparchive_mode', $values)) {
            $mode = self::mapValue('ziparchive_mode', $values['ziparchive_mode'], self::THREAD_MODES);
            $tracker->addChange(GlobalEntity::ZIPARCHIVE_MODE_KEY, $global->getZipArchiveMode(), $mode, 'optionChanged', array_flip(self::THREAD_MODES));
            $global->setZipArchiveMode($mode, false);
        }
        self::setSwitch(
            $tracker,
            $values,
            'ziparchive_validate',
            GlobalEntity::ZIPARCHIVE_VALIDATION_KEY,
            [
                $global,
                'isZipArchiveValidationEnabled',
            ],
            static fn(bool $value) => $global->setZipArchiveValidation($value, false)
        );
        if (array_key_exists('ziparchive_buffer_mb', $values)) {
            $value = max(1, (int) $values['ziparchive_buffer_mb']);
            $tracker->addChange(
                GlobalEntity::ZIPARCHIVE_CHUNK_SIZE_IN_MB_KEY,
                $global->getZipArchiveChunkSize(),
                $value,
                'sizeChanged',
                [
                    'fromUnit' => 'MB',
                    'toUnit'   => 'MB',
                ]
            );
            $global->setZipArchiveChunkSize($value, false);
        }
        if (array_key_exists('php_dump_mode', $values)) {
            $mode = self::mapValue('php_dump_mode', $values['php_dump_mode'], self::THREAD_MODES);
            $tracker->addChange(GlobalEntity::PACKAGE_PHPDUMP_MODE_KEY, $global->getPhpDumpMode(), $mode, 'optionChanged', array_flip(self::THREAD_MODES));
            $global->setPhpDumpMode($mode, false);
        }
        if (array_key_exists('mysqldump_query_limit', $values)) {
            $limit = (int) self::mapValue('mysqldump_query_limit', $values['mysqldump_query_limit'], array_flip(Constants::MYSQL_DUMP_CHUNK_SIZES));
            $tracker->addChange(
                GlobalEntity::PACKAGE_MYSQLDUMP_QRYLIMIT_KEY,
                $global->getMysqldumpQueryLimit(),
                $limit,
                'optionChanged',
                Constants::MYSQL_DUMP_CHUNK_SIZES
            );
            $global->setMysqldumpQueryLimit($limit, false);
        }
        if (array_key_exists('mysqldump_path', $values)) {
            $path = SnapUtil::sanitizeNSCharsNewlineTabs((string) $values['mysqldump_path']);
            $path = preg_match('/^([A-Za-z]\:)?[\/\\\\]/', $path) ? $path : '';
            $path = SnapIO::safePathUntrailingslashit((string) preg_replace('/[\'"]/m', '', $path));
            $tracker->addChange(
                GlobalEntity::PACKAGE_MYSQLDUMP_PATH_KEY,
                $global->getMysqldumpPath(),
                $path,
                'fieldChanged',
                [
                    'truncate' => true,
                    'max'      => 80,
                ]
            );
            $global->setMysqldumpPath($path, false);
            // The path changes the mysqldump detection the engine is validated against
            WpDbUtils::resetMySqlDumpPathCache();
            $optionsMng->resetRequirementResults([RequirementDefs::REQ_MYSQLDUMP_BINARY]);
        }
        if (array_key_exists('mysqldump_options', $values)) {
            $options = $global->getMysqldumpOptions();
            foreach ($options as $option) {
                $enabled = ((array) $values['mysqldump_options'])[$option->getOptionName()] ?? null;
                if ($enabled === true) {
                    $option->enabled();
                } elseif ($enabled === false) {
                    $option->disable();
                }
            }
            $global->setMysqldumpOptions($options, false);
        }

        if (count($engines) > 0) {
            $failures = $this->adjustUnavailable ? $optionsMng->applyValues($engines) : $optionsMng->setValues($engines);
            if (!$this->adjustUnavailable && count($failures) > 0) {
                $messages = [];
                foreach ($failures as $failure) {
                    $messages[] = implode(' ', $failure->getReasons());
                }
                throw new EntityApiException(EntityApiException::CODE_UNAVAILABLE, implode(' ', $messages));
            }
            $this->adjusted = $failures;
        }
    }

    /**
     * Set the build and transfer limits, the server load, the installer name and the archive scope
     *
     * @param array<string,mixed>   $values  Values by key
     * @param SettingsChangeTracker $tracker Change tracker
     *
     * @return void
     */
    private function setLimits(array $values, SettingsChangeTracker $tracker): void
    {
        $global = GlobalEntity::getInstance();

        $minutes = [
            'max_build_minutes'    => [
                GlobalEntity::MAX_PACKAGE_RUNTIME_IN_MIN_KEY,
                [
                    $global,
                    'getMaxPackageRuntime',
                ],
                [
                    $global,
                    'setMaxPackageRuntime',
                ],
            ],
            'max_transfer_minutes' => [
                GlobalEntity::MAX_PACKAGE_TRANSFER_TIME_IN_MIN_KEY,
                [
                    $global,
                    'getMaxPackageTransferTime',
                ],
                [
                    $global,
                    'setMaxPackageTransferTime',
                ],
            ],
        ];
        foreach ($minutes as $key => [$logKey, $getter, $setter]) {
            if (array_key_exists($key, $values)) {
                $value = max(0, (int) $values[$key]);
                $tracker->addChange($logKey, $getter(), $value, 'timeChanged', ['fromUnit' => 'min', 'toUnit' => 'min']);
                $setter($value, false);
            }
        }
        if (array_key_exists('server_load_reduction', $values)) {
            $level = self::mapValue('server_load_reduction', $values['server_load_reduction'], self::SERVER_LOADS);
            $tracker->addChange(
                GlobalEntity::SERVER_LOAD_REDUCTION_KEY,
                $global->getServerLoadReduction(),
                $level,
                'optionChanged',
                array_flip(self::SERVER_LOADS)
            );
            $global->setServerLoadReduction($level, false);
        }
        if (array_key_exists('installer_name', $values)) {
            $mode = self::mapValue('installer_name', $values['installer_name'], self::INSTALLER_NAMES);
            $tracker->addChange(
                GlobalEntity::INSTALLER_NAME_MODE_KEY,
                $global->getInstallerNameMode(),
                $mode,
                'optionChanged',
                array_flip(self::INSTALLER_NAMES)
            );
            $global->setInstallerNameMode($mode, false);
        }
        self::setSwitch(
            $tracker,
            $values,
            'skip_archive_scan',
            GlobalEntity::SKIP_ARCHIVE_SCAN_KEY,
            [
                $global,
                'isArchiveScanSkipped',
            ],
            static fn(bool $value) => $global->setSkipArchiveScan($value, false)
        );
        self::setSwitch(
            $tracker,
            $values,
            'use_home_path_as_root',
            GlobalEntity::HOMEPATH_AS_ABSPATH_KEY,
            [
                $global,
                'isHomePathAsAbsolute',
            ],
            static fn(bool $value) => $global->setHomePathAsAbsolute($value, false)
        );
    }

    /**
     * Set what happens to leftover installer files
     *
     * @param array<string,mixed>   $values  Values by key
     * @param SettingsChangeTracker $tracker Change tracker
     *
     * @return void
     */
    private function setCleanup(array $values, SettingsChangeTracker $tracker): void
    {
        $global = GlobalEntity::getInstance();

        if (array_key_exists('cleanup_mode', $values)) {
            $mode = self::mapValue('cleanup_mode', $values['cleanup_mode'], self::CLEANUP_MODES);
            $tracker->addChange(GlobalEntity::CLEANUP_MODE_KEY, $global->getCleanupMode(), $mode, 'optionChanged', array_flip(self::CLEANUP_MODES));
            $global->setCleanupMode($mode, false);
        }
        if (array_key_exists('cleanup_email', $values)) {
            $email = trim((string) $values['cleanup_email']);
            $email = ($email === '' ? (string) get_option('admin_email') : $email);
            $tracker->addChange(GlobalEntity::CLEANUP_EMAIL_KEY, $global->getCleanupEmail(), $email, 'fieldChanged');
            $global->setCleanupEmail($email, false);
        }
        if (array_key_exists('cleanup_after_hours', $values)) {
            $hours = max(1, (int) $values['cleanup_after_hours']);
            $tracker->addChange(
                GlobalEntity::AUTO_CLEANUP_HOURS_KEY,
                $global->getAutoCleanupHours(),
                $hours,
                'timeChanged',
                [
                    'fromUnit' => 'hour',
                    'toUnit'   => 'hour',
                ]
            );
            $global->setAutoCleanupHours($hours, false);
        }
    }

    /**
     * Set how the worker requests start and reach the site: kickoff, protocol, URL and basic auth
     *
     * @param array<string,mixed>   $values  Values by key
     * @param SettingsChangeTracker $tracker Change tracker
     *
     * @return void
     */
    private function setWorkerRequests(array $values, SettingsChangeTracker $tracker): void
    {
        $dGlobal = DynamicGlobalEntity::getInstance();

        if (array_key_exists('client_side_kickoff', $values)) {
            $dGlobal->setValString(
                ClientSideKick::KICKOFF_OVERRIDE_KEY,
                self::mapValue('client_side_kickoff', $values['client_side_kickoff'], self::KICKOFF_MODES),
                false
            );
        }
        if (array_key_exists('ajax_protocol', $values)) {
            $protocol = (string) $values['ajax_protocol'];
            $dGlobal->setValString(
                ClientSideKick::AJAX_PROTOCOL_OVERRIDE_KEY,
                in_array($protocol, ['http', 'https', 'custom'], true) ? $protocol : 'auto',
                false
            );
        }
        if (array_key_exists('ajax_url', $values)) {
            $dGlobal->setValString(ClientSideKick::AJAX_URL_OVERRIDE_KEY, SnapUtil::sanitizeNSCharsNewlineTrim((string) $values['ajax_url']), false);
        }
        if (array_key_exists('basic_auth_mode', $values)) {
            $mode = ($values['basic_auth_mode'] === 'custom' ? 'custom' : 'auto');
            $tracker->addChange(
                DynamicGlobalEntity::BASIC_AUTH_MODE_KEY,
                $dGlobal->getValString(DynamicGlobalEntity::BASIC_AUTH_MODE_KEY),
                $mode,
                'optionChanged',
                [
                    'auto'   => __('Auto', 'duplicator'),
                    'custom' => __('Custom', 'duplicator'),
                ]
            );
            $dGlobal->setValString(DynamicGlobalEntity::BASIC_AUTH_MODE_KEY, $mode, false);
        }
        if (array_key_exists('basic_auth_user', $values)) {
            $user = (string) $values['basic_auth_user'];
            $tracker->addChange('basic_auth_user', $dGlobal->getValString(DynamicGlobalEntity::BASIC_AUTH_USER_KEY), $user, 'optionChanged');
            $dGlobal->setValString(DynamicGlobalEntity::BASIC_AUTH_USER_KEY, $user, false);
        }
        if (array_key_exists('basic_auth_password', $values)) {
            $password = (string) $values['basic_auth_password'];
            $tracker->addChange(
                'basic_auth_password',
                false,
                $dGlobal->getValString(DynamicGlobalEntity::BASIC_AUTH_PASSWORD_KEY) !== $password,
                'passwordChanged'
            );
            $dGlobal->setValString(DynamicGlobalEntity::BASIC_AUTH_PASSWORD_KEY, $password, false);
        }
    }

    /**
     * @param string                   $optionKey Option rule key
     * @param array<string,int|string> $map       Internal value by API value
     *
     * @return array<int|string,string> Label by internal value
     */
    private static function labels(string $optionKey, array $map): array
    {
        $labels = [];
        foreach ($map as $value) {
            $labels[$value] = OptionsManager::getInstance()->getValueLabel($optionKey, $value);
        }
        return $labels;
    }
}
