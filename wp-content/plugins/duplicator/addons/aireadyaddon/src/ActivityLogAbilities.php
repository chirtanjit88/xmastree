<?php

/**
 * Ability of the Activity Log listing
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Addons\AiReadyAddon;

use Duplicator\Core\CapMng;
use Duplicator\Core\Models\Api\ActivityLogApi;
use Duplicator\Libs\Snap\SnapString;
use stdClass;
use WP_Error;

/**
 * Registers activity-log-list over the Activity Log API.
 */
final class ActivityLogAbilities
{
    const ACTIVITY_LOG_LIST = 'duplicator/activity-log-list';

    private ActivityLogApi $api;

    /**
     * @param ActivityLogApi $api Activity Log API service
     */
    public function __construct(ActivityLogApi $api)
    {
        $this->api = $api;
    }

    /**
     * @return void
     */
    public function registerAbilities(): void
    {
        AbilityRegistration::register(self::ACTIVITY_LOG_LIST, [
            'label'               => __('List Activity Log', 'duplicator'),
            'description'         => SnapString::oneLine(sprintf(
                /* translators: %d: default number of events */
                __(
                    'Returns the newest Activity Log events, %d unless limit says otherwise: backups, deletions, settings changes,
                    license and schedule events, with severity and a short description. severity keeps only the events with that
                    severity, such as every error. With backup_id it returns the scan and build events of that backup, each
                    followed by its steps, to explain what happened to it.',
                    'duplicator'
                ),
                ActivityLogApi::LIST_LIMIT
            )),
            'execute_callback'    => fn($input) => $this->handleList($input),
            'permission_callback' => static fn($input) => AbilityPermission::check(CapMng::CAP_BASIC),
            'input_schema'        => [
                'type'                 => 'object',
                'properties'           => [
                    'limit'     => [
                        'type'        => 'integer',
                        'minimum'     => 1,
                        'maximum'     => ActivityLogApi::LIST_MAX,
                        'default'     => ActivityLogApi::LIST_LIMIT,
                        'description' => 'Maximum number of events to return.',
                    ],
                    'backup_id' => [
                        'type'        => 'integer',
                        'minimum'     => 1,
                        'description' => 'Return only the events of this backup, from backup-list.',
                    ],
                    'severity'  => [
                        'type'        => 'string',
                        'enum'        => array_keys(ActivityLogApi::SEVERITIES),
                        'description' => 'Return only the events with this severity.',
                    ],
                ],
                'additionalProperties' => false,
                'default'              => new stdClass(),
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'count'  => [
                        'type'        => 'integer',
                        'description' => 'Number of events returned.',
                    ],
                    'events' => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'id'          => ['type' => 'integer'],
                                'parent_id'   => [
                                    'type'        => 'integer',
                                    'description' => 'Id of the event this step belongs to, 0 for a main event.',
                                ],
                                'created'     => [
                                    'type'        => 'string',
                                    'description' => 'Creation time, GMT.',
                                ],
                                'type'        => [
                                    'type'        => 'string',
                                    'description' => 'Event type.',
                                ],
                                'severity'    => [
                                    'type'        => 'string',
                                    'enum'        => array_keys(ActivityLogApi::SEVERITIES),
                                    'description' => 'Severity, the values the severity input takes.',
                                ],
                                'title'       => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'via'         => [
                                    'type'        => 'string',
                                    'description' => 'Channel the action was performed through, such as an AI assistant or WP-CLI, empty for none.',
                                ],
                                'via_client'  => [
                                    'type'        => 'string',
                                    'description' => 'Application password name the channel used, empty when unknown.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'annotations'         => ['readonly' => true],
        ]);
    }

    /**
     * @param mixed $input Ability input; accepts optional `limit`, `backup_id` and `severity`
     *
     * @return array<string,mixed>|WP_Error
     */
    public function handleList($input)
    {
        $input  = (array) $input;
        $events = $this->api->list(
            is_numeric($input['limit'] ?? null) ? (int) $input['limit'] : ActivityLogApi::LIST_LIMIT,
            is_numeric($input['backup_id'] ?? null) ? (int) $input['backup_id'] : 0,
            true,
            is_string($input['severity'] ?? null) ? $input['severity'] : ''
        );
        if (is_wp_error($events)) {
            return $events;
        }

        return [
            'count'  => count($events),
            'events' => $events,
        ];
    }
}
