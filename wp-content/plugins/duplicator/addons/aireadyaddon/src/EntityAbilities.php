<?php

/**
 * DUPLICATOR AI READY ADDON - ENTITY ABILITIES
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Addons\AiReadyAddon;

use Duplicator\Core\Models\Api\AbstractApiEntity;
use Duplicator\Core\Models\Api\ApiAction;
use Duplicator\Core\Models\Api\EntityApi;
use Duplicator\Core\Models\Api\EntityApiException;
use Duplicator\Libs\Snap\SnapString;
use stdClass;
use WP_Error;

/**
 * Registers the list, get, create, update, copy, delete and action abilities of every entity exposed by the entity API.
 */
final class EntityAbilities
{
    private EntityApi $api;

    /**
     * @param EntityApi $api Entity API service
     */
    public function __construct(EntityApi $api)
    {
        $this->api = $api;
    }

    /**
     * @return void
     */
    public function registerAbilities(): void
    {
        foreach ($this->api->getEntityClasses() as $slug => $class) {
            $this->registerEntityAbilities($slug, $class);
        }
    }

    /**
     * @param class-string<AbstractApiEntity> $class Entity class
     * @param mixed                           $input Ability input with the filters
     *
     * @return array<string,mixed>|WP_Error
     */
    public function handleList(string $class, $input = null)
    {
        try {
            $items = $this->api->list($class, (array) $input);
        } catch (EntityApiException $e) {
            return $e->toWpError();
        }

        return [
            'count'                            => count($items),
            self::getPlural($class::apiSlug()) => $items,
        ];
    }

    /**
     * @param class-string<AbstractApiEntity> $class Entity class
     * @param mixed                           $input Ability input with the id
     *
     * @return array<string,mixed>|WP_Error
     */
    public function handleGet(string $class, $input)
    {
        try {
            return $this->api->get($class, AbilitiesRegistrar::getInputId($input));
        } catch (EntityApiException $e) {
            return $e->toWpError();
        }
    }

    /**
     * @param class-string<AbstractApiEntity> $class Entity class
     * @param mixed                           $input Ability input with the field values
     *
     * @return array<string,mixed>|WP_Error
     */
    public function handleCreate(string $class, $input)
    {
        try {
            return $this->api->create($class, (array) $input);
        } catch (EntityApiException $e) {
            return $e->toWpError();
        }
    }

    /**
     * @param class-string<AbstractApiEntity> $class Entity class
     * @param mixed                           $input Ability input with the id and the field values to change
     *
     * @return array<string,mixed>|WP_Error
     */
    public function handleUpdate(string $class, $input)
    {
        $values = (array) $input;
        unset($values['id']);

        try {
            return $this->api->update($class, AbilitiesRegistrar::getInputId($input), $values);
        } catch (EntityApiException $e) {
            return $e->toWpError();
        }
    }

    /**
     * @param class-string<AbstractApiEntity> $class Entity class
     * @param mixed                           $input Ability input with the id and the field values to set on the copy
     *
     * @return array<string,mixed>|WP_Error
     */
    public function handleCopy(string $class, $input)
    {
        $values = (array) $input;
        unset($values['id']);

        try {
            return $this->api->copy($class, AbilitiesRegistrar::getInputId($input), $values);
        } catch (EntityApiException $e) {
            return $e->toWpError();
        }
    }

    /**
     * Delete an item; the ability runs only while AI assistants may run sensitive actions.
     *
     * @param class-string<AbstractApiEntity> $class Entity class
     * @param mixed                           $input Ability input with the id
     *
     * @return array{id:int,message:string}|WP_Error
     */
    public function handleDelete(string $class, $input)
    {
        $id = AbilitiesRegistrar::getInputId($input);
        try {
            $this->api->delete($class, $id);
        } catch (EntityApiException $e) {
            return $e->toWpError();
        }

        return [
            'id'      => $id,
            /* translators: 1: item type, 2: item id */
            'message' => sprintf(__('Deleted %1$s %2$d.', 'duplicator'), $class::apiSlug(), $id),
        ];
    }

