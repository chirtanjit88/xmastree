<?php

use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Views\TplMng $tplMng
 */

/** @var array<string,array{title:string,items:string[]}> $entityAbilities Abilities of each exposed item type, by its slug */
$entityAbilities = $tplMng->getDataValueArray('entityAbilities');

// Render-time surface: a loaded addon echoes its own escaped markup here.
ob_start();
do_action('duplicator_tools_ai_cta');
$ctaHtml = trim((string) ob_get_clean());

/**
 * Filter what an assistant can do with backups, one sentence per item with its keywords in <b>.
 *
 * @param string[] $abilities Core backup abilities
 */
$backupAbilities = (array) apply_filters('duplicator_tools_ai_abilities', [
    __('<b>List</b> recent backups with their name, date, status and size.', 'duplicator'),
    __('<b>Create</b> a backup of the whole site or only the database.', 'duplicator'),
    __('<b>Check the progress</b> of a backup until it completes.', 'duplicator'),
    __('<b>Cancel</b> a backup being created or transferred.', 'duplicator'),
    __('<b>Transfer</b> a complete backup to a storage, or back from one.', 'duplicator'),
    __('Check whether the site is <b>protected</b> by a recent backup with a remote copy.', 'duplicator'),
    __('<b>Read the build log</b> of a backup.', 'duplicator'),
    __('<b>Explain how to install</b> a backup.', 'duplicator'),
    __('<b>Delete</b> a backup when allowed in the settings.', 'duplicator'),
]);
/**
 * Filter the groups of what an assistant can do, by key: a title and sentences with <b> keywords
 *
 * @param array<string,array{title:string,items:string[]}> $groups The default groups by key
 */
$groups = (array) apply_filters('duplicator_tools_ai_ability_groups', array_merge(
    [
        'backups' => [
            'title' => __('Backups', 'duplicator'),
            'items' => $backupAbilities,
        ],
    ],
    $entityAbilities,
    [
        'autotune' => [
            'title' => __('AutoTune', 'duplicator'),
            'items' => [
                __('<b>Check</b> AutoTune: the test backups it ran and the build settings it chose.', 'duplicator'),
                __('<b>Start</b> AutoTune when allowed in the settings, and <b>abort</b> it.', 'duplicator'),
            ],
        ],
        'tools'    => [
            'title' => __('Tools', 'duplicator'),
            'items' => [
                __('<b>Clean</b> the temporary build files.', 'duplicator'),
                __('<b>Remove</b> the installer files left after a migration.', 'duplicator'),
                __('<b>Purge</b> orphaned backup files when allowed in the settings.', 'duplicator'),
            ],
        ],
        'settings' => [
            'title' => __('Settings and Site', 'duplicator'),
            'items' => [
                __('<b>Read</b> and <b>change</b> the Duplicator settings.', 'duplicator'),
                __('Show an <b>overview</b> of the site and of Duplicator.', 'duplicator'),
                __('Read the <b>Activity Log</b>, or what happened to one backup.', 'duplicator'),
            ],
        ],
    ]
));

?>
<div class="dupli-tool-ai">
    <h1 class="dupli-tool-ai-title">
        <?php esc_html_e('Manage Duplicator With Your AI Assistant', 'duplicator'); ?>
    </h1>

    <?php
    if ($ctaHtml !== '') {
        ?>
        <div class="dupli-tool-ai-card">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render-time action output; the hooked addon escapes it
            echo $ctaHtml;
            ?>
        </div>
        <?php
    } else {
        ?>
        <p>
            <?php
            printf(
                /* translators: %s: WPVibe link */
                esc_html__('Connect an assistant to this site with %s, then ask it to back the site up.', 'duplicator'),
                '<a href="https://wpvibe.ai/start/" target="_blank" rel="noopener noreferrer">' .
                esc_html__('WPVibe', 'duplicator') . '</a>'
            );
            ?>
            <br>
            <small><?php esc_html_e('WPVibe is a third-party product, not part of Duplicator.', 'duplicator'); ?></small>
        </p>
        <?php
    }
    ?>

    <p class="dupli-tool-ai-headline">
        <strong><?php esc_html_e('What An Assistant Can Do', 'duplicator'); ?></strong>
    </p>

    <section class="dupli-tool-ai-sections">
        <?php foreach ($groups as $groupKey => $group) : ?>
            <section class="dupli-tool-ai-section">
                <p class="dupli-tool-ai-group"><strong><?php echo esc_html($group['title']); ?></strong></p>
                <ul class="dupli-tool-ai-abilities">
                    <?php foreach ($group['items'] as $ability) : ?>
                        <li>
                            <i class="fa fa-caret-right"></i>
                            <?php echo wp_kses_post($ability); ?>
                        </li>
                    <?php endforeach; ?>
                    <?php
                    // Render-time surface: a loaded addon echoes its own escaped <li> rows about abilities the site doesn't have
                    do_action('duplicator_tools_ai_abilities_group_end', (string) $groupKey);
                    ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </section>
    <?php
    // Render-time surface after the groups: a loaded addon echoes its own escaped markup
    do_action('duplicator_tools_ai_abilities_list_end');
    ?>

    <?php if (count($entityAbilities) > 0) : ?>
        <p class="dupli-tool-ai-card">
            <?php
            printf(
                /* translators: %s: link to the General settings */
                esc_html__('Sensitive actions, those that delete, change settings or give out the recovery link, run only when allowed in %s.', 'duplicator'),
                '<a href="' . esc_url(ControllersManager::getMenuLink(
                    ControllersManager::SETTINGS_SUBMENU_SLUG,
                    SettingsPageController::L2_SLUG_GENERAL
                )) . '">' . esc_html__('Settings > General', 'duplicator') . '</a>'
            );
            ?>
        </p>
    <?php endif; ?>

</div>
