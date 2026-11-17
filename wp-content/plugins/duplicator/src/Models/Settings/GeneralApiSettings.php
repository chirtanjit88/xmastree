<?php

/**
 * General settings exposed through the settings API
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Models\Settings;

use Duplicator\Core\Models\Api\AbstractApiSettings;
use Duplicator\Core\Models\Api\ApiField;
use Duplicator\Core\Models\Api\EntityApiException;
use Duplicator\Models\ActivityLog\LogEventSettingsChange;
use Duplicator\Models\DynamicGlobalEntity;
use Duplicator\Models\GlobalEntity;
use Duplicator\Models\StaticGlobal;
use Duplicator\Utils\ActivityLog\SettingsChangeTracker;
use Duplicator\Utils\Email\EmailSummary;
use Duplicator\Utils\Logging\DupLog;
use Duplicator\Utils\Logging\TraceLogMng;
use Duplicator\Utils\UsageStatistics\StatsBootstrap;
use Exception;

/**
 * The settings of the General settings page. setFromArray() also takes what only the page sets:
 * uninstall_settings, uninstall_packages, encrypt_settings, usage_tracking and product_notices.
 */
final class GeneralApiSettings extends AbstractApiSettings
{
    const FREQUENCIES = [
        EmailSummary::SEND_FREQ_NEVER,
        EmailSummary::SEND_FREQ_DAILY,
        EmailSummary::SEND_FREQ_WEEKLY,
        EmailSummary::SEND_FREQ_MONTHLY,
    ];

