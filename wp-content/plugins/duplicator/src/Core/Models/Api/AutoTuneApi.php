<?php

/**
 * AutoTune operations exposed by the API interfaces
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Core\Models\Api;

use Duplicator\Core\CapMng;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Package\AutoTune\Attempt;
use Duplicator\Package\AutoTune\AutoTuneManager;
use Duplicator\Package\AutoTune\AutoTuneSessionEntity;
use Duplicator\Package\ClientSideKick;
use Duplicator\Package\PackageUtils;
use Exception;
use WP_Error;

/**
 * Read, start and abort the AutoTune session. The site availability of the settings capability is enforced here;
 * user authorization belongs to the caller.
 *
 * @phpstan-type AttemptRow array{number:int,backup_id:int,configuration:string,outcome:string,failure:string}
 * @phpstan-type Status array{
 *   status:string,
 *   stop_reason:string,
 *   message:string,
 *   started_at:string,
 *   ended_at:string,
 *   deadline_at:string,
 *   attempts:list<AttemptRow>
 * }
 */
class AutoTuneApi
{
    /** @var array<int,string> Session status names by AutoTuneSessionEntity status */
    const STATUS_NAMES = [
        AutoTuneSessionEntity::STATUS_NONE      => 'none',
        AutoTuneSessionEntity::STATUS_RUNNING   => 'running',
        AutoTuneSessionEntity::STATUS_COMPLETED => 'completed',
        AutoTuneSessionEntity::STATUS_FAILED    => 'failed',
        AutoTuneSessionEntity::STATUS_ABORTED   => 'aborted',
        AutoTuneSessionEntity::STATUS_TIMEOUT   => 'timeout',
        AutoTuneSessionEntity::STATUS_ERROR     => 'error',
    ];

    /**
     * The current or last session
     *
     * @return Status
     */
    public function getStatus(): array
    {
        $session  = AutoTuneSessionEntity::reloadInstance();
        $attempts = [];
        foreach ($session->getAttempts() as $index => $attempt) {
            $attempts[] = [
                'number'        => $index + 1,
                'backup_id'     => $attempt->getPackageId(),
                'configuration' => $attempt->getConfig()->getLabel(),
                'outcome'       => $attempt->getOutcome(),
                'failure'       => ($attempt->getOutcome() === Attempt::OUTCOME_FAILED ? wp_strip_all_tags($attempt->getFailMessage()) : ''),
            ];
        }

        return [
            'status'      => self::STATUS_NAMES[$session->getStatus()] ?? self::STATUS_NAMES[AutoTuneSessionEntity::STATUS_NONE],
            // An abort is applied by the worker: until then the running session reports the stop it waits for
            'stop_reason' => ($session->isRunning() && $session->hasPendingStop() ? $session->getPendingStopReason() : $session->getStopReason()),
            'message'     => wp_strip_all_tags($session->getFailureMessage()),
            'started_at'  => self::formatTime($session->getStartedAt()),
            'ended_at'    => self::formatTime($session->getEndedAt()),
            'deadline_at' => ($session->isRunning() ? self::formatTime($session->getDeadlineAt()) : ''),
            'attempts'    => $attempts,
        ];
    }

    /**
     * Start a session with the host default exclusions, as a start without the dialog does
     *
     * @return Status|WP_Error
     */
    public function start()
    {
        $unavailable = self::getUnavailableError();
        if ($unavailable !== null) {
            return $unavailable;
        }
        if (PackageUtils::isBackupCreationDisabledOnSite($siteMessage)) {
            return new WP_Error('duplicator_backup_unavailable', (string) $siteMessage, ['status' => 403]);
        }
        if (ClientSideKick::isClientSideKickoffMode()) {
            return new WP_Error(
                'duplicator_backup_unavailable',
                SnapString::oneLine(__(
                    'This server cannot run backups in the background, so AutoTune cannot run its test backups.
                    Start AutoTune from Tools > AutoTune instead.',
                    'duplicator'
                )),
                ['status' => 403]
            );
        }

        try {
            AutoTuneManager::start();
        } catch (Exception $e) {
            if (AutoTuneManager::isConflict($e)) {
                return new WP_Error('duplicator_backup_conflict', $e->getMessage(), ['status' => 409]);
            }
            // Unmet backup requirements or no usable build configuration on this host
            return new WP_Error('duplicator_backup_unavailable', $e->getMessage(), ['status' => 403]);
        }

        return $this->getStatus();
    }

    /**
     * Abort the running session and cancel its test Backup
     *
     * @return Status|WP_Error
     */
    public function abort()
    {
        $unavailable = self::getUnavailableError();
        if ($unavailable !== null) {
            return $unavailable;
        }

        try {
            AutoTuneManager::abort();
        } catch (Exception $e) {
            return new WP_Error('duplicator_backup_conflict', $e->getMessage(), ['status' => 409]);
        }

        return $this->getStatus();
    }

    /**
     * AutoTune changes the build settings, so it needs the settings capability on the site
     *
     * @return ?WP_Error The refusal when the site does not allow it, null otherwise
     */
    private static function getUnavailableError(): ?WP_Error
    {
        if (CapMng::isCapAvailableOnSite(CapMng::CAP_SETTINGS)) {
            return null;
        }
        return new WP_Error(
            'duplicator_backup_unavailable',
            __('AutoTune is not available on this site: managing the settings is disabled.', 'duplicator'),
            ['status' => 403]
        );
    }

    /**
     * @param int $timestamp Unix time, 0 for none
     *
     * @return string ISO 8601 UTC date, empty for none
     */
    private static function formatTime(int $timestamp): string
    {
        return ($timestamp > 0 ? gmdate(DATE_ATOM, $timestamp) : '');
    }
}
