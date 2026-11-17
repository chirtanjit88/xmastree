<?php

/**
 * Shared backup request API for sibling plugins and the AI abilities adapter.
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Package;

use Duplicator\Controllers\PackagesPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Exceptions\DupliException;
use Duplicator\Core\Models\Api\ApiField;
use Duplicator\Core\Models\Api\EntityApiException;
use Duplicator\Core\Models\Api\OperationApi;
use Duplicator\Installer\Package\ArchiveDescriptor;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapString;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\FullBackupTemplate;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Models\Storages\UnknownStorage;
use Duplicator\Models\TemplateEntity;
use Duplicator\Package\AutoTune\AutoTuneManager;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\Storage\UploadInfo;
use Duplicator\Utils\Lock\LockUtil;
use Duplicator\Utils\Logging\DupLog;
use Exception;
use Throwable;
use WP_Error;

/**
 * Backup request API for sibling plugins. Authorization belongs to the caller.
 *
 * @phpstan-import-type IneligibilityReasons from FullBackupStatus
 * @phpstan-type        BackupStatus array{
 *   status: string,
 *   name: string,
 *   created: string,
 *   size: string,
 *   can_proceed: bool,
 *   message: string,
 *   details_url: string,
 *   is_full_backup: bool,
 *   exclusions: string[],
 *   warnings: array<int, array{code: int, label: string, message: string}>,
 *   failure: array{reason: string, fixes: list<array{error: string, troubleshooting: string[], code_snippet: string}>},
 *   storages: list<array{id: int, name: string, status: string, has_copy: bool, reason: string}>
 * }
 */
class BackupRequestService
{
    /** @var int Contract revision */
    const API_VERSION = 2;

    const REQUESTER_MAX_LENGTH = 64;
    const REASON_MAX_LENGTH    = 255;

    /** @var int Default number of Backups a listing returns */
    const LIST_LIMIT = 20;
    /** @var string Type of Backups listBackups() returns by default */
    const LIST_TYPE_STANDARD = 'standard';
    /** @var string Filter that adds the types of Backups listBackups() can return, such as an addon's snapshots */
    const LIST_TYPES_FILTER = 'duplicator_backup_list_types';

    /** @var int Largest number of Backups a listing returns */
    const LIST_MAX = 100;

    /** @var int Largest part of a Backup log returned; a longer log returns its end */
    const LOG_MAX_BYTES = MB_IN_BYTES;
    /** @var string Filter that lets addons protect a Backup from every delete path: reason or null, Backup */
    const DELETE_REFUSAL_FILTER = 'duplicator_backup_delete_refusal';

    /** @var string Operation name of the queue, under which add-ons add inputs through OperationApi::INPUTS_FILTER */
    const QUEUE_OPERATION = 'backup-queue';

    /** @var string Request option that builds the Backup from a chosen template */
    const OPTION_TEMPLATE_ID = 'template_id';

    /** @var string[] Request options request() can apply */
    const OPTIONS = [self::OPTION_TEMPLATE_ID];


    const STATUS_MISSING    = 'missing';
    const STATUS_QUEUED     = 'queued';
    const STATUS_RUNNING    = 'running';
    const STATUS_CANCELLING = 'cancelling';
    const STATUS_CANCELLED  = 'cancelled';
    const STATUS_FAILED     = 'failed';
    const STATUS_COMPLETE   = 'complete';

    /** @var int[] Warning codes that mean a file is absent from the archive */
    const BLOCKING_WARNING_CODES = [
        AbstractPackage::WARNING_ARCHIVE_SKIPPED_FILES,
        AbstractPackage::WARNING_ARCHIVE_VANISHED_FILES,
    ];

    /** @var array{reason: string, fixes: array{}} The failure value of a Backup without failure detail */
    private const NO_FAILURE = [
        'reason' => '',
        'fixes'  => [],
    ];

    /** @var string[] Every value getStatusKey() can return; getStatus() adds STATUS_MISSING */
    const STATUS_KEYS = [
        self::STATUS_QUEUED,
        self::STATUS_RUNNING,
        self::STATUS_CANCELLING,
        self::STATUS_CANCELLED,
        self::STATUS_FAILED,
        self::STATUS_COMPLETE,
    ];

