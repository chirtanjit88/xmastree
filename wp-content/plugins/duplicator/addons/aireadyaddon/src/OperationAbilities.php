<?php

/**
 * Abilities of the operations addons declare
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Addons\AiReadyAddon;

use Duplicator\Core\Models\Api\ApiOperation;
use Duplicator\Core\Models\Api\OperationApi;
use stdClass;

/**
 * Registers the duplicator/<group>-<command> ability of every operation declared through OperationApi.
 */
final class OperationAbilities
{
    private OperationApi $api;

    /**
     * @param OperationApi $api Operation API service
     */
    public function __construct(OperationApi $api)
    {
        $this->api = $api;
    }

    /**
     * @return void
     */
    public function registerAbilities(): void
    {
        foreach ($this->api->getOperations() as $operation) {
            $this->register($operation);
        }
    }

    /**
     * @param ApiOperation $operation Operation
     *
     * @return void
     */
    private function register(ApiOperation $operation): void
    {
        $properties = [];
        foreach ($operation->getInputs() as $name => $field) {
            $properties[$name] = $field->getSchema(true);
        }
        $input = [
            'type'                 => 'object',
            'properties'           => ($properties === [] ? new stdClass() : $properties),
            'additionalProperties' => false,
        ];
        if ($operation->getRequired() === []) {
            $input['default'] = new stdClass();
        } else {
            $input['required'] = $operation->getRequired();
        }

        $capability = $operation->getCapability();
        AbilityRegistration::register('duplicator/' . $operation->getName(), [
            'label'               => $operation->getLabel(),
            'description'         => $operation->getDescription(),
            'execute_callback'    => static fn($input = null) => $operation->execute((array) $input),
            'permission_callback' => static fn($input = null) => AbilityPermission::check($capability),
            'input_schema'        => $input,
            'output_schema'       => [
                'type'       => 'object',
                'properties' => ($operation->getOutput() === [] ? new stdClass() : $operation->getOutput()),
            ],
            'annotations'         => [
                'readonly'    => $operation->isReadonly(),
                'destructive' => $operation->isDestructive(),
                'idempotent'  => $operation->isIdempotent(),
            ],
        ], $operation->isSensitive());
    }
}
