<?php

/**
 * AI section appended to the Plugin section of Settings > General
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

use Duplicator\Addons\AiReadyAddon\GeneralSettingsSection;
use Duplicator\Addons\AiReadyAddon\ToolsAiPage;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Libs\Snap\SnapString;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng $tplMng
 */
?>
<label class="lbl-larger">
    <?php esc_html_e("AI Assistants", 'duplicator'); ?>
</label>
<div class="margin-bottom-1">
    <input
        type="checkbox"
        name="<?php echo esc_attr(GeneralSettingsSection::INPUT_NAME); ?>"
        id="<?php echo esc_attr(GeneralSettingsSection::INPUT_NAME); ?>"
        value="1"
        class="margin-0"
        <?php checked(GeneralSettingsSection::isUnsafeAllowed()); ?>>
    <label for="<?php echo esc_attr(GeneralSettingsSection::INPUT_NAME); ?>">
        <?php esc_html_e('Allow AI assistants to run sensitive actions', 'duplicator'); ?>
    </label>
    <p class="description">
        <?php
        echo esc_html(SnapString::oneLine(__(
            'Sensitive actions delete or change things, or give out the recovery link: deleting backups, schedules, templates
            and storages, changing settings and the number of backups a storage keeps, starting AutoTune, purging orphaned
            backup files, and setting or reading the recovery point.',
            'duplicator'
        )));
        ?>
    </p>
    <p class="description">
        <?php
        printf(
            /* translators: %s: link to Tools > AI */
            esc_html__('When checked, an assistant can run them; it is asked to confirm each one with you first. %s', 'duplicator'),
            '<a href="' . esc_url(ControllersManager::getMenuLink(
                ControllersManager::TOOLS_SUBMENU_SLUG,
                ToolsAiPage::L2_SLUG_AI
            )) . '">' . esc_html__('More info and all actions in Tools > AI', 'duplicator') . '</a>'
        );
        ?>
    </p>
</div>
