<?php

/**
 * Abilities of the settings API
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Addons\AiReadyAddon;

use Duplicator\Core\Models\Api\EntityApiException;
use Duplicator\Core\Models\Api\SettingsApi;
use Duplicator\Libs\Snap\SnapString;
use stdClass;
use WP_Error;

/**
 * Registers settings-list, settings-get and settings-update over the settings API.
 */
final class SettingsAbilities
{
    const SETTINGS_LIST   = 'duplicator/settings-list';
    const SETTINGS_GET    = 'duplicator/settings-get';
    const SETTINGS_UPDATE = 'duplicator/settings-update';

    /** @var string[] JSON types a setting value can have */
    const VALUE_TYPES = [
        'string',
        'integer',
        'boolean',
        'array',
    ];

    private SettingsApi $api;

    /**
     * @param SettingsApi $api Settings API service
     */
    public function __construct(SettingsApi $api)
    {
        $this->api = $api;
    }

    /**
     * @return void
     */
    public function registerAbilities(): void
    {
        $fields = $this->api->getFields();
        $row    = [
            'type'       => 'object',
            'properties' => [
                'key'         => ['type' => 'string'],
                'value'       => [
                    'type'        => self::VALUE_TYPES,
                    'description' => 'Current value, of the setting type.',
                ],
                'description' => ['type' => 'string'],
                'unavailable' => [
                    'type'        => 'object',
                    'description' => 'Values the setting can\'t take on this server, with the reason.',
                ],
            ],
        ];
        $values = [];
        foreach ($fields as $key => $field) {
            $values[$key] = $field->getSchema(true);
        }

        $this->register(self::SETTINGS_LIST, [
            'label'            => __('List Settings', 'duplicator'),
            'description'      => SnapString::oneLine(__(
                'Returns every Duplicator setting an assistant can change: key, current value, description and the values the server
                does not allow. Use it to answer how Duplicator is configured, such as its archive or database engine, email summary
                or trace log, rather than answering from memory.',
                'duplicator'
            )),
            'execute_callback' => fn($input) => $this->handle(fn(): array => ['settings' => $this->api->list()]),
            'input_schema'     => [
                'type'       => 'object',
                'properties' => new stdClass(),
                'default'    => new stdClass(),
            ],
            'output_schema'    => [
                'type'       => 'object',
                'properties' => [
                    'settings' => [
                        'type'  => 'array',
                        'items' => $row,
                    ],
                ],
            ],
            'annotations'      => ['readonly' => true],
        ]);

        $this->register(self::SETTINGS_GET, [
            'label'            => __('Get Setting', 'duplicator'),
            'description'      => __('Returns one Duplicator setting by key, to answer how Duplicator is configured.', 'duplicator'),
            'execute_callback' => fn($input) => $this->handle(fn(): array => $this->api->get((string) (((array) $input)['key'] ?? ''))),
            'input_schema'     => [
                'type'                 => 'object',
                'required'             => ['key'],
                'properties'           => [
                    'key' => [
                        'type' => 'string',
                        'enum' => array_keys($fields),
                    ],
                ],
                'additionalProperties' => false,
            ],
            'output_schema'    => $row,
            'annotations'      => ['readonly' => true],
        ]);

        $this->register(self::SETTINGS_UPDATE, [
            'label'            => __('Update Settings', 'duplicator'),
            'description'      => SnapString::oneLine(__(
                'Changes the passed Duplicator settings; the others keep their value. Pass each setting as key: value, or pass
                settings as a list of {key, value}, as settings-list returns them. A value the server does not allow is refused.
                Works only when sensitive actions are allowed in Duplicator > Settings > General.',
                'duplicator'
            )),
            'execute_callback' => fn($input) => $this->handle(fn(): array => ['settings' => $this->api->update(self::getUpdateValues($input))]),
            'input_schema'     => [
                'type'                 => 'object',
                'properties'           => $values + [
                    'settings' => [
                        'type'        => 'array',
                        'description' => 'The settings to change as {key, value}, the shape settings-list returns.',
                        'items'       => [
                            'type'       => 'object',
                            'required'   => [
                                'key',
                                'value',
                            ],
                            'properties' => [
                                'key'   => [
                                    'type' => 'string',
                                    'enum' => array_keys($fields),
                                ],
                                'value' => ['type' => self::VALUE_TYPES],
                            ],
                        ],
                    ],
                ],
                'additionalProperties' => false,
                'minProperties'        => 1,
            ],
            'output_schema'    => [
                'type'       => 'object',
                'properties' => [
                    'settings' => [
                        'type'  => 'array',
                        'items' => $row,
                    ],
                ],
            ],
            'annotations'      => [
                'readonly'    => false,
                'destructive' => false,
            ],
        ], true);
    }

    /**
     * The values of a settings-update input: the settings passed as key: value and those passed as a {key, value} list;
     * a key passed both ways takes the key: value
     *
     * @param mixed $input Ability input
     *
     * @return array<string,mixed>
     */
    public static function getUpdateValues($input): array
    {
        $values = (array) $input;
        $list   = $values['settings'] ?? [];
        unset($values['settings']);
        $fromList = [];
        foreach (is_array($list) ? $list : [] as $item) {
            $item = (array) $item;
            if (isset($item['key']) && is_string($item['key']) && array_key_exists('value', $item)) {
                $fromList[$item['key']] = $item['value'];
            }
        }
        return $values + $fromList;
    }

    /**
     * @param callable(): array<string,mixed> $callback Settings API call
     *
     * @return array<string,mixed>|WP_Error
     */
    private function handle(callable $callback)
    {
        try {
            return $callback();
        } catch (EntityApiException $e) {
            return $e->toWpError();
        }
    }

    /**
     * @param string              $name   Ability name
     * @param array<string,mixed> $args   AbilityRegistration::register() arguments other than the permission callback
     * @param bool                $unsafe Whether the ability is unsafe
     *
     * @return void
     */
    private function register(string $name, array $args, bool $unsafe = false): void
    {
        AbilityRegistration::register(
            $name,
            $args + [
                'permission_callback' => static fn($input) => AbilityPermission::check(SettingsApi::CAPABILITY),
            ],
            $unsafe
        );
    }
}
