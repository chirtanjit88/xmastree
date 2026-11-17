<?php

/**
 * Activity Log listing exposed by the API interfaces
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Core\Models\Api;

use Duplicator\Core\CapMng;
use Duplicator\Models\ActivityLog\AbstractLogEvent;
use Duplicator\Models\ActivityLog\LogEventBackupCreate;
use Duplicator\Models\ActivityLog\LogEventWebsitesScan;
use Duplicator\Package\DupPackage;
use WP_Error;

/**
 * List the Activity Log events, newest first, or the events of one Backup. User authorization belongs to the caller.
 *
 * @phpstan-type LogRow array{id:int,parent_id:int,created:string,type:string,severity:string,title:string,description:string,via:string,via_client:string}
 */
class ActivityLogApi
{
    /** @var int Default number of events a listing returns */
    const LIST_LIMIT = 20;

    /** @var int Largest number of events a listing returns */
    const LIST_MAX = 100;

    /** @var array<string,int> Severity filter values and the severities they select */
    const SEVERITIES = [
        'info'    => AbstractLogEvent::SEVERITY_INFO,
        'warning' => AbstractLogEvent::SEVERITY_WARNING,
        'error'   => AbstractLogEvent::SEVERITY_ERROR,
    ];

    /**
     * List the events.
     *
     * @param int    $limit           Maximum number of events
     * @param int    $backupId        Only the scan and build events of this Backup with their steps, 0 for the newest events
     * @param bool   $checkCapability Leave out the event types the current user can't see; false for callers without a user
     * @param string $severity        Only the events with this SEVERITIES key, empty for all
     *
     * @return list<LogRow>|WP_Error
     */
    public function list(int $limit = self::LIST_LIMIT, int $backupId = 0, bool $checkCapability = true, string $severity = '')
    {
        if ($severity !== '' && !isset(self::SEVERITIES[$severity])) {
            return new WP_Error(
                'duplicator_invalid_argument',
                /* translators: %s: comma separated severity values */
                sprintf(__('The severity must be one of: %s.', 'duplicator'), implode(', ', array_keys(self::SEVERITIES))),
                ['status' => 400]
            );
        }
        $severityValue = ($severity === '' ? -1 : self::SEVERITIES[$severity]);
        if ($limit < 1 || $limit > self::LIST_MAX) {
            return new WP_Error(
                'duplicator_invalid_argument',
                /* translators: %d: maximum number of events */
                sprintf(__('The limit must be between 1 and %d.', 'duplicator'), self::LIST_MAX),
                ['status' => 400]
            );
        }

        if ($backupId > 0) {
            return $this->listBackupEvents($backupId, $limit, $checkCapability, $severityValue);
        }

        $events = AbstractLogEvent::getList([
            'per_page'         => $limit,
            'parent_id'        => 0,
            'severity'         => $severityValue,
            'check_capability' => $checkCapability,
        ]);
        return array_values(array_map([self::class, 'toRow'], $events));
    }

    /**
     * The scan and build events of a Backup, each followed by its steps
     *
     * @param int  $backupId        Backup id
     * @param int  $limit           Maximum number of events
     * @param bool $checkCapability Leave out the event types the current user can't see
     * @param int  $severity        Only the events and steps with this severity, -1 for all
     *
     * @return list<LogRow>|WP_Error
     */
    private function listBackupEvents(int $backupId, int $limit, bool $checkCapability, int $severity)
    {
        $package = DupPackage::getById($backupId);
        if (!$package instanceof DupPackage) {
            return new WP_Error('duplicator_invalid_argument', __('No backup exists with the given id.', 'duplicator'), ['status' => 400]);
        }

        $eventIds = [
            LogEventWebsitesScan::class => $package->getMainScanLogId(),
            LogEventBackupCreate::class => $package->getMainActivityLogId(),
        ];
        $rows     = [];
        foreach ($eventIds as $class => $eventId) {
            if ($eventId <= 0 || ($checkCapability && !CapMng::can($class::getCapability(), false))) {
                continue;
            }
            $event = $class::getById($eventId);
            if (!$event instanceof $class) {
                continue;
            }
            $events = array_merge([$event], $class::getChildLogsByParentId($eventId, 'ASC', self::LIST_MAX));
            foreach ($events as $listed) {
                if ($severity < 0 || $listed->getSeverity() === $severity) {
                    $rows[] = self::toRow($listed);
                }
            }
        }

        return array_slice($rows, 0, $limit);
    }

    /**
     * @param AbstractLogEvent $event Event
     *
     * @return LogRow
     */
    private static function toRow(AbstractLogEvent $event): array
    {
        return [
            'id'          => $event->getId(),
            'parent_id'   => $event->getParentId(),
            'created'     => $event->getCreatedAt(),
            'type'        => $event::getTypeLabel(),
            'severity'    => (string) array_search($event->getSeverity(), self::SEVERITIES, true),
            'title'       => wp_strip_all_tags($event->getTitle()),
            'description' => wp_strip_all_tags($event->getShortDescription()),
            'via'         => $event->getVia(),
            'via_client'  => $event->getViaClient(),
        ];
    }
}
