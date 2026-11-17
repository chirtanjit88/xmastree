<?php

/**
 * Lite answers of the AI abilities
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Addons\LiteBase;

use Duplicator\Addons\LiteBase\Utils\LiteBaseLinks;
use Duplicator\Core\CapMng;
use Duplicator\Core\Models\Api\SiteInfo;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Package\ProtectionStatus;
use stdClass;
use WP_Error;

/**
 * Answers the AI abilities that involve a Duplicator Pro feature in Lite.
 *
 * @phpstan-import-type Status from ProtectionStatus
 */
final class LiteAiAbilityIntegration
{
    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init(): void
    {
        add_filter(ProtectionStatus::FILTER, [self::class, 'filterProtectionStatus']);
        add_filter(SiteInfo::INFO_FILTER, [self::class, 'filterSiteInfo']);
        // After the abilities of the AI add-on, whose category these abilities join
        add_action('wp_abilities_api_init', [self::class, 'registerProAbilities'], 20);
    }

    /**
     * Register, under the Duplicator Pro names, the schedule, template and recovery point abilities, answering that the feature comes
     * with Duplicator Pro, so an assistant gets an accurate answer instead of guessing. Only where the AI add-on
     * registered the Duplicator abilities.
     *
     * @return void
     */
    public static function registerProAbilities(): void
    {
        if (!function_exists('wp_get_ability') || wp_get_ability('duplicator/backup-list') === null) {
            return;
        }

        $schedules = __(
            'Scheduled backups are a Duplicator Pro feature: Duplicator Pro runs backups automatically, hourly, daily, weekly
            or monthly, to any storage.',
            'duplicator'
        );
        $templates = __(
            'Backup templates are a Duplicator Pro feature: they save what a backup includes and excludes, such as folders,
            file types and tables, to reuse it for backups and schedules.',
            'duplicator'
        );
        $recovery  = __(
            'Recovery points are a Duplicator Pro feature: they restore the site from a backup even when wp-admin is unreachable.',
            'duplicator'
        );
        $abilities = [
            'duplicator/schedule-list'   => [
                __('List Schedules', 'duplicator'),
                $schedules,
                'Schedules',
                true,
            ],
            'duplicator/schedule-create' => [
                __('Create Schedule', 'duplicator'),
                $schedules,
                'Schedules',
                false,
            ],
            'duplicator/template-list'   => [
                __('List Templates', 'duplicator'),
                $templates,
                'Templates',
                true,
            ],
            'duplicator/template-create' => [
                __('Create Template', 'duplicator'),
                $templates,
                'Templates',
                false,
            ],
            'duplicator/recovery-get'    => [
                __('Get Recovery Point', 'duplicator'),
                $recovery,
                'Recovery',
                true,
            ],
            'duplicator/recovery-set'    => [
                __('Set Recovery Point', 'duplicator'),
                $recovery,
                'Recovery',
                false,
            ],
        ];
        foreach ($abilities as $name => [$label, $feature, $campaign, $readonly]) {
            wp_register_ability($name, [
                'label'               => $label,
                'description'         => SnapString::oneLine($feature) . ' ' . __(
                    'In Duplicator Lite it answers with what Duplicator Pro adds and a link: tell the user.',
                    'duplicator'
                ),
                'category'            => 'duplicator',
                'execute_callback'    => static fn($input = null): WP_Error => self::proFeatureError($feature, $campaign),
                'permission_callback' => static fn($input = null): bool => CapMng::can(CapMng::CAP_BASIC, false),
                'input_schema'        => [
                    'type'                 => 'object',
                    'additionalProperties' => true,
                    'default'              => new stdClass(),
                ],
                'output_schema'       => ['type' => 'object'],
                'meta'                => [
                    'show_in_rest' => true,
                    'mcp'          => ['public' => true],
                    'annotations'  => [
                        'readonly'    => $readonly,
                        'destructive' => false,
                    ],
                ],
            ]);
        }
    }

    /**
     * @param string $feature  What the Pro feature does, as one or more sentences
     * @param string $campaign Upgrade link campaign
     *
     * @return WP_Error The feature sentence and the upgrade link, the only text an error carries
     */
    private static function proFeatureError(string $feature, string $campaign): WP_Error
    {
        return new WP_Error(
            'duplicator_backup_unavailable',
            sprintf(
                /* translators: 1: what the Duplicator Pro feature does, 2: Duplicator Pro upgrade URL */
                __('%1$s Learn more: %2$s', 'duplicator'),
                SnapString::oneLine($feature),
                LiteBaseLinks::getUpgradeUrl('ai-ability', $campaign)
            ),
            ['status' => 403]
        );
    }

    /**
     * Name the Duplicator Pro features that answer an unprotected site: schedules and recovery points when backups are
     * missing or old, the Pro storages when no remote storage is set up. Nothing once a storage is connected, when the
     * next step is copying a backup. The link goes in upgrade_url, since assistants repeat a feature name but drop links.
     *
     * @param Status $status Protection status
     *
     * @return array<string,mixed> The status, with upgrade_url when the summary names Pro features
     */
    public static function filterProtectionStatus(array $status): array
    {
        if (in_array($status['reason'], [ProtectionStatus::REASON_TOO_OLD, ProtectionStatus::REASON_NO_BACKUPS], true)) {
            $features = SnapString::oneLine(__(
                'Duplicator Pro runs backups automatically on a schedule, and keeps a recovery point that restores the site
                even when wp-admin is unreachable.',
                'duplicator'
            ));
            $campaign = 'Schedules';
        } elseif ($status['reason'] === ProtectionStatus::REASON_NO_REMOTE_STORAGE) {
            $features = SnapString::oneLine(__(
                'Duplicator Cloud works in Duplicator Lite; Google Drive, Dropbox, OneDrive, Amazon S3 and FTP/SFTP storages
                come with Duplicator Pro.',
                'duplicator'
            ));
            $campaign = 'Storages';
        } else {
            return $status;
        }

        $status['summary']    .= ' ' . $features;
        $status['upgrade_url'] = LiteBaseLinks::getUpgradeUrl('ai-ability', $campaign);

        return $status;
    }

    /**
     * Add to the site overview what Duplicator Pro adds, so an assistant describing Duplicator names it
     *
     * @param array<string,mixed> $info Site overview sections
     *
     * @return array<string,mixed>
     */
    public static function filterSiteInfo(array $info): array
    {
        $info['duplicator_pro'] = [
            'adds'        => [
                __('Backups run automatically on a schedule', 'duplicator'),
                __('Recovery points that restore the site even when wp-admin is unreachable', 'duplicator'),
                __('Google Drive, Dropbox, OneDrive, Amazon S3 and FTP/SFTP storages', 'duplicator'),
                __('Backup templates with file and table exclusions', 'duplicator'),
                __('Incremental backups that send only the site changes to Duplicator Cloud', 'duplicator'),
            ],
            'upgrade_url' => LiteBaseLinks::getUpgradeUrl('ai-ability', 'Overview'),
        ];
        return $info;
    }
}
