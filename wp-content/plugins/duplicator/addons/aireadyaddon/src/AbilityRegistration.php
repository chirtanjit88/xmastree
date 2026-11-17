<?php

/**
 * DUPLICATOR AI READY ADDON - ABILITY REGISTRATION
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Addons\AiReadyAddon;

use Duplicator\Models\ActivityLog\AbstractLogEvent;
use WP_Application_Passwords;

/**
 * Registers the abilities of the Duplicator category with their exposure, the sensitive actions gate and the AI
 * assistant channel of the activity log events they create.
 */
final class AbilityRegistration
{
    /**
     * Register an ability of the Duplicator category, public over REST and MCP. An ability annotated destructive, or
     * registered as unsafe, runs only while the site allows AI assistants to run sensitive actions.
     *
     * @param string              $name   Ability name
     * @param array<string,mixed> $args   wp_register_ability() arguments other than the category and the meta, plus
     *                                    the annotations
     * @param bool                $unsafe Whether the ability is unsafe without being destructive
     *
     * @return void
     */
    public static function register(string $name, array $args, bool $unsafe = false): void
    {
        $annotations = $args['annotations'];
        unset($args['annotations']);
        if ($unsafe || ($annotations['destructive'] ?? false)) {
            $args['execute_callback'] = AbilityPermission::guardUnsafe($args['execute_callback']);
            // The description is what an assistant reads in the abilities list before choosing to call one
            $args['description'] = trim($args['description'] . ' ' . self::getUnsafeNote());
        }
        $args['execute_callback'] = self::recordVia($args['execute_callback']);

        wp_register_ability($name, $args + [
            'category' => AbilitiesRegistrar::CATEGORY_SLUG,
            'meta'     => [
                'show_in_rest' => true,
                'mcp'          => ['public' => true],
                'annotations'  => $annotations,
            ],
        ]);
    }

    /**
     * Wrap an execute callback so the activity log events it creates say an AI assistant performed them
     *
     * @param callable $execute Execute callback
     *
     * @return callable
     */
    private static function recordVia(callable $execute): callable
    {
        return static function ($input = null) use ($execute) {
            AbstractLogEvent::setCurrentVia(AbilitiesRegistrar::REQUESTER, self::getClientName());
            try {
                return $execute($input);
            } finally {
                AbstractLogEvent::setCurrentVia('');
            }
        };
    }

    /**
     * Name of the application password the request authenticated with, empty for any other authentication
     *
     * @return string
     */
    private static function getClientName(): string
    {
        $uuid = rest_get_authenticated_app_password();
        if (empty($uuid)) {
            return '';
        }
        $password = WP_Application_Passwords::get_user_application_password(get_current_user_id(), $uuid);
        return is_array($password) ? (string) $password['name'] : '';
    }

    /**
     * The sentence every sensitive ability description ends with
     *
     * @return string
     */
    public static function getUnsafeNote(): string
    {
        return __('Sensitive action: ask the user to confirm before calling it.', 'duplicator');
    }
}
