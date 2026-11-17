<?php

/**
 * DUPLICATOR AI READY ADDON - ABILITY PERMISSION
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Addons\AiReadyAddon;

use Duplicator\Addons\StagingAddon\StagingAddon;
use Duplicator\Core\CapMng;
use Duplicator\Libs\Snap\SnapString;
use WP_Error;

/**
 * Permission check of the abilities that require a given capability.
 */
final class AbilityPermission
{
    /**
     * Check the capability first, so a caller without it learns nothing about the site, then refuse staging sites.
     *
     * @param string $capability Duplicator capability the ability requires
     *
     * @return true|WP_Error
     */
    public static function check(string $capability)
    {
        if (!CapMng::can($capability, false)) {
            return new WP_Error(
                'duplicator_permission_denied',
                sprintf(
                    /* translators: %s: Duplicator permission name */
                    __('Your account isn\'t allowed to do this yet. Ask a site administrator to give you the "%s" permission in Duplicator.', 'duplicator'),
                    CapMng::getCapsInfo()[$capability]['label'] ?? $capability
                )
            );
        }

        if (class_exists(StagingAddon::class) && StagingAddon::isStagingSite()) {
            return new WP_Error(
                'duplicator_staging_site',
                __('Duplicator management is disabled on staging sites. Use your production site instead.', 'duplicator')
            );
        }

        return true;
    }

    /**
     * Wrap the execute callback of a sensitive ability: it runs only while the site allows AI assistants to run sensitive
     * actions
     *
     * @param callable $execute Execute callback
     *
     * @return callable
     */
    public static function guardUnsafe(callable $execute): callable
    {
        return static function ($input = null) use ($execute) {
            if (!GeneralSettingsSection::isUnsafeAllowed()) {
                return new WP_Error(
                    'duplicator_unsafe_disabled',
                    SnapString::oneLine(__(
                        'Sensitive actions from an AI assistant, such as deleting, changing settings or getting the recovery link,
                        are turned off on this site. A site administrator can allow them in Duplicator > Settings > General.',
                        'duplicator'
                    )),
                    ['status' => 403]
                );
            }
            return $execute($input);
        };
    }
}
