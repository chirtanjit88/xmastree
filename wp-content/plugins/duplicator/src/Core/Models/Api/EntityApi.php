<?php

/**
 * Entity API service
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Core\Models\Api;

use Duplicator\Core\CapMng;
use Duplicator\Utils\Logging\DupLog;
use Exception;
use Throwable;

/**
 * List, read, create, update and delete the entities registered for the entity API, and run their actions.
 *
 * The site availability of the entity capability is enforced here; user authorization belongs to the caller.
 */
class EntityApi
{
    /** @var string Filter that collects the entity classes exposed by the entity API */
    const ENTITIES_FILTER = 'duplicator_api_entities';
    /** @var string Filter that lets addons refuse an operation on an entity: reason or null, entity class, operation */
    const UNAVAILABLE_REASON_FILTER = 'duplicator_api_unavailable_reason';
    /** @var string Filter that lets addons refuse deleting a loaded entity: reason or null, entity */
    const DELETE_REFUSAL_FILTER = 'duplicator_api_delete_refusal';
    /** @var string Pattern of entity slugs and action names, which become CLI subcommands and ability names */
    const NAME_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    const OP_LIST   = 'list';
    const OP_GET    = 'get';
    const OP_CREATE = 'create';
    const OP_UPDATE = 'update';
    const OP_DELETE = 'delete';
    const OP_COPY   = 'copy';

    /**
     * Registered entity classes by slug
     *
     * @return array<string,class-string<AbstractApiEntity>>
     *
     * @throws Exception When a registered class does not declare a valid exposure
     */
    public function getEntityClasses(): array
    {
        $result = [];
        foreach ((array) apply_filters(self::ENTITIES_FILTER, []) as $class) {
            if (!is_string($class) || !is_subclass_of($class, AbstractApiEntity::class) || !method_exists($class, 'getById')) {
                throw new Exception('Entity API classes must be listable entities');
            }

            $slug = $class::apiSlug();
            if (preg_match(self::NAME_PATTERN, $slug) !== 1 || isset($result[$slug])) {
                throw new Exception('Invalid or duplicate entity API slug for ' . $class);
            }
            if ($class::apiCapability() === '') {
                throw new Exception('Entity API class ' . $class . ' declares no capability');
            }

            $actions = [];
            foreach ($class::apiActions() as $action) {
                $actions[] = $action->getName();
            }
            $reserved = [
                self::OP_LIST,
                self::OP_GET,
                self::OP_CREATE,
                self::OP_UPDATE,
                self::OP_DELETE,
                self::OP_COPY,
            ];
            if (count(array_intersect($actions, $reserved)) > 0 || count($actions) !== count(array_unique($actions))) {
                throw new Exception('Invalid or duplicate entity API action for ' . $class);
            }

            $result[$slug] = $class;
        }
        return $result;
    }

    /**
     * @param class-string<AbstractApiEntity> $class   Entity class
     * @param array<string,mixed>             $filters Values by filterable field name; only the items equal on every one are returned
     *
     * @return array<int,array<string,mixed>> The entities of the class it exposes
     *
     * @throws EntityApiException
     */
    public function list(string $class, array $filters = []): array
    {
        return $this->run($class, self::OP_LIST, static function () use ($class, $filters): array {
            $filters  = self::castFilters($class, $filters);
            $entities = call_user_func([$class, 'getAll']);
            if (!is_array($entities)) {
                throw new Exception('Unable to load the ' . $class::apiSlug() . ' list');
            }
            $entities = array_filter($entities, static fn(AbstractApiEntity $entity): bool => $class::apiExposes($entity));
            $items    = array_map(static fn(AbstractApiEntity $entity): array => $entity->toApi(), $entities);
            $items    = array_filter($items, static function (array $item) use ($filters): bool {
                foreach ($filters as $name => $value) {
                    if ($item[$name] !== $value) {
                        return false;
                    }
                }
                return true;
            });
            return array_values($items);
        });
    }

