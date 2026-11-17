<?php

/**
 * Entity API action
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Core\Models\Api;

use Closure;
use Duplicator\Core\Models\AbstractEntity;
use Exception;

/**
 * An operation an entity exposes through the entity API beyond create, read, update and delete.
 */
final class ApiAction
{
    private string $name;
    private string $label;
    private string $description;
    private Closure $callback;
    /** @var array<string,array<string,mixed>> */
    private array $resultSchema;
    private bool $destructive;

    /**
     * @param string                            $name         Action name, lowercase words separated by dashes
     * @param string                            $label        Short title
     * @param string                            $description  What the action does, for the user and API callers
     * @param Closure                           $callback     Receives the entity, returns the result with a message key;
     *                                                        throws EntityApiException to refuse
     * @param array<string,array<string,mixed>> $resultSchema JSON schema of the result keys other than message
     * @param bool                              $destructive  Whether the action can remove data
     */
    public function __construct(
        string $name,
        string $label,
        string $description,
        Closure $callback,
        array $resultSchema = [],
        bool $destructive = false
    ) {
        if (preg_match(EntityApi::NAME_PATTERN, $name) !== 1) {
            throw new Exception('Invalid API action name: ' . $name);
        }
        $this->name         = $name;
        $this->label        = $label;
        $this->description  = $description;
        $this->callback     = $callback;
        $this->resultSchema = $resultSchema;
        $this->destructive  = $destructive;
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
     * @return array<string,array<string,mixed>> JSON schema of the result keys other than message
     */
    public function getResultSchema(): array
    {
        return $this->resultSchema;
    }

    /**
     * @return bool Whether the action can remove data
     */
    public function isDestructive(): bool
    {
        return $this->destructive;
    }

    /**
     * @param AbstractEntity $entity Entity the action runs on
     *
     * @return array<string,mixed> Result with a message key
     */
    public function execute(AbstractEntity $entity): array
    {
        $result = ($this->callback)($entity);
        if (!is_array($result) || !is_string($result['message'] ?? null)) {
            throw new Exception('API action ' . $this->name . ' must return an array with a message');
        }
        return $result;
    }
}