    /**
     * @param class-string<AbstractApiEntity> $class  Entity class
     * @param ApiAction                       $action Action to run
     * @param mixed                           $input  Ability input with the id
     *
     * @return array<string,mixed>|WP_Error
     */
    public function handleAction(string $class, ApiAction $action, $input)
    {
        try {
            return $this->api->runAction($class, $action, AbilitiesRegistrar::getInputId($input));
        } catch (EntityApiException $e) {
            return $e->toWpError();
        }
    }

    /**
     * @param string                          $slug  Entity slug
     * @param class-string<AbstractApiEntity> $class Entity class
     *
     * @return void
     */
    private function registerEntityAbilities(string $slug, string $class): void
    {
        $labels      = $class::apiLabels();
        $capability  = $class::apiCapability();
        $plural      = self::getPlural($slug);
        $itemSchema  = self::getItemSchema($class);
        $idSchema    = [
            'type'        => 'integer',
            'minimum'     => 1,
            'description' => sprintf('Id of the %s, from %s-list.', $slug, $slug),
        ];
        $idInput     = [
            'type'       => 'object',
            'required'   => ['id'],
            'properties' => ['id' => $idSchema],
        ];
        $readOnly    = ['readonly' => true];
        $hasWritable = $class::apiHasWritableFields();
        $canDelete   = $class::apiCanDelete();
        if ($hasWritable && $canDelete) {
            /* translators: %s: item type */
            $listDescription = __('Returns every %1$s with its settings. Pass an id to %1$s-get, %1$s-update or %1$s-delete.', 'duplicator');
        } elseif ($hasWritable) {
            /* translators: %s: item type */
            $listDescription = __('Returns every %1$s with its settings. Pass an id to %1$s-get or %1$s-update.', 'duplicator');
        } elseif ($canDelete) {
            /* translators: %s: item type */
            $listDescription = __('Returns every %1$s with its settings. Pass an id to %1$s-get or %1$s-delete.', 'duplicator');
        } else {
            /* translators: %s: item type */
            $listDescription = __('Returns every %1$s with its settings. Pass an id to %1$s-get.', 'duplicator');
        }
        $this->register('duplicator/' . $slug . '-list', $capability, [
            /* translators: %s: item type plural */
            'label'            => sprintf(__('List %s', 'duplicator'), $labels['plural']),
            'description'      => sprintf($listDescription, $slug),
            'execute_callback' => fn($input) => $this->handleList($class, $input),
            'input_schema'     => self::getListInputSchema($class),
            'output_schema'    => [
                'type'       => 'object',
                'properties' => [
                    'count' => [
                        'type'        => 'integer',
                        'description' => 'Number of items returned.',
                    ],
                    $plural => [
                        'type'  => 'array',
                        'items' => $itemSchema,
                    ],
                ],
            ],
            'annotations'      => $readOnly,
        ]);

        $this->register('duplicator/' . $slug . '-get', $capability, [
            /* translators: %s: item type */
            'label'            => sprintf(__('Get %s', 'duplicator'), $labels['singular']),
            /* translators: %s: item type */
            'description'      => sprintf(__('Returns one %s by id.', 'duplicator'), $slug),
            'execute_callback' => fn($input) => $this->handleGet($class, $input),
            'input_schema'     => $idInput,
            'output_schema'    => $itemSchema,
            'annotations'      => $readOnly,
        ]);

        if ($hasWritable) {
            $this->registerWriteAbilities($slug, $class, $idSchema, $itemSchema);
        }

        if ($canDelete) {
            $this->register('duplicator/' . $slug . '-delete', $capability, [
                /* translators: %s: item type */
                'label'            => sprintf(__('Delete %s', 'duplicator'), $labels['singular']),
                'description'      => sprintf(
                    /* translators: %s: item type */
                    SnapString::oneLine(__(
                        'Permanently deletes the %s with the given id.
                        Works only when sensitive actions are allowed in Duplicator > Settings > General.',
                        'duplicator'
                    )),
                    $slug
                ),
                'execute_callback' => fn($input) => $this->handleDelete($class, $input),
                'input_schema'     => $idInput,
                'output_schema'    => [
                    'type'       => 'object',
                    'properties' => [
                        'id'      => $idSchema,
                        'message' => [
                            'type'        => 'string',
                            'description' => 'Confirmation message.',
                        ],
                    ],
                ],
                'annotations'      => [
                    'readonly'    => false,
                    'destructive' => true,
                ],
            ]);
        }

        foreach ($class::apiActions() as $action) {
            $this->register('duplicator/' . $slug . '-' . $action->getName(), $capability, [
                'label'            => $action->getLabel(),
                'description'      => $action->getDescription(),
                'execute_callback' => fn($input) => $this->handleAction($class, $action, $input),
                'input_schema'     => $idInput,
                'output_schema'    => [
                    'type'       => 'object',
                    'properties' => $action->getResultSchema() + [
                        'message' => [
                            'type'        => 'string',
                            'description' => 'Result message.',
                        ],
                    ],
                ],
                'annotations'      => [
                    'readonly'    => false,
                    'destructive' => $action->isDestructive(),
                ],
            ]);
        }
    }