    /**
     * Copy an entity as the admin copy does, then set the passed values on the copy
     *
     * @param class-string<AbstractApiEntity> $class  Entity class
     * @param int                             $id     Id of the entity to copy
     * @param array<string,mixed>             $values Field values to change on the copy
     *
     * @return array<string,mixed> The copy
     *
     * @throws EntityApiException
     */
    public function copy(string $class, int $id, array $values = []): array
    {
        return $this->run($class, self::OP_COPY, function () use ($class, $id, $values): array {
            if (!$class::apiCanCopy()) {
                throw new EntityApiException(
                    EntityApiException::CODE_INVALID_ARGUMENT,
                    sprintf(__('%s can\'t be copied here.', 'duplicator'), $class::apiLabels()['plural'])
                );
            }
            $copy = $this->load($class, $id)->apiCopy();
            // Saved first, so the values apply to an existing entity and don't take the new-entity defaults
            $this->save($copy);
            if (count($values) > 0) {
                try {
                    $copy->setFromApi($values, false);
                    $this->save($copy);
                } catch (Throwable $e) {
                    $copy->delete();
                    throw $e;
                }
            }
            return $copy->toApi();
        });
    }

    /**
     * @param class-string<AbstractApiEntity> $class Entity class
     * @param int                             $id    Entity id
     *
     * @return array<string,mixed>
     *
     * @throws EntityApiException
     */
    public function get(string $class, int $id): array
    {
        return $this->run($class, self::OP_GET, fn(): array => $this->load($class, $id)->toApi());
    }

    /**
     * @param class-string<AbstractApiEntity> $class  Entity class
     * @param array<string,mixed>             $values Field values
     *
     * @return array<string,mixed> The created entity
     *
     * @throws EntityApiException
     */
    public function create(string $class, array $values): array
    {
        return $this->run($class, self::OP_CREATE, function () use ($class, $values): array {
            if (!$class::apiCanCreate()) {
                throw new EntityApiException(
                    EntityApiException::CODE_INVALID_ARGUMENT,
                    trim(
                        sprintf(__('%s can\'t be created here.', 'duplicator'), $class::apiLabels()['plural'])
                        . ' ' . $class::apiWriteNotice()
                    )
                );
            }
            $entity = new $class();
            $entity->setFromApi($values, true);
            $this->save($entity);
            return $entity->toApi();
        });
    }

    /**
     * @param class-string<AbstractApiEntity> $class  Entity class
     * @param int                             $id     Entity id
     * @param array<string,mixed>             $values Field values to change
     *
     * @return array<string,mixed> The updated entity
     *
     * @throws EntityApiException
     */
    public function update(string $class, int $id, array $values): array
    {
        return $this->run($class, self::OP_UPDATE, function () use ($class, $id, $values): array {
            self::checkWritable($class);
            if (count($values) === 0) {
                throw new EntityApiException(
                    EntityApiException::CODE_INVALID_ARGUMENT,
                    __('Pass at least one field to update.', 'duplicator')
                );
            }
            $entity = $this->load($class, $id);
            $entity->setFromApi($values, false);
            $this->save($entity);
            return $entity->toApi();
        });
    }

    /**
     * @param class-string<AbstractApiEntity> $class Entity class
     * @param int                             $id    Entity id
     *
     * @return void
     *
     * @throws EntityApiException
     */
    public function delete(string $class, int $id): void
    {
        $this->run($class, self::OP_DELETE, function () use ($class, $id): array {
            if (!$class::apiCanDelete()) {
                throw new EntityApiException(
                    EntityApiException::CODE_INVALID_ARGUMENT,
                    trim(
                        sprintf(__('%s can\'t be deleted here.', 'duplicator'), $class::apiLabels()['plural'])
                        . ' ' . $class::apiWriteNotice()
                    )
                );
            }
            $entity = $this->load($class, $id);
            $entity->apiCheckDelete();
            /**
             * Why an addon refuses deleting the entity, such as an item other entities still use; null allows it
             *
             * @param ?string           $reason Refusal reason
             * @param AbstractApiEntity $entity Entity to delete
             */
            $reason = apply_filters(self::DELETE_REFUSAL_FILTER, null, $entity);
            if (is_string($reason) && $reason !== '') {
                throw new EntityApiException(EntityApiException::CODE_CONFLICT, $reason);
            }
            if (!$entity->delete()) {
                throw new Exception('Unable to delete ' . $class::apiSlug() . ' ' . $id);
            }
            return [];
        });
    }

    /**
     * @param class-string<AbstractApiEntity> $class  Entity class
     * @param ApiAction                       $action One of the class actions
     * @param int                             $id     Entity id
     *
     * @return array<string,mixed> Action result with a message key
     *
     * @throws EntityApiException
     */
    public function runAction(string $class, ApiAction $action, int $id): array
    {
        return $this->run($class, $action->getName(), fn(): array => $action->execute($this->load($class, $id)));
    }

