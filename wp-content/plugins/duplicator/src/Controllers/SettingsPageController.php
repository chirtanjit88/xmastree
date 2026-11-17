<?php

/**
 * Settings page controller
 */

namespace Duplicator\Controllers;

use Duplicator\Models\GlobalEntity;
use Duplicator\Models\ActivityLog\LogEventSettingsChange;
use Duplicator\Core\CapMng;
use Duplicator\Core\Constants;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Controllers\AbstractMenuPageController;
use Duplicator\Core\Controllers\PageAction;
use Duplicator\Core\Controllers\SubMenuItem;
use Duplicator\Core\Options\OptionsManager;
use Duplicator\Core\Options\Rules\ArchiveEngineRule;
use Duplicator\Core\Options\Rules\CompressionRule;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Shell\Shell;
use Duplicator\Libs\Shell\ShellZipUtils;
use Duplicator\Libs\Snap\SnapServer;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Package\Archive\PackageArchive;
use Duplicator\Package\ClientSideKick;
use Duplicator\Utils\AsyncSetupActions;
use Duplicator\Libs\WpUtils\WpDbUtils;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\Settings\BackupApiSettings;
use Duplicator\Models\Settings\GeneralApiSettings;
use Duplicator\Models\Settings\StorageApiSettings;
use Duplicator\Utils\Logging\TraceLogMng;
use Duplicator\Utils\Settings\MigrateSettings;
use Duplicator\Utils\Settings\ServerThrottle;
use Exception;

class SettingsPageController extends AbstractMenuPageController
{
    const NONCE_ACTION = 'dupli-settings-package';

    /**
     * tabs menu
     */
    const L2_SLUG_GENERAL         = 'general';
    const L2_SLUG_GENERAL_MIGRATE = 'migrate';
    const L2_SLUG_PACKAGE         = 'package';
    const L2_SLUG_STORAGE         = 'storage';
    /*
     * action types
     */
    const ACTION_GENERAL_SAVE          = 'save';
    const ACTION_GENERAL_TRACE         = 'trace';
    const ACTION_PACKAGE_ADVANCED_SAVE = 'pack-adv-save';
    const ACTION_PACKAGE_BASIC_SAVE    = 'pack-basic-save';
    const ACTION_RESET_SETTINGS        = 'reset-settings';
    const ACTION_SAVE_STORAGE          = 'save-storage';
    const ACTION_SAVE_STORAGE_SSL      = 'save-storage-ssl';
    const ACTION_SAVE_STORAGE_OPTIONS  = 'save-storage-options';
    const ACTION_IMPORT_SETTINGS       = 'import-settings';

    /**
     * Class constructor
     */
    protected function __construct()
    {
        $this->parentSlug   = ControllersManager::MAIN_MENU_SLUG;
        $this->pageSlug     = ControllersManager::SETTINGS_SUBMENU_SLUG;
        $this->pageTitle    = __('Settings', 'duplicator');
        $this->menuLabel    = __('Settings', 'duplicator');
        $this->capatibility = CapMng::CAP_SETTINGS;
        $this->menuPos      = 60;

        add_filter('duplicator_sub_menu_items_' . $this->pageSlug, [$this, 'getBasicSubMenus']);
        add_filter('duplicator_sub_level_default_tab_' . $this->pageSlug, [$this, 'getSubMenuDefaults'], 10, 2);
        add_action('duplicator_render_page_content_' . $this->pageSlug, [$this, 'renderContent'], 10, 2);
        add_filter('duplicator_page_actions_' . $this->pageSlug, [$this, 'pageActions']);
    }

    /**
     * Return sub menus for current page
     *
     * @param SubMenuItem[] $subMenus sub menus list
     *
     * @return SubMenuItem[]
     */
    public function getBasicSubMenus($subMenus)
    {
        $subMenus[] = new SubMenuItem(self::L2_SLUG_GENERAL, __('General', 'duplicator'), '', true, 10);
        $subMenus[] = new SubMenuItem(self::L2_SLUG_PACKAGE, __('Backups', 'duplicator'), '', true, 20);
        $subMenus[] = new SubMenuItem(self::L2_SLUG_STORAGE, __('Storage', 'duplicator'), '', true, 50);
        $subMenus[] = new SubMenuItem(self::L2_SLUG_GENERAL_MIGRATE, __('Import/Export Settings', 'duplicator'), '', true, 70);

        return $subMenus;
    }