    /**
     * Register the update ability of an entity with writable fields, and its create ability when it can be created
     *
     * @param string                          $slug       Entity slug
     * @param class-string<AbstractApiEntity> $class      Entity class
     * @param array<string,mixed>             $idSchema   JSON schema of the id input
     * @param array<string,mixed>             $itemSchema JSON schema of the entity
     *
     * @return void
     */
    private function registerWriteAbilities(string $slug, string $class, array $idSchema, array $itemSchema): void
    {
        $labels      = $class::apiLabels();
        $capability  = $class::apiCapability();
        $notDestruct = [
            'readonly'    => false,
            'destructive' => false,
        ];

        $writable    = [];
        $required    = [];
        $destructive = [];
        foreach ($class::apiFields() as $field) {
            if (!$field->isWritable()) {
                continue;
            }
            $writable[$field->getName()] = $field->getSchema(true);
            if ($field->isRequiredOnCreate()) {
                $required[] = $field->getName();
            }
            if ($field->isDestructive()) {
                $destructive[] = $field->getName();
            }
        }
        $notice = $class::apiWriteNotice();
        if (count($destructive) > 0) {
            $notice = trim($notice . ' ' . sprintf(
                SnapString::oneLine(
                    /* translators: %s: comma separated field names */
                    __(
                        'Passing %s works only when sensitive actions are allowed in Duplicator > Settings > General, and is a
                        sensitive action: ask the user to confirm before passing it.',
                        'duplicator'
                    )
                ),
                implode(', ', $destructive)
            ));
        }

        if ($class::apiCanCreate()) {
            $createInput = [
                'type'                 => 'object',
                'properties'           => $writable,
                'additionalProperties' => false,
            ];
            if (count($required) > 0) {
                $createInput['required'] = $required;
            }

            $this->register('duplicator/' . $slug . '-create', $capability, [
                /* translators: %s: item type */
                'label'            => sprintf(__('Create %s', 'duplicator'), $labels['singular']),
                'description'      => trim(
                    sprintf(
                        /* translators: %s: item type */
                        __('Creates a %1$s. Fields not passed take their default values. Returns the new %1$s.', 'duplicator'),
                        $slug
                    ) . ' ' . $notice
                ),
                'execute_callback' => self::guardDestructiveFields(fn($input) => $this->handleCreate($class, $input), $destructive),
                'input_schema'     => $createInput,
                'output_schema'    => $itemSchema,
                'annotations'      => $notDestruct,
            ]);
        }

        $this->register('duplicator/' . $slug . '-update', $capability, [
            /* translators: %s: item type */
            'label'            => sprintf(__('Update %s', 'duplicator'), $labels['singular']),
            'description'      => trim(
                sprintf(
                    /* translators: %s: item type */
                    __(
                        'Changes the passed fields of the %1$s with the given id; fields not passed keep their current values. Returns the updated %1$s.',
                        'duplicator'
                    ),
                    $slug
                ) . ' ' . $notice
            ),
            'execute_callback' => self::guardDestructiveFields(fn($input) => $this->handleUpdate($class, $input), $destructive),
            'input_schema'     => [
                'type'                 => 'object',
                'required'             => ['id'],
                'properties'           => ['id' => $idSchema] + $writable,
                'additionalProperties' => false,
            ],
            'output_schema'    => $itemSchema,
            'annotations'      => $notDestruct,
        ]);

        if ($class::apiCanCopy()) {
            $this->register('duplicator/' . $slug . '-copy', $capability, [
                /* translators: %s: item type */
                'label'            => sprintf(__('Copy %s', 'duplicator'), $labels['singular']),
                'description'      => trim(
                    sprintf(
                        /* translators: %s: item type */
                        SnapString::oneLine(__(
                            'Copies the %1$s with the given id, as the admin copy does, then sets the passed fields on the copy;
                            without name the copy is named after the original. Returns the new %1$s.',
                            'duplicator'
                        )),
                        $slug
                    ) . ' ' . $notice
                ),
                'execute_callback' => self::guardDestructiveFields(fn($input) => $this->handleCopy($class, $input), $destructive),
                'input_schema'     => [
                    'type'                 => 'object',
                    'required'             => ['id'],
                    'properties'           => ['id' => $idSchema] + $writable,
                    'additionalProperties' => false,
                ],
                'output_schema'    => $itemSchema,
                'annotations'      => $notDestruct,
            ]);
        }
    }

