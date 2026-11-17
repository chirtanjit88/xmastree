<?php

/**
 * DUPLICATOR AI READY ADDON
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Addons\AiReadyAddon;

use Duplicator\Core\Addons\AbstractAddonCore;
use Duplicator\Core\Bootstrap;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Models\Api\EntityApi;
use Duplicator\Core\Models\Api\ActivityLogApi;
use Duplicator\Core\Models\Api\AutoTuneApi;
use Duplicator\Core\Models\Api\MaintenanceApi;
use Duplicator\Core\Models\Api\OperationApi;
use Duplicator\Core\Models\Api\SettingsApi;
use Duplicator\Core\Models\Api\SiteInfo;
use Duplicator\Package\BackupRequestService;
use Duplicator\Package\ProtectionStatus;

/**
 * Main class for the AiReadyAddon: hooks the Abilities API registrars on init.
 */
class AiReadyAddon extends AbstractAddonCore
{
    const ADDON_PATH = __DIR__;

    /**
     * @return void
     */
    public function init(): void
    {
        add_filter('duplicator_template_file', [self::class, 'getTemplateFile'], 10, 2);
        add_action('admin_init', [self::class, 'registerJsCss']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueStyles']);
        GeneralSettingsSection::registerStorage();
        ToolsAiPage::register();

        // Without the Abilities API (WordPress before 6.9) the addon only shows the Tools > AI tab
        if (!function_exists('wp_register_ability')) {
            return;
        }

        $service   = new BackupRequestService();
        $registrar = new AbilitiesRegistrar($service, new ProtectionStatus(), new SiteInfo($service));
        add_action('wp_abilities_api_categories_init', [$registrar, 'registerCategory']);
        add_action('wp_abilities_api_init', [$registrar, 'registerAbilities']);
        add_action('wp_abilities_api_init', [new EntityAbilities(new EntityApi()), 'registerAbilities']);
        add_action('wp_abilities_api_init', [new SettingsAbilities(new SettingsApi()), 'registerAbilities']);
        add_action('wp_abilities_api_init', [new ActivityLogAbilities(new ActivityLogApi()), 'registerAbilities']);
        add_action('wp_abilities_api_init', [new ToolsAbilities(new AutoTuneApi(), new MaintenanceApi()), 'registerAbilities']);
        add_action('wp_abilities_api_init', [new OperationAbilities(new OperationApi()), 'registerAbilities']);
        GeneralSettingsSection::register();
    }

    /**
     * Register styles
     *
     * @return void
     */
    public static function registerJsCss(): void
    {
        if (wp_doing_ajax()) {
            return;
        }

        $min = Bootstrap::getMinPrefix();
        wp_register_style(
            'dupli-addon-aireadyaddon',
            self::getAddonUrl() . "/assets/css/aireadyaddon{$min}.css",
            [
                'dupli-plugin-global-style',
                'dupli-main',
            ],
            DUPLICATOR_VERSION
        );
    }

    /**
     * Enqueue CSS styles on Duplicator admin pages only
     *
     * @return void
     */
    public static function enqueueStyles(): void
    {
        if (!ControllersManager::getInstance()->isDuplicatorPage()) {
            return;
        }
        wp_enqueue_style('dupli-addon-aireadyaddon');
    }

    /**
     * @param string $path    Template path
     * @param string $slugTpl Template slug
     *
     * @return string
     */
    public static function getTemplateFile($path, $slugTpl): string
    {
        if (strpos((string) $slugTpl, 'aireadyaddon/') === 0) {
            return self::getAddonPath() . '/template/' . $slugTpl . '.php';
        }
        return (string) $path;
    }

    /**
     * @return string
     */
    public static function getAddonPath(): string
    {
        return self::ADDON_PATH;
    }

    /**
     * @return string
     */
    public static function getAddonFile(): string
    {
        return __FILE__;
    }
}
