<?php

/**
 * Entity exposed through the entity API
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Core\Models\Api;

use Duplicator\Core\Models\AbstractEntity;
use Exception;

/**
 * Base class of the entities the entity API can expose: declares the API hooks and maps the declared fields
 * to and from API values
 */
abstract class AbstractApiEntity extends AbstractEntity
{
    /**
     * Slug the entity API exposes the entity under
     *
     * @return string
     */
    abstract public static function apiSlug(): string;

    /**
     * Display labels of the entity in the entity API
     *
     * @return array{singular:string,plural:string}
     */
    public static function apiLabels(): array
    {
        return [
            'singular' => static::apiSlug(),
            'plural'   => static::apiSlug(),
        ];
    }

    /**
     * Capability a user needs to manage the entity through the entity API
     *
     * @return string
     */
    abstract public static function apiCapability(): string;

    /**
     * Fields the entity API can read and write
     *
     * @return ApiField[]
     */
    abstract public static function apiFields(): array;

    /**
     * Entity operations the entity API exposes beyond create, read, update and delete
     *
     * @return ApiAction[]
     */
    public static function apiActions(): array
    {
        return [];
    }

    /**
     * One sentence telling the user what the actions do, with the verbs in <b>, shown in place of the per-action
     * descriptions; empty to list the description of each action
     *
     * @return string
     */
    public static function apiActionsSummary(): string
    {
        return '';
    }

    /**
     * Why the entity API refuses an operation right now
     *
     * @param string $operation An EntityApi::OP_* value or an action name
     *
     * @return ?string Translated reason, null when the operation is available
     */
    public static function apiUnavailableReason(string $operation): ?string
    {
        return null;
    }

    /**
     * Whether the entity API exposes a loaded instance; by default only instances of exactly the class,
     * since subclasses can share the id space
     *
     * @param AbstractEntity $entity Instance loaded by id or listed
     *
     * @return bool
     */
    public static function apiExposes(AbstractEntity $entity): bool
    {
        return get_class($entity) === static::class;
    }

    /**
     * Whether the entity declares any writable field, without which it can't be created or updated
     *
     * @return bool
     */
    final public static function apiHasWritableFields(): bool
    {
        foreach (static::apiFields() as $field) {
            if ($field->isWritable()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the entity API can create the entity; by default when it declares a writable field
     *
     * @return bool
     */
    public static function apiCanCreate(): bool
    {
        return static::apiHasWritableFields();
    }

    /**
     * Whether the entity API can delete the entity; by default it can
     *
     * @return bool
     */
    public static function apiCanDelete(): bool
    {
        return true;
    }

    /**
     * Whether the entity API can copy the entity, through apiCopy()
     *
     * @return bool
     */
    public static function apiCanCopy(): bool
    {
        return false;
    }

    /**
     * A new, unsaved entity with the settings of this one, as the admin copy makes it
     *
     * @return AbstractApiEntity
     *
     * @throws EntityApiException When the copy is refused
     */
    public function apiCopy(): AbstractApiEntity
    {
        throw new Exception(static::class . ' can\'t be copied');
    }

    /**
     * What the entity API can't write and why, appended to the write descriptions and refusals
     *
     * @return string Translated sentences, empty for none
     */
    public static function apiWriteNotice(): string
    {
        return '';
    }

    /**
     * Refuse deleting this entity through the entity API
     *
     * @return void
     *
     * @throws EntityApiException When the entity can't be deleted
     */
    public function apiCheckDelete(): void
    {
    }

    /**
     * Set the passed API values on the declared writable fields through setFromArray(); fields not passed
     * keep their value, or take the entity default on create.
     *
     * @param array<string,mixed> $values   Values by field name
     * @param bool                $isCreate True when the entity is new
     *
     * @return void
     *
     * @throws EntityApiException When a key is not a writable field or a value is invalid
     */
    final public function setFromApi(array $values, bool $isCreate): void
    {
        $fields = [];
        foreach (static::apiFields() as $field) {
            if ($field->isWritable()) {
                $fields[$field->getName()] = $field;
            }
        }

        $unknown = array_diff(array_keys($values), array_keys($fields));
        if (count($unknown) > 0) {
            throw new EntityApiException(
                EntityApiException::CODE_INVALID_ARGUMENT,
                sprintf(__('Unknown or read-only fields: %s.', 'duplicator'), implode(', ', $unknown))
            );
        }

        $cast = [];
        foreach ($fields as $name => $field) {
            if (array_key_exists($name, $values)) {
                $cast[$name] = $field->cast($values[$name]);
            } elseif ($isCreate && $field->isRequiredOnCreate()) {
                throw new EntityApiException(
                    EntityApiException::CODE_INVALID_ARGUMENT,
                    sprintf(__('The %s field is required.', 'duplicator'), $name)
                );
            }
        }

        $this->setFromArray($cast);
    }

    /**
     * The entity id and the declared readable fields
     *
     * @return array<string,mixed>
     */
    final public function toApi(): array
    {
        $values = $this->toArray();
        $result = ['id' => $this->getId()];
        foreach (static::apiFields() as $field) {
            if (!$field->isReadable()) {
                continue;
            }
            if (!array_key_exists($field->getName(), $values)) {
                throw new Exception(static::class . '::toArray() does not return the field ' . $field->getName());
            }
            $result[$field->getName()] = $field->normalize($values[$field->getName()]);
        }
        return $result;
    }

    /**
     * Set the passed values, the only place that holds the entity's write rules: shared by the admin form
     * and the entity API. Keys not passed keep their value; on a new entity they take the entity default.
     *
     * @param array<string,mixed> $values Values by field name, already of the field type
     *
     * @return void
     *
     * @throws EntityApiException When a value or the resulting state is invalid
     */
    abstract protected function setFromArray(array $values): void;

    /**
     * The readable field values by field name
     *
     * @return array<string,mixed>
     */
    abstract protected function toArray(): array;
}