    /** @var array<int,string> The status of a copy to a storage, by upload record status */
    const STORAGE_STATUS_KEYS = [
        UploadInfo::STATUS_PENDING   => 'pending',
        UploadInfo::STATUS_RUNNING   => 'running',
        UploadInfo::STATUS_SUCCEEDED => 'succeeded',
        UploadInfo::STATUS_FAILED    => 'failed',
        UploadInfo::STATUS_CANCELLED => 'cancelled',
    ];

    /**
     * Start a background backup in three layers: the template, the default one unless another is given, as it is;
     * then the scope, when passed, which makes it the full backup of that template (exclusions removed, components
     * of the scope); then the password, when passed, which protects it.
     *
     * @param string  $requester     Display identity of the calling plugin, stored as attribution
     * @param string  $reason        Free text explaining why the backup was requested
     * @param ?string $scope         One of BuildComponents::COMPONENTS_ACTIONS, null to keep the template as it is
     * @param string  $executionType Execution type stored on the Backup
     * @param int     $templateId    Template to build with, 0 for the default template
     * @param int[]   $storageIds    Storages to store the Backup in, empty for the default local storage
     * @param string  $password      Installer password protecting the Backup, empty to keep the template protection
     *
     * @return int|WP_Error The Backup package id, or the failure (native union types need PHP 8)
     */
    public function request(
        string $requester,
        string $reason,
        ?string $scope = null,
        string $executionType = AbstractPackage::EXECUTION_TYPE_API,
        int $templateId = 0,
        array $storageIds = [],
        string $password = ''
    ) {
        if ($scope !== null && !in_array($scope, BuildComponents::COMPONENTS_ACTIONS, true)) {
            return new WP_Error(
                'duplicator_invalid_argument',
                sprintf(
                    /* translators: %s: comma separated scope values */
                    __('The scope must be one of: %s.', 'duplicator'),
                    implode(', ', BuildComponents::COMPONENTS_ACTIONS)
                ),
                ['status' => 400]
            );
        }

        $requester = mb_substr(SnapUtil::sanitizeNSCharsNewlineTrim($requester), 0, self::REQUESTER_MAX_LENGTH);
        $reason    = mb_substr(SnapUtil::sanitizeNSCharsNewlineTrim($reason), 0, self::REASON_MAX_LENGTH);
        if ($requester === '') {
            return new WP_Error(
                'duplicator_invalid_argument',
                __('The backup request is missing the requesting plugin name.', 'duplicator'),
                ['status' => 400]
            );
        }

        $unavailable = self::getUnavailableError(
            __(
                'This server cannot run backups in the background.
                Create the backup from the Duplicator admin pages instead.',
                'duplicator'
            )
        );
        if ($unavailable !== null) {
            return $unavailable;
        }

        try {
            $template = self::buildRequestTemplate($templateId, $scope, $password);
            if (is_wp_error($template)) {
                return $template;
            }
            $storageError = self::checkRequestStorages($storageIds);
            if ($storageError !== null) {
                return $storageError;
            }

            if (LockUtil::lockProcessOrThrowWaiting() === false) {
                return new WP_Error(
                    'duplicator_backup_conflict',
                    __('Another backup is already in progress. Wait for it to finish and try again.', 'duplicator'),
                    ['status' => 409]
                );
            }

            try {
                if (PackageUtils::isBackupCreationBlocked($blockMessage)) {
                    return new WP_Error('duplicator_backup_conflict', (string) $blockMessage, ['status' => 409]);
                }

                $package = PackageUtils::createBackgroundPackage(
                    $template,
                    array_values(array_unique($storageIds)),
                    new PackageExecutionInfo($executionType, $requester, $reason),
                    '',
                    ($scope === null ? null : BuildComponents::getComponentsFromAction($scope))
                );

                $packageId = $package->getId();
            } finally {
                LockUtil::unlockProcess();
            }
        } catch (Throwable $e) {
            return self::toWpError($e);
        }

        // The record is persisted; a failed kickoff only delays the build until the next Runner tick.
        try {
            Runner::kickOffWorker();
        } catch (Throwable $e) {
            DupLog::infoTraceException($e, 'Backup request: worker kickoff failed, the Backup stays queued.');
        }

        return $packageId;
    }

