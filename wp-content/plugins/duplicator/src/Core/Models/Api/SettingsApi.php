<?php

/**
 * Settings API: list, get and update the settings core and the addons expose
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Core\Models\Api;

use Duplicator\Core\CapMng;
use Duplicator\Models\Settings\BackupApiSettings;
use Duplicator\Models\Settings\GeneralApiSettings;
use Duplicator\Models\Settings\StorageApiSettings;
use Duplicator\Utils\Logging\DupLog;
use Exception;
use Throwable;

/**
 * One flat list of settings under globally unique keys. Each key belongs to an AbstractApiSettings class, whose
 * validate() and setFromArray() hold the rules; this service holds the allow-list and the casts.
 */
final class SettingsApi
{
    /** @var string Filter that collects the AbstractApiSettings classes addons add to the core ones */
    const SETTINGS_FILTER = 'duplicator_api_settings';
    /** @var string Pattern of setting keys */
    const KEY_PATTERN = '/^[a-z0-9]+(?:_[a-z0-9]+)*$/';
    /** @var string Capability every setting needs */
    const CAPABILITY = CapMng::CAP_SETTINGS;

    /**
     * Every exposed setting with its value, description and the values it can't take on this server
     *
     * @return list<array{key:string,value:mixed,description:string,unavailable:array<string,string>}>
     */
    public function list(): array
    {
        return $this->run(function (): array {
            $rows = [];
            foreach ($this->getSettingsClasses() as $class) {
                $rows = array_merge($rows, self::toRows(new $class()));
            }
            return $rows;
        });
    }

    /**
     * One setting
     *
     * @param string $key Setting key
     *
     * @return array{key:string,value:mixed,description:string,unavailable:array<string,string>}
     */
    public function get(string $key): array
    {
        return $this->run(function () use ($key): array {
            $class = $this->getClassByKey()[$key] ?? null;
            if ($class === null) {
                throw new EntityApiException(EntityApiException::CODE_NOT_FOUND, sprintf(__('No setting is named %s.', 'duplicator'), $key));
            }
            foreach (self::toRows(new $class()) as $row) {
                if ($row['key'] === $key) {
                    return $row;
                }
            }
            throw new Exception('Setting ' . $key . ' is declared but not returned by ' . $class);
        });
    }

    /**
     * Change the passed settings; the others keep their value. Every value is checked against its field and the rules
     * of its settings before anything is written.
     *
     * @param array<string,mixed> $values Values by key
     *
     * @return list<array{key:string,value:mixed,description:string,unavailable:array<string,string>}> The changed settings
     */
    public function update(array $values): array
    {
        return $this->run(function () use ($values): array {
            if (count($values) === 0) {
                throw new EntityApiException(EntityApiException::CODE_INVALID_ARGUMENT, __('Pass at least one setting to update.', 'duplicator'));
            }

            $classByKey = $this->getClassByKey();
            $fields     = $this->getFields();
            $unknown    = array_diff(array_keys($values), array_keys($classByKey));
            if (count($unknown) > 0) {
                throw new EntityApiException(
                    EntityApiException::CODE_INVALID_ARGUMENT,
                    sprintf(__('Unknown or read-only settings: %s.', 'duplicator'), implode(', ', $unknown))
                );
            }

            $byClass = [];
            foreach ($values as $key => $value) {
                $byClass[$classByKey[$key]][$key] = $fields[$key]->cast($value);
            }

            $settingsByClass = [];
            foreach ($byClass as $class => $classValues) {
                $settingsByClass[$class] = new $class();
                $settingsByClass[$class]->validate($classValues);
            }

            $rows = [];
            foreach ($byClass as $class => $classValues) {
                $settingsByClass[$class]->setFromArray($classValues);
                foreach (self::toRows(new $class()) as $row) {
                    if (array_key_exists($row['key'], $classValues)) {
                        $rows[] = $row;
                    }
                }
            }
            return $rows;
        });
    }

    /**
     * Writable fields of every setting, by key
     *
     * @return array<string,ApiField>
     */
    public function getFields(): array
    {
        $fields = [];
        foreach ($this->getSettingsClasses() as $class) {
            foreach ($class::apiFields() as $field) {
                $fields[$field->getName()] = $field;
            }
        }
        return $fields;
    }

    /**
     * Core and addon settings classes
     *
     * @return class-string<AbstractApiSettings>[]
     */
    public function getSettingsClasses(): array
    {
        $classes = array_merge(
            [
                GeneralApiSettings::class,
                BackupApiSettings::class,
                StorageApiSettings::class,
            ],
            (array) apply_filters(self::SETTINGS_FILTER, [])
        );
        foreach ($classes as $class) {
            if (!is_string($class) || !is_subclass_of($class, AbstractApiSettings::class)) {
                throw new Exception('Settings API classes must extend ' . AbstractApiSettings::class);
            }
        }
        return array_values(array_unique($classes));
    }

    /**
     * Settings class of every key, refusing invalid and duplicate keys
     *
     * @return array<string,class-string<AbstractApiSettings>>
     */
    private function getClassByKey(): array
    {
        $result = [];
        foreach ($this->getSettingsClasses() as $class) {
            foreach ($class::apiFields() as $field) {
                $key = $field->getName();
                if (preg_match(self::KEY_PATTERN, $key) !== 1 || isset($result[$key]) || !$field->isWritable() || !$field->isReadable()) {
                    throw new Exception('Invalid or duplicate setting key ' . $key . ' in ' . $class);
                }
                $result[$key] = $class;
            }
        }
        return $result;
    }

    /**
     * @param AbstractApiSettings $settings Settings
     *
     * @return list<array{key:string,value:mixed,description:string,unavailable:array<string,string>}>
     */
    private static function toRows(AbstractApiSettings $settings): array
    {
        $values      = $settings->toArray();
        $unavailable = $settings->apiUnavailableValues();
        $rows        = [];
        foreach ($settings::apiFields() as $field) {
            $key = $field->getName();
            if (!array_key_exists($key, $values)) {
                throw new Exception(get_class($settings) . '::toArray() does not return the setting ' . $key);
            }
            $rows[] = [
                'key'         => $key,
                'value'       => $field->normalize($values[$key]),
                'description' => $field->getDescription(),
                'unavailable' => $unavailable[$key] ?? [],
            ];
        }
        return $rows;
    }

    /**
     * Run an operation, refusing it where the site does not allow the settings and hiding unexpected failures
     *
     * @template T
     *
     * @param callable(): T $callback Operation
     *
     * @return T
     */
    private function run(callable $callback)
    {
        if (!CapMng::isCapAvailableOnSite(self::CAPABILITY)) {
            throw new EntityApiException(EntityApiException::CODE_UNAVAILABLE, __('Settings are not available on this site.', 'duplicator'));
        }

        try {
            return $callback();
        } catch (EntityApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            DupLog::infoTraceException($e, 'Settings API failed.');
            throw new EntityApiException(
                EntityApiException::CODE_FAILED,
                __('Duplicator could not complete the request. Check the Duplicator logs for details.', 'duplicator'),
                $e
            );
        }
    }
}
