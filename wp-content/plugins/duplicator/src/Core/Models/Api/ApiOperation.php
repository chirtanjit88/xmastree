<?php

/**
 * Operation an addon exposes through the operation API
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Core\Models\Api;

use Closure;
use Duplicator\Core\CapMng;
use Exception;
use WP_Error;

/**
 * An operation declared through OperationApi, which the API interfaces expose under its group and command name.
 */
final class ApiOperation
{
    private string $group;
    private string $command;
    private string $label;
    private string $description;
    private string $capability;
    private Closure $callback;
    /** @var array<string,ApiField> */
    private array $inputs = [];
    /** @var string[] Names of the required inputs */
    private array $required = [];
    /** @var array<string,array<string,mixed>> */
    private array $output     = [];
    private bool $readonly    = false;
    private bool $destructive = false;
    private bool $sensitive   = false;
    private bool $idempotent  = false;

    /**
     * @param string  $group       Group name, lowercase words separated by dashes, such as recovery
     * @param string  $command     Command name in the group, lowercase words separated by dashes, such as get
     * @param string  $label       Short title
     * @param string  $description What the operation does, for the user and API callers
     * @param string  $capability  Capability the caller needs
     * @param Closure $callback    Receives the input values by name, cast to their field type, and returns the result
     *                             array or a WP_Error
     */
    public function __construct(
        string $group,
        string $command,
        string $label,
        string $description,
        string $capability,
        Closure $callback
    ) {
        if (preg_match(EntityApi::NAME_PATTERN, $group) !== 1 || preg_match(EntityApi::NAME_PATTERN, $command) !== 1) {
            throw new Exception('Invalid API operation name: ' . $group . ' ' . $command);
        }
        $this->group       = $group;
        $this->command     = $command;
        $this->label       = $label;
        $this->description = $description;
        $this->capability  = $capability;
        $this->callback    = $callback;
    }

    /**
     * Add an input
     *
     * @param ApiField $field    Input field
     * @param bool     $required Whether the caller must pass it
     *
     * @return self
     */
    public function input(ApiField $field, bool $required = false): self
    {
        $this->inputs[$field->getName()] = $field;
        if ($required) {
            $this->required[] = $field->getName();
        }
        return $this;
    }

    /**
     * @param array<string,array<string,mixed>> $properties JSON schema of the result keys
     *
     * @return self
     */
    public function output(array $properties): self
    {
        $this->output = $properties;
        return $this;
    }

    /**
     * The operation only reads
     *
     * @return self
     */
    public function readonly(): self
    {
        $this->readonly = true;
        return $this;
    }

    /**
     * The operation can remove data
     *
     * @return self
     */
    public function destructive(): self
    {
        $this->destructive = true;
        return $this;
    }

    /**
     * The operation runs only while the site allows AI assistants to run sensitive actions
     *
     * @return self
     */
    public function sensitive(): self
    {
        $this->sensitive = true;
        return $this;
    }

    /**
     * Repeating the operation with the same input changes nothing more
     *
     * @return self
     */
    public function idempotent(): self
    {
        $this->idempotent = true;
        return $this;
    }

    /**
     * @return string Full name, <group>-<command>
     */
    public function getName(): string
    {
        return $this->group . '-' . $this->command;
    }

    /**
     * @return string
     */
    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * @return string
     */
    public function getCommand(): string
    {
        return $this->command;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getCapability(): string
    {
        return $this->capability;
    }

    /**
     * @return array<string,ApiField> The operation inputs and those addons add, by name
     */
    public function getInputs(): array
    {
        return $this->inputs + OperationApi::getAddedInputs($this->getName(), $this->inputs);
    }

    /**
     * @return string[] Names of the required inputs
     */
    public function getRequired(): array
    {
        return $this->required;
    }

    /**
     * @return array<string,array<string,mixed>> JSON schema of the result keys
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * @return bool
     */
    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    /**
     * @return bool
     */
    public function isDestructive(): bool
    {
        return $this->destructive;
    }

    /**
     * @return bool
     */
    public function isSensitive(): bool
    {
        return $this->sensitive;
    }

    /**
     * @return bool
     */
    public function isIdempotent(): bool
    {
        return $this->idempotent;
    }

    /**
     * Run the operation: refused when the site does not allow its capability; the passed inputs are cast to their
     * field type, unknown keys ignored
     *
     * @param array<string,mixed> $input Input values by name
     *
     * @return array<string,mixed>|WP_Error
     */
    public function execute(array $input)
    {
        if (!CapMng::isCapAvailableOnSite($this->capability)) {
            return new WP_Error(
                'duplicator_backup_unavailable',
                __('This operation is not available on this site.', 'duplicator'),
                ['status' => 403]
            );
        }

        $values = [];
        try {
            foreach ($this->getInputs() as $name => $field) {
                if (array_key_exists($name, $input)) {
                    $values[$name] = $field->cast($input[$name]);
                } elseif (in_array($name, $this->required, true)) {
                    throw new EntityApiException(
                        EntityApiException::CODE_INVALID_ARGUMENT,
                        /* translators: %s: input name */
                        sprintf(__('%s is required.', 'duplicator'), $name)
                    );
                }
            }
        } catch (EntityApiException $e) {
            return $e->toWpError();
        }

        $result = ($this->callback)($values);
        if (!is_array($result) && !$result instanceof WP_Error) {
            throw new Exception('API operation ' . $this->getName() . ' must return an array or a WP_Error');
        }
        return $result;
    }
}
