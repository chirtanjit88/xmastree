<?php

declare(strict_types=1);

namespace Duplicator\Addons\LiteBase\Notifications;

use Duplicator\Addons\LiteBase\Utils\LiteBaseLinks;
use Duplicator\Core\Views\TplMng;

/**
 * Adds to the Tools > AI abilities groups the abilities only Pro has, marked Pro, and an upgrade button after them.
 */
class AiPageProAbilities
{
    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init(): void
    {
        add_filter('duplicator_tools_ai_ability_groups', [self::class, 'addProGroups']);
        add_action('duplicator_tools_ai_abilities_group_end', [self::class, 'renderGroupEnd']);
        add_action('duplicator_tools_ai_abilities_list_end', [self::class, 'render']);
    }

    /**
     * Add after Backups the groups only Pro has; their rows come from renderGroupEnd()
     *
     * @param array<string,array{title:string,items:string[]}> $groups Groups by key
     *
     * @return array<string,array{title:string,items:string[]}>
     */
    public static function addProGroups(array $groups): array
    {
        $proGroups = [];
        if (!isset($groups['recovery'])) {
            $proGroups['recovery'] = [
                'title' => __('Recovery Point', 'duplicator'),
                'items' => [],
            ];
        }
        if (!isset($groups['schedule'])) {
            $proGroups['schedule'] = [
                'title' => __('Schedules', 'duplicator'),
                'items' => [],
            ];
        }
        if (!isset($groups['template'])) {
            $proGroups['template'] = [
                'title' => __('Templates', 'duplicator'),
                'items' => [],
            ];
        }
        if (!isset($groups['incremental'])) {
            $proGroups['incremental'] = [
                'title' => __('Incremental Backups', 'duplicator'),
                'items' => [],
            ];
        }
        $position = array_search('backups', array_keys($groups), true);
        $position = ($position === false ? 0 : $position + 1);
        return array_slice($groups, 0, $position, true) + $proGroups + array_slice($groups, $position, null, true);
    }

    /**
     * Render the Pro rows of a group
     *
     * @param string $groupKey Group key
     *
     * @return void
     */
    public static function renderGroupEnd(string $groupKey): void
    {
        $abilities = self::getProAbilities()[$groupKey] ?? [];
        if (count($abilities) === 0) {
            return;
        }
        TplMng::getInstance()->render('litebase/tools/ai-pro-abilities', ['abilities' => $abilities]);
    }

    /**
     * Render the upgrade button after the groups.
     *
     * @return void
     */
    public static function render(): void
    {
        TplMng::getInstance()->render(
            'litebase/tools/ai-pro-upgrade',
            ['upgradeUrl' => LiteBaseLinks::getUpgradeUrl('tools-ai', 'Pro Abilities')]
        );
    }

    /**
     * The Pro rows by group key, worded as Pro lists them
     *
     * @return array<string,string[]>
     */
    private static function getProAbilities(): array
    {
        return [
            'backups'     => [__('Create a backup from one of your <b>templates</b>, with its exclusions.', 'duplicator')],
            'recovery'    => [
                __('<b>Get</b> the recovery point and its recovery link.', 'duplicator'),
                __('<b>Set</b> a full backup as the recovery point.', 'duplicator'),
            ],
            'schedule'    => [
                __('<b>List</b>, <b>view</b>, <b>create</b> and <b>update</b> them.', 'duplicator'),
                __('<b>Delete</b> one when allowed in the settings.', 'duplicator'),
                __('<b>Copy</b> one, changing the settings you name on the copy.', 'duplicator'),
                __('<b>Run</b> a schedule now, <b>activate</b> or <b>deactivate</b> it.', 'duplicator'),
            ],
            'template'    => [
                __('<b>List</b>, <b>view</b>, <b>create</b> and <b>update</b> them.', 'duplicator'),
                __('<b>Delete</b> one when allowed in the settings.', 'duplicator'),
                __('<b>Copy</b> one, changing the settings you name on the copy.', 'duplicator'),
            ],
            'incremental' => [
                __('<b>Run</b> an incremental backup now, <b>activate</b> or <b>deactivate</b> incremental backups.', 'duplicator'),
                __('Set <b>how often</b> they run and <b>how many</b> Duplicator Cloud keeps.', 'duplicator'),
                __('<b>List</b> them and <b>check</b> whether one can run now.', 'duplicator'),
            ],
            'storage'     => [__('<b>Manage more storage types</b>, such as Amazon S3, Google Drive, Dropbox, OneDrive, FTP and SFTP.', 'duplicator')],
        ];
    }
}