    /**
     * Check availability, run the operation and turn unexpected failures into a failed error
     *
     * @param class-string<AbstractApiEntity> $class     Entity class
     * @param string                          $operation An OP_* value or an action name
     * @param callable(): array<mixed>        $callback  Operation
     *
     * @return array<mixed>
     *
     * @throws EntityApiException
     */
    private function run(string $class, string $operation, callable $callback): array
    {
        if (!CapMng::isCapAvailableOnSite($class::apiCapability())) {
            throw new EntityApiException(
                EntityApiException::CODE_UNAVAILABLE,
                sprintf(__('%s are not available on this site.', 'duplicator'), $class::apiLabels()['plural'])
            );
        }
        $reason = apply_filters(self::UNAVAILABLE_REASON_FILTER, $class::apiUnavailableReason($operation), $class, $operation);
        if (is_string($reason)) {
            throw new EntityApiException(EntityApiException::CODE_UNAVAILABLE, $reason);
        }

        try {
            return $callback();
        } catch (EntityApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            DupLog::infoTraceException($e, 'Entity API: ' . $operation . ' ' . $class::apiSlug() . ' failed.');
            throw new EntityApiException(
                EntityApiException::CODE_FAILED,
                __('Duplicator could not complete the request. Check the Duplicator logs for details.', 'duplicator'),
                $e
            );
        }
    }

    /**
     * @param class-string<AbstractApiEntity> $class Entity class
     *
     * @return void
     *
     * @throws EntityApiException When the class declares no writable field
     */
    private static function checkWritable(string $class): void
    {
        if (!$class::apiHasWritableFields()) {
            throw new EntityApiException(
                EntityApiException::CODE_INVALID_ARGUMENT,
                trim(
                    sprintf(__('%s can\'t be updated here.', 'duplicator'), $class::apiLabels()['plural'])
                    . ' ' . $class::apiWriteNotice()
                )
            );
        }
    }

    /**
     * Check the listing filters and cast them to the field types
     *
     * @param class-string<AbstractApiEntity> $class   Entity class
     * @param array<string,mixed>             $filters Values by field name
     *
     * @return array<string,string|int|bool>
     *
     * @throws EntityApiException When a key is not a filterable field or a value is invalid
     */
    private static function castFilters(string $class, array $filters): array
    {
        $fields = [];
        foreach ($class::apiFields() as $field) {
            if ($field->isFilterable()) {
                $fields[$field->getName()] = $field;
            }
        }
        $unknown = array_diff(array_keys($filters), array_keys($fields));
        if (count($unknown) > 0) {
            throw new EntityApiException(
                EntityApiException::CODE_INVALID_ARGUMENT,
                sprintf(
                    /* translators: 1: comma separated field names, 2: comma separated field names */
                    __('Unknown or not filterable fields: %1$s. Filter on: %2$s.', 'duplicator'),
                    implode(', ', $unknown),
                    implode(', ', array_keys($fields))
                )
            );
        }
        $cast = [];
        foreach ($filters as $name => $value) {
            $cast[$name] = $fields[$name]->castFilter($value);
        }
        return $cast;
    }

    /**
     * Load an entity the class exposes
     *
     * @param class-string<AbstractApiEntity> $class Entity class
     * @param int                             $id    Entity id
     *
     * @return AbstractApiEntity
     *
     * @throws EntityApiException When no exposed entity of the class has the id
     */
    private function load(string $class, int $id): AbstractApiEntity
    {
        $entity = ($id > 0 ? call_user_func([$class, 'getById'], $id) : false);
        if (!$entity instanceof AbstractApiEntity || !$class::apiExposes($entity)) {
            throw new EntityApiException(
                EntityApiException::CODE_NOT_FOUND,
                sprintf(__('No %1$s exists with id %2$d.', 'duplicator'), $class::apiSlug(), $id)
            );
        }
        return $entity;
    }

    /**
     * @param AbstractApiEntity $entity Entity
     *
     * @return void
     */
    private function save(AbstractApiEntity $entity): void
    {
        if (!$entity->save()) {
            throw new Exception('Unable to save ' . $entity::apiSlug());
        }
    }
}