    /**
     * The inputs add-ons add to the queue that request() applies, such as the template: the interfaces expose only
     * these, so an option exists only where the add-on that provides it runs
     *
     * @return array<string,ApiField> Options by input name
     */
    public function getRequestOptions(): array
    {
        return array_intersect_key(OperationApi::getAddedInputs(self::QUEUE_OPERATION), array_flip(self::OPTIONS));
    }

    /**
     * Report the state of a Backup.
     *
     * @param int $packageId The Backup package id returned by request()
     *
     * @return BackupStatus|WP_Error The status shape, or the failure (native union types need PHP 8)
     */
    public function getStatus(int $packageId)
    {
        try {
            $package = DupPackage::getById($packageId);
            if ($package === false) {
                return [
                    'status'         => self::STATUS_MISSING,
                    'name'           => '',
                    'created'        => '',
                    'size'           => '',
                    'can_proceed'    => false,
                    'message'        => __('No backup exists with the given id.', 'duplicator'),
                    'details_url'    => '',
                    'is_full_backup' => false,
                    'exclusions'     => [],
                    'warnings'       => [],
                    'failure'        => self::NO_FAILURE,
                    'storages'       => [],
                ];
            }

            $status        = self::getStatusKey($package);
            $reasons       = [];
            $isFull        = self::isFullBackup($package, $reasons);
            $hasExclusions = $package->Archive->hasEffectiveInstanceFilters();

            $warnings   = $package->getBuildWarningsDisplayList();
            $canProceed = $package->getExecutionType() === AbstractPackage::EXECUTION_TYPE_API
                && $status === self::STATUS_COMPLETE
                && $package->haveLocalStorage()
                && array_intersect(self::BLOCKING_WARNING_CODES, array_column($warnings, 'code')) === [];

            return [
                'status'         => $status,
                'name'           => $package->getName(),
                'created'        => $package->getCreated(),
                'size'           => $package->getBuildSize(),
                'can_proceed'    => $canProceed,
                // Progress messages embed markup for the admin UI; API consumers get plain text.
                'message'        => wp_strip_all_tags((string) $package->getProgress()['message']),
                'details_url'    => ControllersManager::getMenuLink(
                    ControllersManager::PACKAGES_SUBMENU_SLUG,
                    null,
                    null,
                    [
                        ControllersManager::QUERY_STRING_INNER_PAGE => PackagesPageController::LIST_INNER_PAGE_DETAILS,
                        'id'                                        => $packageId,
                    ],
                    false
                ),
                'is_full_backup' => $isFull,
                'exclusions'     => $isFull ? [] : self::exclusionMessages($reasons, $hasExclusions),
                'warnings'       => $warnings,
                'failure'        => in_array($status, [self::STATUS_FAILED, self::STATUS_CANCELLED], true)
                    ? $package->getFailureDetails()
                    : self::NO_FAILURE,
                'storages'       => self::storageCopies($package),
            ];
        } catch (Throwable $e) {
            return self::toWpError($e);
        }
    }

    /**
     * Start copying a complete Backup between this server and a storage, either way, and kick off the worker:
     * from the default local storage to another storage (upload), or from a storage to the default local one (download).
     *
     * @param int $packageId     Backup id
     * @param int $fromStorageId Storage that holds the Backup
     * @param int $toStorageId   Storage to copy it to
     *
     * @return true|WP_Error
     */
    public function transfer(int $packageId, int $fromStorageId, int $toStorageId)
    {
        $unavailable = self::getUnavailableError(
            __(
                'This server cannot run transfers in the background.
                Transfer the backup from the Duplicator admin pages instead.',
                'duplicator'
            )
        );
        if ($unavailable !== null) {
            return $unavailable;
        }

        $isDownload = self::checkTransfer($packageId, $fromStorageId, $toStorageId);
        if (is_wp_error($isDownload)) {
            return $isDownload;
        }

        try {
            $result = PackageUtils::startManualTransfer($packageId, [$isDownload ? $fromStorageId : $toStorageId], $isDownload);
        } catch (Throwable $e) {
            return self::toWpError($e);
        }
        if (is_wp_error($result)) {
            return $result;
        }

        // The transfer is persisted; a failed kickoff only delays it until the next Runner tick.
        try {
            Runner::kickOffWorker();
        } catch (Throwable $e) {
            DupLog::infoTraceException($e, 'Backup transfer: worker kickoff failed, the transfer stays queued.');
        }

        return true;
    }

