<?php

/**
 * Entity API field
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Core\Models\Api;

use Exception;

/**
 * Description of a field an entity exposes through the entity API: its type, its constraints and its schema.
 * The entity reads and writes its values in toArray() and setFromArray().
 */
final class ApiField
{
    const TYPE_STRING       = 'string';
    const TYPE_INTEGER      = 'integer';
    const TYPE_BOOLEAN      = 'boolean';
    const TYPE_STRING_LIST  = 'string_list';
    const TYPE_INTEGER_LIST = 'integer_list';

    private const TYPES = [
        self::TYPE_STRING,
        self::TYPE_INTEGER,
        self::TYPE_BOOLEAN,
        self::TYPE_STRING_LIST,
        self::TYPE_INTEGER_LIST,
    ];

    private string $name;
    private string $type;
    private string $description;
    /** @var string[] */
    private array $enum = [];
    /** @var string[] */
    private array $writeEnum       = [];
    private ?int $minimum          = null;
    private ?int $maximum          = null;
    private bool $requiredOnCreate = false;
    private bool $writable         = true;
    private bool $readable         = true;
    private bool $destructive      = false;

    /**
     * @param string $name        Field name, the key of the value in toArray() and setFromArray()
     * @param string $type        One of the TYPE_* constants
     * @param string $description Description for API callers
     */
    public function __construct(string $name, string $type, string $description)
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new Exception('Unknown API field type: ' . $type);
        }
        $this->name        = $name;
        $this->type        = $type;
        $this->description = $description;
    }

    /**
     * Restrict a string field, or the items of a string list, to the given values
     *
     * @param string[] $values Allowed values
     *
     * @return self
     */
    public function enum(array $values): self
    {
        $this->enum = array_values($values);
        return $this;
    }

    /**
     * Accept on write only these values, when reads can return more of the enum
     *
     * @param string[] $values Writable values
     *
     * @return self
     */
    public function writeEnum(array $values): self
    {
        $this->writeEnum = array_values($values);
        return $this;
    }

    /**
     * Restrict an integer field, or the items of an integer list, to a range
     *
     * @param ?int $minimum Minimum value, null for none
     * @param ?int $maximum Maximum value, null for none
     *
     * @return self
     */
    public function range(?int $minimum, ?int $maximum = null): self
    {
        $this->minimum = $minimum;
        $this->maximum = $maximum;
        return $this;
    }

    /**
     * @return self
     */
    public function requiredOnCreate(): self
    {
        $this->requiredOnCreate = true;
        return $this;
    }

    /**
     * @return self
     */
    public function readOnly(): self
    {
        $this->writable = false;
        return $this;
    }

    /**
     * Accept the field on write but never return it or list it in output schemas
     *
     * @return self
     */
    public function writeOnly(): self
    {
        $this->readable = false;
        return $this;
    }

    /**
     * Mark the field as one whose write can remove data
     *
     * @return self
     */
    public function destructive(): self
    {
        $this->destructive = true;
        return $this;
    }

    /**
     * @return bool Whether a write of the field can remove data
     */
    public function isDestructive(): bool
    {
        return $this->destructive;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return bool
     */
    public function isRequiredOnCreate(): bool
    {
        return $this->requiredOnCreate;
    }

    /**
     * @return bool
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * @return bool Whether reads return the field
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * @return bool Whether a listing can filter on the field: a readable field that is not a list
     */
    public function isFilterable(): bool
    {
        return $this->readable && in_array($this->type, [self::TYPE_STRING, self::TYPE_INTEGER, self::TYPE_BOOLEAN], true);
    }

    /**
     * Cast a listing filter value to the field type, parsed as cast() does; enum and range are not checked,
     * a value no item has matches nothing
     *
     * @param mixed $value Filter value
     *
     * @return string|int|bool
     */
    public function castFilter($value)
    {
        switch ($this->type) {
            case self::TYPE_STRING:
                if (!is_string($value)) {
                    throw $this->invalid(sprintf(__('%s must be a string.', 'duplicator'), $this->name));
                }
                return $value;
            case self::TYPE_INTEGER:
                if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                    $value = (int) $value;
                }
                if (!is_int($value)) {
                    throw $this->invalid(sprintf(__('%s must be an integer.', 'duplicator'), $this->name));
                }
                return $value;
            default:
                return (bool) $this->cast($value);
        }
    }

    /**
     * Cast an API or command line value to the field type. Strings are parsed: integers from digits,
     * booleans from true/false/1/0, lists from comma separated values.
     *
     * @param mixed $value Input value
     *
     * @return mixed The value in the field type
     *
     * @throws EntityApiException When the value does not fit the field
     */
    public function cast($value)
    {
        switch ($this->type) {
            case self::TYPE_STRING:
                return $this->castString($value);
            case self::TYPE_INTEGER:
                return $this->castInteger($value);
            case self::TYPE_BOOLEAN:
                if (is_bool($value)) {
                    return $value;
                }
                if (in_array($value, ['true', '1', 1], true)) {
                    return true;
                }
                if (in_array($value, ['false', '0', 0], true)) {
                    return false;
                }
                throw $this->invalid(sprintf(__('%s must be true or false.', 'duplicator'), $this->name));
            default:
                if (is_string($value)) {
                    $value = ($value === '' ? [] : array_map('trim', explode(',', $value)));
                }
                if (!is_array($value)) {
                    throw $this->invalid(sprintf(__('%s must be a list.', 'duplicator'), $this->name));
                }
                $items = [];
                foreach ($value as $item) {
                    $items[] = ($this->type === self::TYPE_STRING_LIST ? $this->castString($item) : $this->castInteger($item));
                }
                return array_values(array_unique($items));
        }
    }

    /**
     * Convert a value the entity returns to the field type, for output
     *
     * @param mixed $value Value from toArray()
     *
     * @return mixed
     */
    public function normalize($value)
    {
        switch ($this->type) {
            case self::TYPE_STRING:
                return (string) $value;
            case self::TYPE_INTEGER:
                return (int) $value;
            case self::TYPE_BOOLEAN:
                return (bool) $value;
            case self::TYPE_STRING_LIST:
                return array_values(array_map('strval', (array) $value));
            default:
                return array_values(array_map('intval', (array) $value));
        }
    }

    /**
     * @param bool $forWrite True for the schema of the written value
     *
     * @return array<string,mixed> JSON schema of the field
     */
    public function getSchema(bool $forWrite = false): array
    {
        $item = [];
        switch ($this->type) {
            case self::TYPE_STRING:
            case self::TYPE_STRING_LIST:
                $item['type'] = 'string';
                $enum         = ($forWrite ? $this->getWriteEnum() : $this->enum);
                if (count($enum) > 0) {
                    $item['enum'] = $enum;
                }
                break;
            case self::TYPE_INTEGER:
            case self::TYPE_INTEGER_LIST:
                $item['type'] = 'integer';
                if ($this->minimum !== null) {
                    $item['minimum'] = $this->minimum;
                }
                if ($this->maximum !== null) {
                    $item['maximum'] = $this->maximum;
                }
                break;
            default:
                $item['type'] = 'boolean';
                break;
        }

        if ($this->type === self::TYPE_STRING_LIST || $this->type === self::TYPE_INTEGER_LIST) {
            $schema = [
                'type'  => 'array',
                'items' => $item,
            ];
        } else {
            $schema = $item;
        }
        $schema['description'] = $this->description;

        return $schema;
    }

    /**
     * @param mixed $value Input value
     *
     * @return string
     */
    private function castString($value): string
    {
        if (!is_string($value)) {
            throw $this->invalid(sprintf(__('%s must be a string.', 'duplicator'), $this->name));
        }
        $enum = $this->getWriteEnum();
        if (count($enum) > 0 && !in_array($value, $enum, true)) {
            throw $this->invalid(
                sprintf(__('%1$s must be one of: %2$s.', 'duplicator'), $this->name, implode(', ', $enum))
            );
        }
        return $value;
    }

    /**
     * @return string[] Values accepted on write, empty for any
     */
    private function getWriteEnum(): array
    {
        return (count($this->writeEnum) > 0 ? $this->writeEnum : $this->enum);
    }

    /**
     * @param mixed $value Input value
     *
     * @return int
     */
    private function castInteger($value): int
    {
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $value = (int) $value;
        }
        if (!is_int($value)) {
            throw $this->invalid(sprintf(__('%s must be an integer.', 'duplicator'), $this->name));
        }
        if ($this->minimum !== null && $value < $this->minimum) {
            throw $this->invalid(sprintf(__('%1$s must be at least %2$d.', 'duplicator'), $this->name, $this->minimum));
        }
        if ($this->maximum !== null && $value > $this->maximum) {
            throw $this->invalid(sprintf(__('%1$s must be at most %2$d.', 'duplicator'), $this->name, $this->maximum));
        }
        return $value;
    }

    /**
     * @param string $message Translated message
     *
     * @return EntityApiException
     */
    private function invalid(string $message): EntityApiException
    {
        return new EntityApiException(EntityApiException::CODE_INVALID_ARGUMENT, $message);
    }
}