    /**
     * @return ApiField[]
     */
    public static function apiFields(): array
    {
        return [
            new ApiField('trace_log', ApiField::TYPE_BOOLEAN, 'Whether the Duplicator trace log is written; turning it on clears the old log.'),
            (new ApiField('trace_log_max_size_mb', ApiField::TYPE_INTEGER, 'Total size of the trace log files in MB.'))
                ->range(1),
            (new ApiField('email_summary_frequency', ApiField::TYPE_STRING, 'How often the email summary is sent.'))
                ->enum(self::FREQUENCIES),
            new ApiField('email_summary_recipients', ApiField::TYPE_STRING_LIST, 'Email addresses the summary is sent to.'),
            (new ApiField('activity_log_retention_months', ApiField::TYPE_INTEGER, 'Months the activity log entries are kept; 0 keeps them forever.'))
                ->range(0),
            new ApiField('unload_third_party_scripts', ApiField::TYPE_BOOLEAN, 'Whether scripts of other plugins are left out of the Duplicator pages.'),
            new ApiField('unload_third_party_styles', ApiField::TYPE_BOOLEAN, 'Whether styles of other plugins are left out of the Duplicator pages.'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $global = GlobalEntity::getInstance();
        return [
            'trace_log'                     => StaticGlobal::getTraceLogEnabledOption(),
            'trace_log_max_size_mb'         => intdiv(TraceLogMng::getInstance()->getMaxTotalSize(), MB_IN_BYTES),
            'email_summary_frequency'       => $global->getEmailSummaryFrequency(),
            'email_summary_recipients'      => $global->getEmailSummaryRecipients(),
            'activity_log_retention_months' => intdiv(DynamicGlobalEntity::getInstance()->getValInt('activity_log_retention'), MONTH_IN_SECONDS),
            'unload_third_party_scripts'    => $global->shouldUnhookThirdPartyJs(),
            'unload_third_party_styles'     => $global->shouldUnhookThirdPartyCss(),
        ];
    }

    /**
     * @param array<string,mixed> $values Values by key
     *
     * @return void
     */
    public function validate(array $values): void
    {
        if (array_key_exists('email_summary_frequency', $values) && !in_array($values['email_summary_frequency'], self::FREQUENCIES, true)) {
            throw new EntityApiException(
                EntityApiException::CODE_INVALID_ARGUMENT,
                sprintf(__('email_summary_frequency must be one of: %s.', 'duplicator'), implode(', ', self::FREQUENCIES))
            );
        }
        if (array_key_exists('email_summary_recipients', $values)) {
            foreach ((array) $values['email_summary_recipients'] as $email) {
                if (sanitize_email((string) $email) === '') {
                    throw new EntityApiException(
                        EntityApiException::CODE_INVALID_ARGUMENT,
                        __('email_summary_recipients must contain valid email addresses.', 'duplicator')
                    );
                }
            }
        }
    }

    /**
     * @param array<string,mixed> $values Values by key
     *
     * @return void
     */
    public function setFromArray(array $values): void
    {
        $this->validate($values);
        $tracker = new SettingsChangeTracker();
        $global  = GlobalEntity::getInstance();

        self::setSwitch(
            $tracker,
            $values,
            'uninstall_settings',
            'uninstall_settings',
            [
                StaticGlobal::class,
                'getUninstallSettingsOption',
            ],
            static fn(bool $value) => StaticGlobal::setUninstallSettingsOption($value)
        );
        self::setSwitch(
            $tracker,
            $values,
            'uninstall_packages',
            'uninstall_packages',
            [
                StaticGlobal::class,
                'getUninstallPackageOption',
            ],
            static fn(bool $value) => StaticGlobal::setUninstallPackageOption($value)
        );
        $newCrypt = null;
        if (array_key_exists('encrypt_settings', $values)) {
            $newCrypt = (bool) $values['encrypt_settings'];
            $tracker->addChange('crypt_option', StaticGlobal::getCryptOption(), $newCrypt, $newCrypt ? 'enabled' : 'disabled');
            if ($newCrypt === StaticGlobal::getCryptOption()) {
                $newCrypt = null;
            }
        }
        self::setSwitch(
            $tracker,
            $values,
            'unload_third_party_scripts',
            GlobalEntity::UNHOOK_THIRD_PARTY_JS_KEY,
            [
                $global,
                'shouldUnhookThirdPartyJs',
            ],
            static fn(bool $value) => $global->setUnhookThirdPartyJs($value, false)
        );
        self::setSwitch(
            $tracker,
            $values,
            'unload_third_party_styles',
            GlobalEntity::UNHOOK_THIRD_PARTY_CSS_KEY,
            [
                $global,
                'shouldUnhookThirdPartyCss',
            ],
            static fn(bool $value) => $global->setUnhookThirdPartyCss($value)
        );
        if (array_key_exists('trace_log', $values)) {
            $value   = (bool) $values['trace_log'];
            $current = StaticGlobal::getTraceLogEnabledOption();
            $tracker->addChange(
                'logging_mode',
                $current ? 'on' : 'off',
                $value ? 'on' : 'off',
                'optionChanged',
                [
                    'off' => __('Off', 'duplicator'),
                    'on'  => __('On', 'duplicator'),
                ]
            );
            if ($value && !$current) {
                DupLog::deleteTraceLog();
            }
            StaticGlobal::setTraceLogEnabledOption($value);
        }
        if (array_key_exists('email_summary_frequency', $values)) {
            $value = (string) $values['email_summary_frequency'];
            $tracker->addChange(GlobalEntity::EMAIL_SUMMARY_FREQUENCY_KEY, $global->getEmailSummaryFrequency(), $value, 'frequencyChanged');
            $global->setEmailSummaryFrequency($value);
        }
        if (array_key_exists('email_summary_recipients', $values)) {
            $recipients = array_map(static fn($email): string => sanitize_email((string) $email), (array) $values['email_summary_recipients']);
            $tracker->addChange(GlobalEntity::EMAIL_SUMMARY_RECIPIENTS_KEY, $global->getEmailSummaryRecipients(), $recipients, 'emailListChanged');
            $global->setEmailSummaryRecipients($recipients);
        }
        if (!DUPLICATOR_USTATS_DISALLOW) { // @phpstan-ignore-line
            self::setSwitch(
                $tracker,
                $values,
                'usage_tracking',
                'usage_tracking',
                [
                    StatsBootstrap::class,
                    'isTrackingAllowed',
                ],
                static fn(bool $value) => StatsBootstrap::setTrackingAllowed($value)
            );
        }
        self::setSwitch(
            $tracker,
            $values,
            'product_notices',
            'am_notices',
            [
                $global,
                'isAmNoticesEnabled',
            ],
            static fn(bool $value) => $global->setAmNotices($value)
        );
        if (array_key_exists('trace_log_max_size_mb', $values)) {
            $value = max(1, (int) $values['trace_log_max_size_mb']);
            $tracker->addChange(
                'trace_max_size',
                intdiv(TraceLogMng::getInstance()->getMaxTotalSize(), MB_IN_BYTES),
                $value,
                'sizeChanged',
                [
                    'fromUnit' => 'MB',
                    'toUnit'   => 'MB',
                ]
            );
            TraceLogMng::getInstance()->setMaxTotalSize($value * MB_IN_BYTES);
        }

        if (!$global->save()) {
            throw new Exception('Unable to save the general settings.');
        }

        if (array_key_exists('activity_log_retention_months', $values)) {
            $dGlobal = DynamicGlobalEntity::getInstance();
            $seconds = max(0, (int) $values['activity_log_retention_months']) * MONTH_IN_SECONDS;
            $tracker->addChange(
                'activity_log_retention',
                $dGlobal->getValInt('activity_log_retention'),
                $seconds,
                'timeChanged',
                [
                    'fromUnit' => 'sec',
                    'toUnit'   => 'month',
                ]
            );
            $dGlobal->setValInt('activity_log_retention', $seconds);
            if (!$dGlobal->save()) {
                throw new Exception('Unable to save the activity log retention.');
            }
        }
        $tracker->createLog(LogEventSettingsChange::SUB_TYPE_GENERAL);

        if ($newCrypt !== null) {
            do_action('duplicator_before_update_crypt_setting');
            StaticGlobal::setCryptOption($newCrypt);
            do_action('duplicator_after_update_crypt_setting');
        }
    }
}
