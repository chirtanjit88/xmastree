<?php

/**
 * Settings exposed through the settings API
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Core\Models\Api;

use Duplicator\Utils\ActivityLog\SettingsChangeTracker;

/**
 * A set of settings that core or an addon exposes through the settings API under globally unique keys.
 *
 * setFromArray() is the only write path of these settings: the settings page passes every field of its form,
 * the settings API only the declared fields. validate() holds the refusals, so the settings API checks every set of
 * settings before it writes any.
 */
abstract class AbstractApiSettings
{
    /**
     * Settings the API reads and writes, named by their unique key
     *
     * @return ApiField[]
     */
    abstract public static function apiFields(): array;

    /**
     * Current values of the declared settings by key
     *
     * @return array<string,mixed>
     */
    abstract public function toArray(): array;

    /**
     * Set the passed values with every rule of these settings, save them and log the changes. Keys not passed keep
     * their value; the settings page may pass keys the API does not declare.
     *
     * @param array<string,mixed> $values Values by key
     *
     * @return void
     *
     * @throws EntityApiException When a value is refused
     */
    abstract public function setFromArray(array $values): void;

    /**
     * Refuse the values setFromArray() would refuse, before anything is written; setFromArray() calls it first.
     *
     * @param array<string,mixed> $values Values by key
     *
     * @return void
     *
     * @throws EntityApiException When a value is refused
     */
    public function validate(array $values): void
    {
    }

    /**
     * Values a declared setting can't take on this server, with the reason
     *
     * @return array<string,array<string,string>> Reason by value, by key
     */
    public function apiUnavailableValues(): array
    {
        return [];
    }

    /**
     * Internal value of an API value, refusing a value the map does not hold
     *
     * @param string                  $key   Setting key
     * @param mixed                   $value API value
     * @param array<string|int,mixed> $map   Internal value by API value
     *
     * @return mixed Internal value
     *
     * @throws EntityApiException When the value is not in the map
     */
    protected static function mapValue(string $key, $value, array $map)
    {
        if (!array_key_exists((string) $value, $map)) {
            throw new EntityApiException(
                EntityApiException::CODE_INVALID_ARGUMENT,
                sprintf(__('%1$s must be one of: %2$s.', 'duplicator'), $key, implode(', ', array_keys($map)))
            );
        }
        return $map[(string) $value];
    }

    /**
     * Refuse a value that is neither empty nor a valid email address
     *
     * @param array<string,mixed> $values Values by key
     * @param string              $key    Setting key
     *
     * @return void
     *
     * @throws EntityApiException When the value is not a valid email address
     */
    protected static function validateEmail(array $values, string $key): void
    {
        if (!array_key_exists($key, $values)) {
            return;
        }
        $email = trim((string) $values[$key]);
        if ($email !== '' && !is_email($email)) {
            throw new EntityApiException(
                EntityApiException::CODE_INVALID_ARGUMENT,
                sprintf(__('%s must be a valid email address.', 'duplicator'), $key)
            );
        }
    }

    /**
     * Set a passed on/off value, recording the change
     *
     * @param SettingsChangeTracker $tracker Change tracker
     * @param array<string,mixed>   $values  Values by key
     * @param string                $key     Setting key
     * @param string                $logKey  Key of the change in the activity log
     * @param callable(): bool      $getter  Current value
     * @param callable(bool): mixed $setter  Setter of the new value
     *
     * @return void
     */
    protected static function setSwitch(
        SettingsChangeTracker $tracker,
        array $values,
        string $key,
        string $logKey,
        callable $getter,
        callable $setter
    ): void {
        if (!array_key_exists($key, $values)) {
            return;
        }
        $value = (bool) $values[$key];
        $tracker->addChange($logKey, $getter(), $value, $value ? 'enabled' : 'disabled');
        $setter($value);
    }
}
