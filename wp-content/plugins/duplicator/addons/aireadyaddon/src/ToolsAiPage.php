<?php

/**
 * Tools > AI tab
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Addons\AiReadyAddon;

use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Controllers\SubMenuItem;
use Duplicator\Core\Models\Api\EntityApi;
use Duplicator\Core\Views\TplMng;
use Duplicator\Views\AdminNotices;

/**
 * Adds the AI tab to the Tools page: what an assistant can do, and a notice when WordPress is too old for it
 */
final class ToolsAiPage
{
    /** @var string Tools tab slug */
    const L2_SLUG_AI = 'ai';

    /**
     * @return void
     */
    public static function register(): void
    {
        $toolsSlug = ControllersManager::TOOLS_SUBMENU_SLUG;
        add_filter('duplicator_sub_menu_items_' . $toolsSlug, [self::class, 'addSubMenu']);
        add_action('duplicator_render_page_content_' . $toolsSlug, [self::class, 'renderContent'], 10, 2);
        add_filter('duplicator_admin_notices', [self::class, 'addNotices']);
    }

    /**
     * @param SubMenuItem[] $subMenus Sub menus
     *
     * @return SubMenuItem[]
     */
    public static function addSubMenu(array $subMenus): array
    {
        $subMenus[] = new SubMenuItem(self::L2_SLUG_AI, __('AI', 'duplicator'), '', true, 45);
        return $subMenus;
    }

    /**
     * @param string[] $currentLevelSlugs Current menu slugs
     * @param string   $innerPage         Current inner page
     *
     * @return void
     */
    public static function renderContent(array $currentLevelSlugs, string $innerPage): void
    {
        if (($currentLevelSlugs[1] ?? '') !== self::L2_SLUG_AI) {
            return;
        }

        $entityAbilities = [];
        foreach ((new EntityApi())->getEntityClasses() as $entityClass) {
            if ($entityClass::apiCanCreate()) {
                $entityText = __('<b>List</b>, <b>view</b>, <b>create</b> and <b>update</b> them.', 'duplicator');
            } elseif ($entityClass::apiHasWritableFields()) {
                $entityText = __('<b>List</b>, <b>view</b> and <b>update</b> them.', 'duplicator');
            } else {
                $entityText = __('<b>List</b> and <b>view</b> them.', 'duplicator');
            }
            $items = [$entityText];
            if ($entityClass::apiCanDelete()) {
                $items[] = __('<b>Delete</b> one when allowed in the settings.', 'duplicator');
            }
            if ($entityClass::apiCanCopy()) {
                $items[] = __('<b>Copy</b> one, changing the settings you name on the copy.', 'duplicator');
            }
            $actionsSummary = $entityClass::apiActionsSummary();
            if ($actionsSummary !== '') {
                $items[] = $actionsSummary;
            } else {
                foreach ($entityClass::apiActions() as $entityAction) {
                    // The description is plain text shared with the ability: bold only its leading verb
                    $items[] = (string) preg_replace('/^(\S+)/', '<b>$1</b>', esc_html($entityAction->getDescription()));
                }
            }
            $entityAbilities[$entityClass::apiSlug()] = [
                'title' => $entityClass::apiLabels()['plural'],
                'items' => $items,
            ];
        }
        TplMng::getInstance()->render('aireadyaddon/tools/ai', ['entityAbilities' => $entityAbilities]);
    }

    /**
     * @param callable[] $notices Admin notice callbacks
     *
     * @return callable[]
     */
    public static function addNotices(array $notices): array
    {
        $notices[] = [
            self::class,
            'wordPressUpdateNotice',
        ];
        return $notices;
    }

    /**
     * On this tab, ask to update WordPress when it has no Abilities API
     *
     * @return void
     */
    public static function wordPressUpdateNotice(): void
    {
        if (function_exists('wp_register_ability')) {
            return;
        }
        if (!ControllersManager::isCurrentPage(ControllersManager::TOOLS_SUBMENU_SLUG, self::L2_SLUG_AI)) {
            return;
        }

        AdminNotices::displayGeneralAdminNotice(
            '<i class="fa fa-triangle-exclamation"></i> ' . sprintf(
                /* translators: %s: required WordPress version, in bold */
                esc_html__('The AI features need %s. Update WordPress to use them.', 'duplicator'),
                '<b>' . esc_html__('WordPress 6.9 or later', 'duplicator') . '</b>'
            ),
            AdminNotices::GEN_ERROR_NOTICE,
            false
        );
    }
}
