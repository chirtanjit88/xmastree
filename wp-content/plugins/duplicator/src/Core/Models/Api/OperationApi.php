<?php

/**
 * Operations and inputs addons add to the API interfaces
 *
 * @package   Duplicator
 * @copyright (c) 2026, Snap Creek LLC
 * @license   https://www.gnu.org/licenses/gpl-3.0.html GPLv3
 */

declare(strict_types=1);

namespace Duplicator\Core\Models\Api;

use Exception;

/**
 * Collects the operations addons declare and the inputs they add to existing operations, so an addon extends the API
 * interfaces without referencing the code that exposes them.
 */
final class OperationApi
{
    /** @var string Filter through which addons declare their ApiOperation instances */
    const OPERATIONS_FILTER = 'duplicator_api_operations';

    /** @var string Filter through which addons add ApiField inputs to an operation, by operation name */
    const INPUTS_FILTER = 'duplicator_api_inputs';

    /**
     * The declared operations; a repeated name or a value that is not an ApiOperation is a programming error
     *
     * @return array<string,ApiOperation> Operations by name
     */
    public function getOperations(): array
    {
        $result = [];
        /**
         * Declare operations for the API interfaces.
         *
         * @param ApiOperation[] $operations Operations declared by earlier callbacks
         */
        foreach ((array) apply_filters(self::OPERATIONS_FILTER, []) as $operation) {
            if (!$operation instanceof ApiOperation || isset($result[$operation->getName()])) {
                throw new Exception('Invalid or repeated API operation');
            }
            $result[$operation->getName()] = $operation;
        }
        return $result;
    }

    /**
     * The inputs addons add to an operation; one already defined by the operation is ignored
     *
     * @param string                 $operation Operation name, such as backup-queue
     * @param array<string,ApiField> $own       Inputs the operation defines, by name
     *
     * @return array<string,ApiField> Added inputs by name
     */
    public static function getAddedInputs(string $operation, array $own = []): array
    {
        $result = [];
        /**
         * Add inputs to an operation.
         *
         * @param ApiField[] $inputs    Inputs added by earlier callbacks
         * @param string     $operation Operation name
         */
        foreach ((array) apply_filters(self::INPUTS_FILTER, [], $operation) as $field) {
            if (!$field instanceof ApiField) {
                throw new Exception('Invalid API input of ' . $operation);
            }
            if (!isset($own[$field->getName()])) {
                $result[$field->getName()] = $field;
            }
        }
        return $result;
    }
}