    /**
     * Wrap a create, update or copy callback: an input passing a destructive field runs only while the site allows AI
     * assistants to run sensitive actions
     *
     * @param callable $execute     Execute callback
     * @param string[] $destructive Names of the destructive fields
     *
     * @return callable
     */
    private static function guardDestructiveFields(callable $execute, array $destructive): callable
    {
        if (count($destructive) === 0) {
            return $execute;
        }
        $guarded = AbilityPermission::guardUnsafe($execute);
        return static function ($input = null) use ($execute, $guarded, $destructive) {
            $passed = array_intersect_key((array) $input, array_flip($destructive));
            return (count($passed) > 0 ? $guarded($input) : $execute($input));
        };
    }

    /**
     * @param string              $name       Ability name
     * @param string              $capability Capability the caller needs
     * @param array<string,mixed> $args       AbilityRegistration::register() arguments other than the permission callback
     *
     * @return void
     */
    private function register(string $name, string $capability, array $args): void
    {
        AbilityRegistration::register($name, $args + [
            'permission_callback' => static fn($input) => AbilityPermission::check($capability),
        ]);
    }

    /**
     * @param class-string<AbstractApiEntity> $class Entity class
     *
     * @return array<string,mixed> JSON schema of the list input: one optional filter per filterable field
     */
    private static function getListInputSchema(string $class): array
    {
        $properties = [];
        foreach ($class::apiFields() as $field) {
            if ($field->isFilterable()) {
                $schema                        = $field->getSchema();
                $schema['description']         = 'Return only the items with this value. ' . $schema['description'];
                $properties[$field->getName()] = $schema;
            }
        }

        return [
            'type'                 => 'object',
            'properties'           => (count($properties) > 0 ? $properties : new stdClass()),
            'additionalProperties' => false,
            'default'              => new stdClass(),
        ];
    }

    /**
     * @param class-string<AbstractApiEntity> $class Entity class
     *
     * @return array<string,mixed> JSON schema of the entity as the entity API returns it
     */
    private static function getItemSchema(string $class): array
    {
        $properties = [
            'id' => [
                'type'        => 'integer',
                'description' => 'Unique id.',
            ],
        ];
        foreach ($class::apiFields() as $field) {
            if ($field->isReadable()) {
                $properties[$field->getName()] = $field->getSchema();
            }
        }

        return [
            'type'       => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * @param string $slug Entity slug
     *
     * @return string The plural used in list results
     */
    private static function getPlural(string $slug): string
    {
        return $slug . 's';
    }
}