    /**
     * The Backup with the id, when it can be deleted: an addon can protect it and AutoTune protects the Backup it is
     * building. The caller checks the user.
     *
     * @param int $packageId Backup id
     *
     * @return DupPackage|WP_Error
     */
    public function getDeletableBackup(int $packageId)
    {
        $package = DupPackage::getById($packageId);
        if (!$package instanceof DupPackage) {
            return new WP_Error('duplicator_invalid_argument', __('No backup exists with the given id.', 'duplicator'), ['status' => 400]);
        }
        $reason = self::getDeleteRefusal($package);
        if ($reason !== null) {
            return new WP_Error('duplicator_backup_conflict', $reason, ['status' => 409]);
        }
        try {
            AutoTuneManager::assertPackageDestructiveActionAllowed($package);
        } catch (Exception $e) {
            return new WP_Error('duplicator_backup_conflict', $e->getMessage(), ['status' => 409]);
        }
        return $package;
    }

    /**
     * Why an addon protects the Backup from every delete path, whoever asks
     *
     * @param AbstractPackage $package The Backup
     *
     * @return ?string The reason, null when the Backup can be deleted
     */
    public static function getDeleteRefusal(AbstractPackage $package): ?string
    {
        /**
         * Why an addon protects the Backup from every delete path, whoever asks; null allows deleting it
         *
         * @param ?string         $reason  Refusal reason
         * @param AbstractPackage $package The Backup
         */
        $reason = apply_filters(self::DELETE_REFUSAL_FILTER, null, $package);
        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    /**
     * Delete a Backup with its local files; copies on remote storages are kept
     *
     * @param int $packageId Backup id
     *
     * @return array{id:int,message:string}|WP_Error
     */
    public function delete(int $packageId)
    {
        $package = $this->getDeletableBackup($packageId);
        if (is_wp_error($package)) {
            return $package;
        }
        if (!$package->delete()) {
            return new WP_Error('duplicator_backup_failed', __('The backup could not be deleted.', 'duplicator'), ['status' => 500]);
        }

        return [
            'id'      => $packageId,
            /* translators: %d: Backup id */
            'message' => sprintf(__('Backup %d deleted. Its copies on remote storages are kept.', 'duplicator'), $packageId),
        ];
    }

    /**
     * The build log of a Backup
     *
     * @param int $packageId Backup id
     *
     * @return array{id:int,log:string,truncated:bool,message:string}|WP_Error
     */
    public function getLog(int $packageId)
    {
        $package = DupPackage::getById($packageId);
        if ($package === false) {
            return new WP_Error('duplicator_invalid_argument', __('No backup exists with the given id.', 'duplicator'), ['status' => 400]);
        }

        $path   = $package->getSafeLogFilepath();
        $handle = (is_file($path) ? SnapIO::callWithPhpErrorCapture(static fn() => fopen($path, 'rb')) : false);
        if ($handle === false) {
            return [
                'id'        => $packageId,
                'log'       => '',
                'truncated' => false,
                'message'   => __('The log of this backup is not available.', 'duplicator'),
            ];
        }

        // The size of the open file: filesize() can return a stale value from the stat cache
        $stat      = fstat($handle);
        $size      = ($stat === false ? 0 : (int) $stat['size']);
        $truncated = $size > self::LOG_MAX_BYTES;
        if ($truncated) {
            fseek($handle, $size - self::LOG_MAX_BYTES);
        }
        $log = (string) stream_get_contents($handle);
        fclose($handle);

        return [
            'id'        => $packageId,
            'log'       => $log,
            'truncated' => $truncated,
            'message'   => ($truncated ? __('The log is long: only its end is returned.', 'duplicator') : ''),
        ];
    }

    /**
     * How a Backup is installed, for an assistant to tell the user
     *
     * @return array{instructions: string}
     */
    public function getInstallGuide(): array
    {
        return [
            'instructions' => SnapString::oneLine(__(
                'To install a backup, download its archive and its installer from the Backups page, or transfer the backup to a storage
                and get them from there, and put both files in the folder of the destination site. Then open the installer in a browser
                at the URL of that folder, or run it with php from the command line in that folder: php installer.php --help
                explains how.',
                'duplicator'
            )),
        ];
    }

    /**
     * Ask a queued or running Backup, or its transfer, to stop; the worker cancels it on its next step.
     *
     * @param int $packageId Backup id
     *
     * @return array{id:int,status:string,message:string}|WP_Error
     */
    public function cancel(int $packageId)
    {
        $package = DupPackage::getById($packageId);
        if (!$package instanceof DupPackage) {
            return new WP_Error('duplicator_invalid_argument', __('No backup exists with the given id.', 'duplicator'), ['status' => 400]);
        }

        $status = self::getStatusKey($package);
        if ($status === self::STATUS_CANCELLING) {
            return [
                'id'      => $packageId,
                'status'  => $status,
                /* translators: %d: Backup id */
                'message' => sprintf(__('Backup %d is already being cancelled.', 'duplicator'), $packageId),
            ];
        }
        if (!in_array($status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true)) {
            return new WP_Error(
                'duplicator_backup_conflict',
                sprintf(
                    /* translators: 1: Backup id, 2: status */
                    __('Backup %1$d is %2$s: only a queued or running backup can be cancelled.', 'duplicator'),
                    $packageId,
                    $status
                ),
                ['status' => 409]
            );
        }
        try {
            AutoTuneManager::assertPackageDestructiveActionAllowed($package);
        } catch (Exception $e) {
            return new WP_Error('duplicator_backup_conflict', $e->getMessage(), ['status' => 409]);
        }

        DupLog::trace("Backup request API: set {$packageId} for cancel");
        $package->setForCancel();

        return [
            'id'      => $packageId,
            'status'  => self::STATUS_CANCELLING,
            /* translators: %d: Backup id */
            'message' => sprintf(
                /* translators: %d: Backup id */
                __(
                    'Backup %d is being cancelled: it stops at the next step of the worker. A backup that finishes before then stays complete.',
                    'duplicator'
                ),
                $packageId
            ),
        ];
    }

    /**
     * The runtime template of a request: the template layer, then the scope and password layers; never saved
     *
     * @param int     $templateId Template id, 0 for the default template
     * @param ?string $scope      Scope, null to keep the template as it is
     * @param string  $password   Installer password, empty to keep the template protection
     *
     * @return TemplateEntity|WP_Error
     */
    private static function buildRequestTemplate(int $templateId, ?string $scope, string $password)
    {
        $base = ($templateId > 0 ? self::getRequestTemplate($templateId) : TemplateEntity::getDefaultTemplateOrFail());
        if (is_wp_error($base)) {
            return $base;
        }

        if ($scope !== null) {
            $template = new FullBackupTemplate($base);
        } else {
            $template = new TemplateEntity();
            $template->copyFrom($base);
        }

        if ($password !== '') {
            // Archive encryption stays on with the new password; any other mode becomes password protection
            $security = ((int) $template->installer_opts_secure_on === ArchiveDescriptor::SECURE_MODE_ARC_ENCRYPT ? 'encrypt' : 'password');
            try {
                $template->setFromApi(
                    [
                        'security' => $security,
                        'password' => $password,
                    ],
                    false
                );
            } catch (EntityApiException $e) {
                return $e->toWpError();
            }
        }

        return $template;
    }

    /**
     * Check the API rules of a transfer: one end is the default local storage, the other a different storage, and the
     * Backup is complete
     *
     * @param int $packageId     Backup id
     * @param int $fromStorageId Source storage id
     * @param int $toStorageId   Destination storage id
     *
     * @return bool|WP_Error True for a download to the default local storage, false for an upload from it
     */
    private static function checkTransfer(int $packageId, int $fromStorageId, int $toStorageId)
    {
        $localId = StoragesUtil::getDefaultStorageId();
        if ($fromStorageId === $toStorageId) {
            return self::invalidTransfer(__('The source and destination storages must be different.', 'duplicator'));
        }
        if ($fromStorageId !== $localId && $toStorageId !== $localId) {
            return self::invalidTransfer(sprintf(
                /* translators: %d: default local storage id */
                SnapString::oneLine(__(
                    'A transfer copies a backup to or from the default local storage (id %d).
                    To copy it between two other storages, transfer it to the default local storage first, then from there.',
                    'duplicator'
                )),
                $localId
            ));
        }
        $storageError = self::checkRequestStorages([$fromStorageId, $toStorageId]);
        if ($storageError !== null) {
            return $storageError;
        }

        $package = DupPackage::getById($packageId);
        if (!$package instanceof DupPackage) {
            return self::invalidTransfer(__('No backup exists with the given id.', 'duplicator'));
        }
        $running = PackageUtils::getRunningTransferError($packageId);
        if ($running !== null) {
            return $running;
        }
        if ($package->getStatus() !== AbstractPackage::STATUS_COMPLETE) {
            return self::invalidTransfer(__('Only a complete backup can be transferred.', 'duplicator'));
        }

        // Whether the Backup is on the source and not on the destination is checked on the storages when the transfer starts
        return ($toStorageId === $localId);
    }

    /**
     * @param string $message Error message
     *
     * @return WP_Error
     */
    private static function invalidTransfer(string $message): WP_Error
    {
        return new WP_Error('duplicator_invalid_argument', $message, ['status' => 400]);
    }

    /**
     * The template a request names: an existing template the entity API exposes, not the manual one
     *
     * @param int $templateId Template id
     *
     * @return TemplateEntity|WP_Error
     */
    private static function getRequestTemplate(int $templateId)
    {
        $template = TemplateEntity::getById($templateId);
        if (!$template instanceof TemplateEntity || !TemplateEntity::apiExposes($template)) {
            return new WP_Error(
                'duplicator_invalid_argument',
                /* translators: %d: template id */
                sprintf(__('No Backup template exists with id %d.', 'duplicator'), $templateId),
                ['status' => 400]
            );
        }
        return $template;
    }

    /**
     * Refuse a storage id that names no storage the entity API exposes
     *
     * @param int[] $storageIds Storage ids
     *
     * @return ?WP_Error Null when every id is valid
     */
    private static function checkRequestStorages(array $storageIds): ?WP_Error
    {
        foreach ($storageIds as $storageId) {
            $storage = ($storageId > 0 ? AbstractStorageEntity::getById($storageId) : false);
            if ($storage === false || !AbstractStorageEntity::apiExposes($storage)) {
                return new WP_Error(
                    'duplicator_invalid_argument',
                    /* translators: %d: storage id */
                    sprintf(__('No storage exists with id %d.', 'duplicator'), $storageId),
                    ['status' => 400]
                );
            }
        }
        return null;
    }

    /**
     * Refuse when Backups cannot be created on this site or cannot progress in the background.
     *
     * @param string $backgroundMessage Message for client-side-kickoff hosts
     *
     * @return ?WP_Error Null when the work can run
     */
    private static function getUnavailableError(string $backgroundMessage): ?WP_Error
    {
        if (PackageUtils::isBackupCreationDisabledOnSite($siteMessage)) {
            return new WP_Error('duplicator_backup_unavailable', (string) $siteMessage, ['status' => 403]);
        }

        if (ClientSideKick::isClientSideKickoffMode()) {
            return new WP_Error('duplicator_backup_unavailable', SnapString::oneLine($backgroundMessage), ['status' => 403]);
        }

        return null;
    }

    /**
     * The types of Backups listBackups() can return
     *
     * @return array<string,string> Package type by list type
     */
    public function getListTypes(): array
    {
        /**
         * Add a type of Backups the Backup list can return
         *
         * @param array<string,string> $types Package type by list type, the standard Backups first
         */
        return (array) apply_filters(self::LIST_TYPES_FILTER, [self::LIST_TYPE_STANDARD => DupPackage::getType()]);
    }

    /**
     * List the most recent Backups of one type, newest first.
     *
     * @param int    $limit  Maximum number of rows
     * @param string $status Only the Backups with this STATUS_KEYS value, empty for all
     * @param string $type   One of the getListTypes() keys
     *
     * @return list<array{id: int, name: string, created: string, status: string, size: string}>|WP_Error
     */
    public function listBackups(int $limit = self::LIST_LIMIT, string $status = '', string $type = self::LIST_TYPE_STANDARD)
    {
        if ($limit < 1 || $limit > self::LIST_MAX) {
            return new WP_Error(
                'duplicator_invalid_argument',
                /* translators: %d: maximum number of backups */
                sprintf(__('The limit must be between 1 and %d.', 'duplicator'), self::LIST_MAX),
                ['status' => 400]
            );
        }
        if ($status !== '' && !in_array($status, self::STATUS_KEYS, true)) {
            return new WP_Error(
                'duplicator_invalid_argument',
                /* translators: %s: comma separated status values */
                sprintf(__('The status must be one of: %s.', 'duplicator'), implode(', ', self::STATUS_KEYS)),
                ['status' => 400]
            );
        }
        $packageTypes = $this->getListTypes();
        if (!isset($packageTypes[$type])) {
            return new WP_Error(
                'duplicator_invalid_argument',
                /* translators: %s: comma separated type values */
                sprintf(__('The type must be one of: %s.', 'duplicator'), implode(', ', array_keys($packageTypes))),
                ['status' => 400]
            );
        }

        // Full objects only for the listed Backups, for the stored size; one type only, as the Backups list does.
        /** @var AbstractPackage[] $packages */
        $packages = DupPackage::dbSelect(self::getStatusWhere($status), $limit, 0, '`id` DESC', 'objs', [$packageTypes[$type]]);
        $rows     = [];
        foreach ($packages as $package) {
            $rows[] = [
                'id'      => $package->getId(),
                'name'    => $package->getName(),
                'created' => $package->getCreated(),
                'status'  => self::getStatusKey($package),
                'size'    => $package->getBuildSize(),
            ];
        }

        return $rows;
    }

    /**
     * SQL condition on the stored status matching a STATUS_KEYS value as getStatusKey() reports it: a Backup not complete
     * that is pending cancellation is cancelling, whatever its stored status
     *
     * @param string $status A STATUS_KEYS value, empty for all
     *
     * @return string Condition, empty for all
     */
    private static function getStatusWhere(string $status): string
    {
        global $wpdb;

        if ($status === '') {
            return '';
        }
        if ($status === self::STATUS_COMPLETE) {
            return $wpdb->prepare('`status` = %d', AbstractPackage::STATUS_COMPLETE);
        }

        $pendingIds = array_filter(array_map('intval', AbstractPackage::getPendingCancellations()), static fn(int $id): bool => $id > 0);
        $isPending  = ($pendingIds === [] ? '0' : '`id` IN (' . implode(',', $pendingIds) . ')');
        if ($status === self::STATUS_CANCELLING) {
            return $wpdb->prepare(
                '(`status` = %d OR (`status` <> %d AND ' . $isPending . '))',
                AbstractPackage::STATUS_PENDING_CANCEL,
                AbstractPackage::STATUS_COMPLETE
            );
        }

        switch ($status) {
            case self::STATUS_QUEUED:
                $stored = $wpdb->prepare('`status` = %d', AbstractPackage::STATUS_PRE_PROCESS);
                break;
            case self::STATUS_CANCELLED:
                $stored = $wpdb->prepare('`status` IN (%d, %d)', AbstractPackage::STATUS_BUILD_CANCELLED, AbstractPackage::STATUS_STORAGE_CANCELLED);
                break;
            case self::STATUS_FAILED:
                $stored = $wpdb->prepare(
                    '`status` < 0 AND `status` NOT IN (%d, %d, %d)',
                    AbstractPackage::STATUS_BUILD_CANCELLED,
                    AbstractPackage::STATUS_PENDING_CANCEL,
                    AbstractPackage::STATUS_STORAGE_CANCELLED
                );
                break;
            default:
                $stored = $wpdb->prepare('`status` > %d AND `status` < %d', AbstractPackage::STATUS_PRE_PROCESS, AbstractPackage::STATUS_COMPLETE);
                break;
        }

        return '(' . $stored . ' AND NOT ' . $isPending . ')';
    }

    /**
     * Collapse a Backup's state to the service STATUS_* vocabulary.
     *
     * @param AbstractPackage $package The Backup
     *
     * @return string One of the STATUS_* constants
     */
    public static function getStatusKey(AbstractPackage $package): string
    {
        $status = $package->getReportedStatus();

        if ($status === AbstractPackage::STATUS_COMPLETE) {
            return self::STATUS_COMPLETE;
        }
        if ($status === AbstractPackage::STATUS_PENDING_CANCEL) {
            return self::STATUS_CANCELLING;
        }
        if ($status === AbstractPackage::STATUS_PRE_PROCESS) {
            return self::STATUS_QUEUED;
        }
        if (
            $status === AbstractPackage::STATUS_BUILD_CANCELLED ||
            $status === AbstractPackage::STATUS_STORAGE_CANCELLED
        ) {
            return self::STATUS_CANCELLED;
        }
        if ($status < 0) {
            return self::STATUS_FAILED;
        }

        return self::STATUS_RUNNING;
    }

    /**
     * Whether the Backup leaves nothing out: a full backup with no archive filter.
     *
     * @param AbstractPackage      $package The Backup
     * @param IneligibilityReasons $reasons Filled with the reasons the Backup is not full
     *
     * @return bool
     */
    public static function isFullBackup(AbstractPackage $package, array &$reasons = []): bool
    {
        return (new FullBackupStatus($package))->isFullBackup($reasons) && !$package->Archive->hasEffectiveInstanceFilters();
    }

    /**
     * The copy of the Backup on each storage it was sent to, from the latest upload to each
     *
     * @param AbstractPackage $package The Backup
     *
     * @return list<array{id: int, name: string, status: string, has_copy: bool, reason: string}>
     */
    private static function storageCopies(AbstractPackage $package): array
    {
        $copies = [];
        foreach ($package->getLatestUploadInfos(true) as $uploadInfo) {
            $storage = $uploadInfo->getStorage();
            $hasCopy = $uploadInfo->hasCompleted(true) && $uploadInfo->packageExists();
            $status  = self::STORAGE_STATUS_KEYS[$uploadInfo->getStatus()];
            $reason  = $uploadInfo->getFailureReason();
            if ($status === 'succeeded' && !$hasCopy) {
                $reason = __('The copy is no longer on the storage.', 'duplicator');
            }

            $copies[] = [
                'id'       => $uploadInfo->getStorageId(),
                'name'     => $storage instanceof UnknownStorage ? __('Deleted storage', 'duplicator') : $storage->getName(),
                'status'   => $status,
                'has_copy' => $hasCopy,
                'reason'   => $reason,
            ];
        }
        return $copies;
    }

    /**
     * Describe what the Backup leaves out, for a consumer that cannot read Duplicator's settings.
     *
     * @param IneligibilityReasons $reasons       Reasons from FullBackupStatus::isFullBackup()
     * @param bool                 $hasExclusions Whether archive filters exclude files
     *
     * @return string[]
     */
    private static function exclusionMessages(array $reasons, bool $hasExclusions): array
    {
        $messages = [];

        if (!empty($reasons['db_only'])) {
            // The shared wording rejects database-only Backups; the dropped reasons restate it.
            $messages[] = __('This backup contains the database only; site files are not included.', 'duplicator');
            unset($reasons['db_only'], $reasons['missing_components'], $reasons['filtered_wp_dirs']);
        }

        $messages = array_merge($messages, FullBackupStatus::reasonsToMessages($reasons));

        if ($hasExclusions) {
            $messages[] = __('The backup settings exclude files from this backup.', 'duplicator');
        }

        return $messages;
    }

    /**
     * Map a failure to the public error contract, exposing user messages only.
     *
     * @param Throwable $e The failure
     *
     * @return WP_Error
     */
    private static function toWpError(Throwable $e): WP_Error
    {
        if ($e instanceof DupliException && $e->hasUserMessage()) {
            return new WP_Error('duplicator_backup_failed', $e->getUserMessage(), ['status' => 500]);
        }

        DupLog::infoTraceException($e, 'Backup request API: unexpected failure.');
        return new WP_Error(
            'duplicator_backup_failed',
            __('Duplicator could not complete the backup request. Check the Duplicator logs for details.', 'duplicator'),
            ['status' => 500]
        );
    }
}