    /**
     * Return slug default for parent menu slug
     *
     * @param string $slug   current default
     * @param string $parent parent for default
     *
     * @return string default slug
     */
    public function getSubMenuDefaults($slug, $parent)
    {
        switch ($parent) {
            case '':
                return self::L2_SLUG_GENERAL;
            default:
                return $slug;
        }
    }

    /**
     * Return actions for current page
     *
     * @param PageAction[] $actions actions lists
     *
     * @return PageAction[]
     */
    public function pageActions($actions)
    {
        $actions[] = new PageAction(
            self::ACTION_GENERAL_SAVE,
            [
                $this,
                'saveGeneral',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_GENERAL,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_GENERAL_TRACE,
            [
                $this,
                'traceGeneral',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_GENERAL,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_PACKAGE_BASIC_SAVE,
            [
                $this,
                'savePackage',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_PACKAGE,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_RESET_SETTINGS,
            [
                $this,
                'resetSettings',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_GENERAL,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_SAVE_STORAGE,
            [
                $this,
                'saveStorageGeneral',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_STORAGE,
            ]
        );
        $actions[] = new PageAction(
            self::ACTION_IMPORT_SETTINGS,
            [
                $this,
                'importSettings',
            ],
            [
                $this->pageSlug,
                self::L2_SLUG_GENERAL_MIGRATE,
            ]
        );
        return $actions;
    }


    /**
     * Render page content
     *
     * @param string[] $currentLevelSlugs current menu slugs
     * @param string   $innerPage         current inner page, empty if not set
     *
     * @return void
     */
    public function renderContent($currentLevelSlugs, $innerPage): void
    {
        switch ($currentLevelSlugs[1]) {
            case self::L2_SLUG_GENERAL:
                TplMng::getInstance()->render('admin_pages/settings/general/general');
                break;
            case self::L2_SLUG_GENERAL_MIGRATE:
                TplMng::getInstance()->render('admin_pages/settings/migrate_settings/migrate_page');
                break;
            case self::L2_SLUG_PACKAGE:
                TplMng::getInstance()->render(
                    'admin_pages/settings/backup/backup_settings',
                    array_merge(self::getAdvancedSettingsData(), self::getOptionsAvailabilityData())
                );
                break;
            case self::L2_SLUG_STORAGE:
                TplMng::getInstance()->render('admin_pages/settings/storage/storage_settings');
                break;
        }
    }

    /**
     * Prepare template data for the advanced backup settings section
     *
     * @return array<string, mixed>
     */
    private static function getAdvancedSettingsData(): array
    {
        $dGlobal      = DynamicGlobalEntity::getInstance();
        $detectedAuth = SnapServer::detectBasicAuthCredentials();
        $authMode     = $dGlobal->getValString(DynamicGlobalEntity::BASIC_AUTH_MODE_KEY);

        $savedAuthUser = $dGlobal->getValString(DynamicGlobalEntity::BASIC_AUTH_USER_KEY);
        $savedAuthPass = $dGlobal->getValString(DynamicGlobalEntity::BASIC_AUTH_PASSWORD_KEY);

        $detectedAuthUser = $detectedAuth !== null ? $detectedAuth['user'] : '';

        // In custom mode warn when the server reports different credentials than the stored ones
        $showMismatchWarning = $authMode === 'custom'
            && $detectedAuth !== null
            && $savedAuthUser !== ''
            && $detectedAuth['user'] !== $savedAuthUser;

        $isShellZipAvailable = (ShellZipUtils::getShellExecZipPath() != null);
        $shellZipData        = ['hasShellZip' => $isShellZipAvailable];
        if (!$isShellZipAvailable) {
            $scanPaths        = PackageArchive::getScanPaths();
            $shellZipProblems = ShellZipUtils::getShellExecZipProblems();

            $shellZipData['multiScanPath'] = count($scanPaths) > 1;
            $shellZipData['problems']      = $shellZipProblems;
            $shellZipData['isWindows']     = SnapServer::isWindows();
        }

        return [
            'basicAuthMode'       => $authMode,
            'showMismatchWarning' => $showMismatchWarning,
            'detectedAuthUser'    => $detectedAuthUser,
            'savedAuthUser'       => $savedAuthUser,
            'savedAuthPass'       => $savedAuthPass,
            'shellZipData'        => $shellZipData,
        ];
    }

    /**
     * Availability matrix data for the gated backup options: the dependency
     * matrix the generic JS uses to update the children (disabled state and
     * warning icons) when a parent value changes. The matrix entry adds the
     * DOM field names to the manager data: reasons are stripped to plain text
     * for the tooltips. The static decorations are rendered by the templates
     * through OptionsUIHelper.
     *
     * @return array<string, mixed>
     */
    private static function getOptionsAvailabilityData(): array
    {
        $manager = OptionsManager::getInstance();

        $compressionMatrix = $manager->availabilityMatrix(CompressionRule::OPTION_KEY);
        foreach ($compressionMatrix['entries'] as $comboKey => $outcomes) {
            foreach ($outcomes as $valueKey => $outcome) {
                $compressionMatrix['entries'][$comboKey][$valueKey]['reasons'] = array_map('wp_strip_all_tags', $outcome['reasons']);
            }
        }

        return [
            'availabilityMatrix' => [
                CompressionRule::OPTION_KEY => array_merge($compressionMatrix, [
                    'field'        => GlobalEntity::ARCHIVE_COMPRESSION_KEY,
                    'parentFields' => [ArchiveEngineRule::OPTION_KEY => GlobalEntity::ARCHIVE_BUILD_MODE_KEY],
                ]),
            ],
        ];
    }

    /**
     * Save general settings
     *
     * @return array<string, mixed>
     */
    public function saveGeneral(): array
    {
        $recipients  = filter_input(INPUT_POST, '_email_summary_recipients', FILTER_SANITIZE_EMAIL, [
            'flags'   => FILTER_REQUIRE_ARRAY,
            'options' => ['default' => []],
        ]);
        $values      = [
            'uninstall_settings'            => SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'uninstall_settings'),
            'uninstall_packages'            => SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'uninstall_packages'),
            'encrypt_settings'              => SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'crypt'),
            'unload_third_party_scripts'    => SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_unhook_third_party_js'),
            'unload_third_party_styles'     => SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_unhook_third_party_css'),
            'email_summary_frequency'       => SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, '_email_summary_frequency'),
            'email_summary_recipients'      => array_values(array_filter((array) $recipients, static fn($email): bool => $email !== '')),
            'usage_tracking'                => SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'usage_tracking'),
            // The form checkbox disables the notices
            'product_notices'               => !SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'dup_am_notices'),
            'trace_log_max_size_mb'         => SnapUtil::sanitizeIntInput(INPUT_POST, 'trace_max_size', TraceLogMng::DEFAULT_MAX_TOTAL_SIZE / MB_IN_BYTES),
            'activity_log_retention_months' => SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'activity_log_retention_months', 0),
        ];
        $loggingMode = SnapUtil::sanitizeStrictInput(SnapUtil::INPUT_REQUEST, '_logging_mode');
        if ($loggingMode === 'on' || $loggingMode === 'off') {
            $values['trace_log'] = ($loggingMode === 'on');
        }

        try {
            (new GeneralApiSettings())->setFromArray($values);
        } catch (Exception $e) {
            return [
                'saveSuccess'  => false,
                'errorMessage' => $e->getMessage(),
            ];
        }

        return [
            'saveSuccess'    => true,
            'successMessage' => __("General settings updated.", 'duplicator'),
        ];
    }

    /**
     * Save storage general settings
     *
     * @return array<string, mixed>
     */
    public function saveStorageGeneral(): array
    {
        $purgeMode = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, GlobalEntity::PURGE_BACKUP_RECORDS_KEY, 0);
        try {
            (new StorageApiSettings())->setFromArray([
                'storage_htaccess_off'         => SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, '_storage_htaccess_off'),
                'storage_ssl_use_server_certs' => SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, GlobalEntity::SSL_USE_SERVER_CERTS_KEY),
                'storage_ssl_disable_verify'   => SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, GlobalEntity::SSL_DISABLE_VERIFY_KEY),
                'storage_ipv4_only'            => SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, GlobalEntity::IPV4_ONLY_KEY),
                'backup_records_purge'         => (string) (array_search($purgeMode, StorageApiSettings::PURGE_MODES, true) ?: 'archive_removed'),
            ]);
        } catch (Exception $e) {
            return [
                'saveSuccess'  => false,
                'errorMessage' => __('Can\'t update storage settings.', 'duplicator') . ' ' . $e->getMessage(),
            ];
        }

        return [
            'saveSuccess'    => true,
            'successMessage' => __('Storage settings updated.', 'duplicator'),
        ];
    }

    /**
     * Migrate settings
     *
     * @return array<string, mixed>
     */
    public function importSettings(): array
    {
        $inputData = filter_input_array(INPUT_POST, [
            'import-opts' => [
                'filter'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => [
                    'default' => [],
                ],
            ],
        ]);

        if (empty($inputData['import-opts'])) {
            return ['errorMessage' => __('No import options selected.', 'duplicator')];
        }

        if (!isset($_FILES['import-file']['tmp_name'])) {
            return ['errorMessage' => __('No file uploaded.', 'duplicator')];
        }

        $filePath = SnapUtil::sanitizeNSCharsNewlineTabs($_FILES["import-file"]["tmp_name"]);
        try {
            if (MigrateSettings::import($filePath, $inputData['import-opts']) == false) {
                return ['errorMessage' => __('Couldn\'t import settings.', 'duplicator')];
            }
        } catch (Exception $ex) {
            return ['errorMessage' => sprintf(__('Couldn\'t import settings. Error: %s', 'duplicator'), $ex->getMessage())];
        }

        // Log the settings import action
        LogEventSettingsChange::create(
            LogEventSettingsChange::SUB_TYPE_IMPORT_EXPORT,
            [
                'changes'     => [], // Import doesn't track individual changes
                'action_type' => 'settings_import',
            ]
        );

        return ['successMessage' => __('Settings imported.', 'duplicator')];
    }

    /**
     * Reset all user settings and redirects to the settings page
     *
     * @return array<string, mixed>
     */
    public function resetSettings(): array
    {
        $result = ['saveSuccess' => false];

        $global = GlobalEntity::getInstance();

        // Capture before values for logging (before reset)
        $beforeValues = ['all_standard_settings' => 'existing_values'];

        if ($global->resetUserSettings() && $global->save()) {
            $result['successMessage'] = __('Settings reset to defaults successfully', 'duplicator');
            $result['saveSuccess']    = true;

            // Log the settings reset action
            LogEventSettingsChange::create(
                LogEventSettingsChange::SUB_TYPE_GENERAL,
                [
                    'changes'     => [], // Reset doesn't track individual changes
                    'action_type' => 'settings_reset',
                ]
            );
        } else {
            $result['errorMessage'] = __('Failed to reset settings.', 'duplicator');
            $result['saveSuccess']  = false;
        }

        TraceLogMng::getInstance()->setMaxTotalSize(TraceLogMng::DEFAULT_MAX_TOTAL_SIZE);
        return $result;
    }

    /**
     * Update trace mode
     *
     * @return array<string, mixed>
     */
    public function traceGeneral(): array
    {
        $loggingMode = SnapUtil::sanitizeStrictInput(SnapUtil::INPUT_REQUEST, '_logging_mode');
        if ($loggingMode !== 'on' && $loggingMode !== 'off') {
            return [
                'saveSuccess'  => false,
                'errorMessage' => __("Trace mode not valid.", 'duplicator'),
            ];
        }

        (new GeneralApiSettings())->setFromArray(['trace_log' => ($loggingMode === 'on')]);

        return [
            'saveSuccess'    => true,
            'successMessage' => ($loggingMode === 'on' ?
                __("Trace settings have been turned on.", 'duplicator') :
                __("Trace settings have been turned off.", 'duplicator')),
        ];
    }

    /**
     * Resolve the installer name mode from a submitted value.
     *
     * Falls back to the secure hashed mode when the value is missing or
     * unrecognized, so a malformed request cannot downgrade to the predictable
     * simple name; an explicit simple selection is still honored.
     *
     * @param string $submittedMode Raw installer_name_mode input value
     *
     * @return string One of the GlobalEntity::INSTALLER_NAME_MODE_* constants
     */
    private static function resolveInstallerNameMode(string $submittedMode): string
    {
        return $submittedMode === GlobalEntity::INSTALLER_NAME_MODE_SIMPLE
            ? GlobalEntity::INSTALLER_NAME_MODE_SIMPLE
            : GlobalEntity::INSTALLER_NAME_MODE_WITH_HASH;
    }

    /**
     * Settings API name of a value the form posts in its stored form
     *
     * @param array<string,int|string> $map     Stored value by API name
     * @param mixed                    $value   Posted value
     * @param ?string                  $default API name when the value is not one of the map
     *
     * @return ?string
     */
    private static function formChoice(array $map, $value, ?string $default = null): ?string
    {
        foreach ($map as $name => $stored) {
            if ($value !== null && $value !== false && (string) $stored === (string) $value) {
                return $name;
            }
        }
        return $default;
    }

    /**
     * Save Backup basic settings
     *
     * @return array<string, mixed>
     */
    public function savePackage(): array
    {
        $global   = GlobalEntity::getInstance();
        $settings = new BackupApiSettings();

        $int  = static fn(string $name) => filter_input(INPUT_POST, $name, FILTER_VALIDATE_INT);
        $bool = static fn(string $name): bool => (bool) filter_input(INPUT_POST, $name, FILTER_VALIDATE_BOOLEAN);

        $values = [
            'php_dump_mode'         => self::formChoice(BackupApiSettings::THREAD_MODES, $int('_phpdump_mode'), 'multi_threaded'),
            'mysqldump_path'        => SnapUtil::sanitizeDefaultInput(INPUT_POST, '_package_mysqldump_path'),
            'archive_compression'   => $bool(GlobalEntity::ARCHIVE_COMPRESSION_KEY),
            'ziparchive_mode'       => self::formChoice(BackupApiSettings::THREAD_MODES, $int(GlobalEntity::ZIPARCHIVE_MODE_KEY), 'multi_threaded'),
            'ziparchive_validate'   => $bool(GlobalEntity::ZIPARCHIVE_VALIDATION_KEY),
            'ziparchive_buffer_mb'  => $int(GlobalEntity::ZIPARCHIVE_CHUNK_SIZE_IN_MB_KEY) ?? GlobalEntity::getDefaultZipArchiveChunkSize(),
            'max_build_minutes'     => $int(GlobalEntity::MAX_PACKAGE_RUNTIME_IN_MIN_KEY) ?? Constants::DEFAULT_MAX_PACKAGE_RUNTIME_IN_MIN,
            'max_transfer_minutes'  => $int(GlobalEntity::MAX_PACKAGE_TRANSFER_TIME_IN_MIN_KEY) ?? Constants::DEFAULT_MAX_PACKAGE_TRANSFER_TIME_IN_MIN,
            'server_load_reduction' => self::formChoice(BackupApiSettings::SERVER_LOADS, $int(GlobalEntity::SERVER_LOAD_REDUCTION_KEY), 'off'),
            'installer_name'        => self::formChoice(
                BackupApiSettings::INSTALLER_NAMES,
                self::resolveInstallerNameMode(SnapUtil::sanitizeDefaultInput(INPUT_POST, GlobalEntity::INSTALLER_NAME_MODE_KEY)),
                'hashed'
            ),
            'client_side_kickoff'   => self::formChoice(
                BackupApiSettings::KICKOFF_MODES,
                SnapUtil::sanitizeTextInput(INPUT_POST, 'override_kickoff', 'auto'),
                'auto'
            ),
            'ajax_protocol'         => SnapUtil::sanitizeTextInput(INPUT_POST, 'override_ajax_protocol', 'auto'),
            'ajax_url'              => SnapUtil::sanitizeTextInput(INPUT_POST, 'override_ajax_url', ''),
            'use_home_path_as_root' => $bool(GlobalEntity::HOMEPATH_AS_ABSPATH_KEY),
            'skip_archive_scan'     => $bool('_skip_archive_scan'),
            'basic_auth_mode'       => SnapUtil::sanitizeTextInput(INPUT_POST, DynamicGlobalEntity::BASIC_AUTH_MODE_KEY, 'auto'),
            'cleanup_mode'          => self::formChoice(BackupApiSettings::CLEANUP_MODES, $int(GlobalEntity::CLEANUP_MODE_KEY), 'off'),
            'cleanup_email'         => (string) filter_input(INPUT_POST, GlobalEntity::CLEANUP_EMAIL_KEY, FILTER_VALIDATE_EMAIL),
            'cleanup_after_hours'   => max(1, (int) ($int(GlobalEntity::AUTO_CLEANUP_HOURS_KEY) ?? 24)),
        ];
        // A disabled radio doesn't submit: the stored engine is kept
        $dbMode = SnapUtil::sanitizeDefaultInput(INPUT_POST, '_package_dbmode');
        if ($dbMode === 'mysql' || $dbMode === 'php') {
            $values['database_engine'] = ($dbMode === 'mysql' ? 'mysqldump' : 'php');
        }
        $buildMode = self::formChoice(BackupApiSettings::ARCHIVE_ENGINES, $int(GlobalEntity::ARCHIVE_BUILD_MODE_KEY));
        if ($buildMode !== null) {
            $values['archive_engine'] = $buildMode;
        }
        $queryLimit = $int('_package_mysqldump_qrylimit') ?? Constants::DEFAULT_MYSQL_DUMP_CHUNK_SIZE;
        if (isset(Constants::MYSQL_DUMP_CHUNK_SIZES[$queryLimit])) {
            $values['mysqldump_query_limit'] = Constants::MYSQL_DUMP_CHUNK_SIZES[$queryLimit];
        }
        $values['mysqldump_options'] = [];
        foreach ($global->getMysqldumpOptions() as $option) {
            $values['mysqldump_options'][$option->getOptionName()] = (bool) filter_input(INPUT_POST, $option->getInputName(), FILTER_VALIDATE_BOOLEAN);
        }
        if ($values['basic_auth_mode'] === 'custom') {
            $values['basic_auth_user']     = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'basic_auth_user', '');
            $values['basic_auth_password'] = stripslashes(
                SnapUtil::sanitizeNSCharsNewlineTrim(SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'basic_auth_password', ''))
            );
        }

        try {
            $rejectedOptions = $settings->setFromForm($values);
        } catch (Exception $e) {
            return [
                'saveSuccess'  => false,
                'errorMessage' => $e->getMessage(),
            ];
        }
        $result = [
            'saveSuccess'    => true,
            'successMessage' => __("Backup Settings Saved.", 'duplicator'),
        ];

        $newKickoffOverride = BackupApiSettings::KICKOFF_MODES[$values['client_side_kickoff']];
        $newAjaxProtocol    = DynamicGlobalEntity::getInstance()->getValString(ClientSideKick::AJAX_PROTOCOL_OVERRIDE_KEY) ?: 'auto';
        $newBasicAuthMode   = ($values['basic_auth_mode'] === 'custom' ? 'custom' : 'auto');

        $detectionResult = $settings->getDetection() ?? AsyncSetupActions::runDetection(true);
        $lockResult      = $detectionResult['lockResult'];

        $overrideLabels  = [
            'auto'   => __('Auto', 'duplicator'),
            'server' => __('Disabled', 'duplicator'),
            'client' => __('Enabled', 'duplicator'),
            'http'   => __('HTTP', 'duplicator'),
            'https'  => __('HTTPS', 'duplicator'),
            'custom' => __('Custom', 'duplicator'),
        ];
        $dGlobalFresh    = DynamicGlobalEntity::getInstance();
        $loopbackPassed  = $detectionResult['loopbackPass'];
        $clientSideKick  = ClientSideKick::isClientSideKickoffMode();
        $kickoffMismatch = $newKickoffOverride === 'server' && !$loopbackPassed;

        $lockMismatch = !$lockResult['sqlReliable'] && !$lockResult['fileReliable'];

        $result['successMessage'] = TplMng::getInstance()->render(
            'admin_pages/settings/backup/detection_result_message',
            [
                'kickSettingLabel'      => $overrideLabels[$newKickoffOverride],
                'ajaxSettingLabel'      => $overrideLabels[$newAjaxProtocol],
                'basicAuthSettingLabel' => $overrideLabels[$newBasicAuthMode],
                'ajaxUrl'               => ClientSideKick::getBackendAjaxUrl(),
                'sqlLockPassed'         => $lockResult['sqlReliable'],
                'fileLockPassed'        => $lockResult['fileReliable'],
                'clientSideKickoff'     => $clientSideKick,
                'basicAuthConfigured'   => $dGlobalFresh->getBasicAuthHeader() !== null,
                'basicAuthUser'         => $dGlobalFresh->getValString(DynamicGlobalEntity::BASIC_AUTH_USER_KEY),
                'kickoffOverride'       => $newKickoffOverride,
                'kickoffMismatch'       => $kickoffMismatch,
                'lockMismatch'          => $lockMismatch,
            ],
            false
        );

        if (count($rejectedOptions) > 0) {
            $result['errorMessage'] = TplMng::getInstance()->render(
                'parts/settings/adjusted_options_notice',
                ['failures' => $rejectedOptions],
                false
            );
        }

        return $result;
    }

    /**
     * Mysql dump message
     *
     * @param bool   $mysqlDumpFound Found
     * @param string $mysqlDumpPath  mysqldump path
     *
     * @return void
     */
    public static function getMySQLDumpMessage($mysqlDumpFound = false, $mysqlDumpPath = ''): void
    {
        ?>
        <?php if ($mysqlDumpFound) :
            ?>
            <span class="dup-feature-found success-color">
                <?php echo esc_html($mysqlDumpPath) ?> &nbsp;
                <small>
                    <i class="fa fa-check-circle"></i>&nbsp;<i><?php esc_html_e("Successfully Found", 'duplicator'); ?></i>
                </small>
            </span>
            <?php
        else :
            ?>
            <span class="dup-feature-notfound alert-color">
                <i class="fa fa-exclamation-triangle fa-sm" aria-hidden="true"></i>
                <?php
                self::getMySqlDumpPathProblems($mysqlDumpPath, !empty($mysqlDumpPath));
                ?>
            </span>
            <?php
        endif;
    }

    /**
     * Return purge orphan Backups action URL
     *
     * @param bool $on true turn on, false turn off
     *
     * @return string
     */
    public function getTraceActionUrl($on)
    {
        $action = $this->getActionByKey(self::ACTION_GENERAL_TRACE);
        return $this->getMenuLink(
            self::L2_SLUG_GENERAL,
            null,
            [
                'action'        => $action->getKey(),
                '_wpnonce'      => $action->getNonce(),
                '_logging_mode' => ($on ? 'on' : 'off'),
            ]
        );
    }

    /**
     * Display mysql dump path problems
     *
     * @param string $path      mysqldump path
     * @param bool   $is_custom is custom path
     *
     * @return void
     */
    public static function getMySqlDumpPathProblems($path = '', $is_custom = false): void
    {
        $available = WpDbUtils::getMySqlDumpPath();
        $default   = false;
        if ($available) {
            if ($is_custom) {
                if (!Shell::isExecutable($path)) {
                    printf(
                        esc_html_x(
                            'The mysqldump program at custom path exists but is not executable. Please check file permission
                            to resolve this problem. Please check this %1$sFAQ page%2$s for possible solution.',
                            '%1$s and %2$s are html anchor tags or link',
                            'duplicator'
                        ),
                        '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-resolve-dependency-checks') . '" target="_blank">',
                        '</a>'
                    );
                } else {
                    $default = true;
                }
            } else {
                if (!Shell::isExecutable($available)) {
                    printf(
                        esc_html_x(
                            'The mysqldump program at its default location exists but is not executable.
                            Please check file permission to resolve this problem. Please check this %1$sFAQ page%2$s for possible solution.',
                            '%1$s and %2$s are html anchor tags or link',
                            'duplicator'
                        ),
                        '<a href="' . esc_url(DUPLICATOR_DUPLICATOR_DOCS_URL . 'how-to-resolve-dependency-checks') . '" target="_blank">',
                        '</a>'
                    );
                } else {
                    $default = true;
                }
            }
        } else {
            if ($is_custom) {
                printf(
                    esc_html_x(
                        'The mysqldump program was not found at its custom path location.
                        Please check is there some typo mistake or mysqldump program exists on that location.
                        Also you can leave custom path empty to force automatic settings. If the problem persists
                        contact your server admin for the correct path. For a list of approved providers that support mysqldump %1$sclick here%2$s.',
                        '%1$s and %2$s are html anchor tags or links',
                        'duplicator'
                    ),
                    '<a href="' . esc_url(DUPLICATOR_BLOG_URL . 'best-wordpress-hosting/') . '" target="_blank">',
                    '</a>'
                );
            } else {
                esc_html_e(
                    'The mysqldump program was not found at its default location.
                    To use mysqldump, ask your host to install it or for a custom mysqldump path.',
                    'duplicator'
                );
            }
        }

        if ($default) {
            printf(
                esc_html_x(
                    'The mysqldump program was not found at its default location or the custom path below.
                    Please enter a valid path where mysqldump can run. If the problem persists contact your
                    server admin for the correct path. For a list of approved providers that support mysqldump %1$sclick here%2$s.',
                    '%1$s and %2$s are html anchor tags or links',
                    'duplicator'
                ),
                '<a href="' . esc_url(DUPLICATOR_BLOG_URL . 'best-wordpress-hosting/') . '" target="_blank">',
                '</a>'
            );
        }
    }
}
