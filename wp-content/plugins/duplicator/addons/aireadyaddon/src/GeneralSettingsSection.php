<?php

/**
 * AI section of Settings > General
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Addons\AiReadyAddon;

use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Controllers\PageAction;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\ActivityLog\LogEventSettingsChange;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Utils\ActivityLog\SettingsChangeTracker;
use Exception;

/**
 * Adds the sensitive actions setting at the end of the Plugin section of Settings > General and saves it with that page
 */
final class GeneralSettingsSection
{
    /** @var string Checkbox name */
    const INPUT_NAME = 'ai_allow_unsafe';
    /** @var string DynamicGlobalEntity key of the setting, stored under its first name */
    const ALLOW_UNSAFE_KEY = 'ai_allow_delete';
    /** @var string Core template the section is appended to */
    const PLUGIN_SETTINGS_TPL = 'admin_pages/settings/general/plugin_settings';

    /**
     * Keep the setting out of the settings export and import, and label it in the activity log; needed whether or not
     * the abilities run
     *
     * @return void
     */
    public static function registerStorage(): void
    {
        add_filter('duplicator_dynamic_skip_data_export', [self::class, 'skipExport']);
        add_filter('duplicator_capability_labels', [self::class, 'addLabel']);
    }

    /**
     * @param string[] $keys DynamicGlobalEntity keys skipped by the settings export and import
     *
     * @return string[]
     */
    public static function skipExport($keys): array
    {
        $keys   = (array) $keys;
        $keys[] = self::ALLOW_UNSAFE_KEY;
        return $keys;
    }

    /**
     * @param array<string,string> $labels Activity log labels by setting key
     *
     * @return array<string,string>
     */
    public static function addLabel($labels): array
    {
        $labels                         = (array) $labels;
        $labels[self::ALLOW_UNSAFE_KEY] = __('Allow AI assistants to run sensitive actions', 'duplicator');
        return $labels;
    }

    /**
     * @return bool Whether the site allows AI assistants to run sensitive actions
     */
    public static function isUnsafeAllowed(): bool
    {
        return DynamicGlobalEntity::getInstance()->getValBool(self::ALLOW_UNSAFE_KEY);
    }

    /**
     * @return void
     */
    public static function register(): void
    {
        add_filter(TplMng::getRenderHook(self::PLUGIN_SETTINGS_TPL), [self::class, 'render']);
        add_filter('duplicator_page_actions_' . ControllersManager::SETTINGS_SUBMENU_SLUG, [self::class, 'pageActions']);
    }

    /**
     * @param string $html Plugin section HTML
     *
     * @return string
     */
    public static function render($html): string
    {
        return $html . TplMng::getInstance()->render('aireadyaddon/settings/general_ai', [], false);
    }

    /**
     * Same key and page as the General save, so it runs on the same submit and checks the same nonce
     *
     * @param PageAction[] $actions Page actions
     *
     * @return PageAction[]
     */
    public static function pageActions($actions): array
    {
        $actions[] = new PageAction(
            SettingsPageController::ACTION_GENERAL_SAVE,
            [
                self::class,
                'save',
            ],
            [
                ControllersManager::SETTINGS_SUBMENU_SLUG,
                SettingsPageController::L2_SLUG_GENERAL,
            ]
        );
        return $actions;
    }

    /**
     * Save the setting and log the change
     *
     * @return array<string,mixed>
     */
    public static function save(): array
    {
        $allow   = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, self::INPUT_NAME);
        $dGlobal = DynamicGlobalEntity::getInstance();
        $tracker = new SettingsChangeTracker();
        $tracker->addChange(
            self::ALLOW_UNSAFE_KEY,
            $dGlobal->getValBool(self::ALLOW_UNSAFE_KEY),
            $allow,
            $allow ? 'enabled' : 'disabled'
        );
        if (!$dGlobal->setValBool(self::ALLOW_UNSAFE_KEY, $allow, true)) {
            throw new Exception(__('The AI settings could not be saved.', 'duplicator'));
        }
        $tracker->createLog(LogEventSettingsChange::SUB_TYPE_GENERAL);
        return [];
    }
}
