<?php

namespace Shm\ShmBlueprints;

use InvalidArgumentException;
use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\Response;

/**
 * Improved Blueprint for RPC mutations with database operations
 * 
 * This class provides a fluent interface for creating RPC mutations
 * that interact with MongoDB collections through the schema system.
 * Part of the Lumus framework for building API endpoints.
 * 
 * Key improvements:
 * - Better separation of concerns
 * - Improved error handling
 * - Type safety
 * - Comprehensive documentation
 * - Cleaner architecture
 */
class ShmBlueprintMutation
{
    private StructureType $structure;
    private bool $oneRow = false;
    private bool $delete = true;
    private mixed $pipelineFunction = null;

    private mixed $prepareArgsFunction = null;

    private mixed $beforeResolveFunction = null;

    /**
     * Колбэк, который вызывается после успешной вставки или обновления.
     * Сигнатура: function ($id, array $args, self $mutation): void {}
     */
    private mixed $afterSaveCallback = null;

    public function __construct(StructureType $structure)
    {
        $this->structure = $structure;
    }



    /**
     * Set whether this mutation operates on a single row
     */
    public function oneRow(bool $oneRow): static
    {
        $this->oneRow = $oneRow;
        return $this;
    }

    /**
     * Set whether this mutation supports deletion
     */
    public function delete(bool $delete): static
    {
        $this->delete = $delete;
        return $this;
    }

    /**
     * Set a callback to be executed before the main resolve function
     */
    public function beforeResolve(callable $callback): static
    {
        $this->beforeResolveFunction = $callback;
        return $this;
    }


    /**
     * Устанавливает колбэк, который будет вызван:
     *  - после вставки документа
     *  - после обновления документа
     *
     * В колбэк передаётся:
     *  - string|\MongoDB\BSON\ObjectId $_id — идентификатор документа
     *  - array $args — аргументы мутации (оригинальные)
     *  - self $mutation — текущий экземпляр мутации
     */
    public function afterSave(callable $callback): static
    {
        $this->afterSaveCallback = $callback;
        return $this;
    }

    /**
     * Вызвать afterSave-колбэк, если он задан.
     *
     * @param mixed $_id  Идентификатор документа
     * @param array $args Аргументы мутации (оригинальные/до нормализации)
     */
    private function callAfterSave(mixed $_id, array $args): void
    {
        if ($this->afterSaveCallback !== null && $_id) {
            ($this->afterSaveCallback)($_id, $args, $this);
        }
    }



    /**
     * Set a callback to be executed before the mutation and can return new args
     * 
     * @param callable $callback Callback function that receives arguments and can return new arguments
     * @return static
     * @throws InvalidArgumentException If callback is not a callable
     */
    public function prepareArgs(callable $callback): static
    {
        $this->prepareArgsFunction = $callback;
        return $this;
    }


    /**
     * Execute the prepare args callback if set
     */
    public function callPrepareArgs(array &$args): void
    {
        if ($this->prepareArgsFunction !== null) {
            $_args = ($this->prepareArgsFunction)($args);
            if ($_args) {
                $args = $_args;
            }
        }
    }

    /**
     * Set the pipeline for database operations
     * 
     * @param array|callable|null $pipeline MongoDB aggregation pipeline or function returning pipeline
     * @throws InvalidArgumentException If pipeline is neither array nor callable
     */
    public function pipeline(array|callable|null $pipeline): static
    {
        if ($pipeline === null) {
            $this->pipelineFunction = null;
            return $this;
        }

        if (is_array($pipeline)) {
            // Convert array to function for consistency
            $this->pipelineFunction = fn() => $pipeline;
        } elseif (is_callable($pipeline)) {
            $this->pipelineFunction = $pipeline;
        } else {
            throw new InvalidArgumentException('Pipeline должен быть массивом или функцией');
        }

        return $this;
    }

    /**
     * Execute the before resolve callback if set
     */
    public function callBeforeResolve(mixed $root, array &$args): void
    {
        if ($this->beforeResolveFunction !== null) {
            ($this->beforeResolveFunction)($root, $args);
        }
    }

    /**
     * Get the validated pipeline for database operations
     * 
     * @return array MongoDB aggregation pipeline
     */
    public function getPipeline(): array
    {
        if ($this->pipelineFunction === null) {
            return [];
        }

        $pipeline = ($this->pipelineFunction)();

        if (empty($pipeline)) {
            return [];
        }

        // Validate the pipeline structure
        mDB::validatePipeline($pipeline);

        return $pipeline;
    }

    /**
     * Flatten nested objects/arrays into dot-notation keys
     * 
     * @param mixed $data Data to flatten
     * @param string $parentKey Parent key for nesting
     * @param array $result Reference to result array
     * @return array Flattened data with dot-notation keys
     */
    public function flattenObject(mixed $data, string $parentKey = '', array &$result = []): array
    {
        if (!is_array($data) && !is_object($data)) {
            return $result;
        }

        foreach ($data as $key => $value) {
            $newKey = $parentKey === '' ? $key : "{$parentKey}.{$key}";

            if (is_array($value) || is_object($value)) {
                // Handle MongoDB ObjectId specially
                if ($value instanceof \MongoDB\BSON\ObjectId) {
                    $result[$newKey] = $value;
                    continue;
                }

                // Handle empty arrays/objects
                if (empty((array)$value)) {
                    $result[$newKey] = $value;
                    continue;
                }

                // Check if first element is ObjectId (array of IDs)
                $firstElement = is_array($value) ? ($value[0] ?? null) : null;
                if ($firstElement && $firstElement instanceof \MongoDB\BSON\ObjectId) {
                    $result[$newKey] = $value;
                } else {
                    // Recursively flatten nested structures
                    $this->flattenObject($value, $newKey, $result);
                }
            } else {
                // Primitive values
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Create the RPC mutation definition
     * 
     * @return array RPC mutation configuration with type, args, and resolve function
     */
    public function make(): array
    {
        $args = $this->buildMutationArgs();
        $argsStructure = Shm::structure($args);

        return [
            "type" => $this->structure,
            "args" => $argsStructure,
            'resolve' => $this->createResolveFunction($argsStructure),
        ];
    }

    /**
     * Build the arguments structure for the mutation
     * 
     * @return array Arguments configuration
     */
    private function buildMutationArgs(): array
    {
        $args = [];

        // Add _id parameter for multi-row operations
        if (!$this->oneRow) {
            $args['_id'] = Shm::ID();
        }

        // Add delete parameter if deletion is enabled
        if ($this->delete && !$this->oneRow) {
            $args['delete'] = Shm::boolean();
        }

        // Add fields for editing
        $args['fields'] = $this->structure->editableThis();

        // Add unset parameter for removable fields
        $editableKeys = $this->getEditableKeys();
        if (!empty($editableKeys)) {
            $args['unset'] = Shm::enum($editableKeys);
        }

        // Add array operation fields (addToSet, pull)
        $arrayFields = $this->getArrayOperationFields();
        if (!empty($arrayFields)) {
            $args['addToSet'] = Shm::structure($arrayFields)->editable();
            $args['pull'] = Shm::structure($arrayFields)->editable();
        }

        // Add move parameter for manual sorting
        if ($this->structure->manualSort && !$this->oneRow) {
            $args['move'] = Shm::structure([
                'aboveId' => Shm::ID(),
                'belowId' => Shm::ID()
            ]);
        }

        return $args;
    }

    /**
     * Get editable field keys from structure
     * 
     * @return array List of editable field keys
     */
    private function getEditableKeys(): array
    {
        return array_values(array_filter(
            array_keys($this->structure->items),
            fn($key) => isset($this->structure->items[$key]->editable) && $this->structure->items[$key]->editable
        ));
    }

    /**
     * Get fields that support array operations (IDs type)
     * 
     * @return array Fields configuration for array operations
     */
    private function getArrayOperationFields(): array
    {
        $fields = [];

        foreach ($this->structure->items as $key => $value) {
            $editable = $value->editable ?? false;
            if (!$editable) {
                continue;
            }

            $type = $value->type ?? null;
            if ($type === "IDs") {
                $fields[$key] = Shm::arrayOf(Shm::ID());
            }
        }

        return $fields;
    }

    /**
     * Create the resolve function for the mutation
     * 
     * @param StructureType $argsStructure Structure for argument validation
     * @return callable Resolve function
     */
    private function createResolveFunction(StructureType $argsStructure): callable
    {
        return function (mixed $root, array $args) use ($argsStructure): mixed {
            // Execute before resolve callback
            $this->callBeforeResolve($root, $args);

            // Get and validate pipeline
            $pipeline = $this->getPipeline();

            // Handle one-row operations
            if ($this->oneRow) {
                return $this->handleOneRowOperation($args, $pipeline, $argsStructure);
            }

            // Handle multi-row operations
            return $this->handleMultiRowOperation($args, $pipeline, $argsStructure);
        };
    }

    /**
     * Handle single row operations
     * 
     * @param array $args Arguments
     * @param array $pipeline Database pipeline
     * @param StructureType $argsStructure Argument structure
     * @return mixed Operation result
     */
    private function handleOneRowOperation(array $args, array $pipeline, StructureType $argsStructure): mixed
    {
        // Validate pipeline exists for one-row operations
        if (empty($pipeline)) {
            Response::validation('Ошибка доступа');
        }

        // Find the single row
        $find = $this->structure->aggregate([
            ...$pipeline,
            ['$limit' => 1]
        ])->toArray();

        if (empty($find)) {
            Response::validation('Ошибка доступа');
        }

        $args['_id'] = $find[0]->_id ?? null;
        if (!$args['_id']) {
            Response::validation('Ошибка доступа');
        }

        return $this->executeUpdateOperation($args, $pipeline, $argsStructure);
    }

    /**
     * Handle multi-row operations
     * 
     * @param array $args Arguments
     * @param array $pipeline Database pipeline
     * @param StructureType $argsStructure Argument structure
     * @return mixed Operation result
     */
    private function handleMultiRowOperation(array $args, array $pipeline, StructureType $argsStructure): mixed
    {
        // Check if this is an insert operation
        if (!isset($args['_id'])) {
            return $this->handleInsertOperation($args, $argsStructure);
        }

        return $this->executeUpdateOperation($args, $pipeline, $argsStructure);
    }

    /**
     * Handle insert operations
     * 
     * @param array $args Arguments
     * @param StructureType $argsStructure Argument structure
     * @return mixed Insert result
     */
    private function handleInsertOperation(array $args, StructureType $argsStructure): mixed
    {
        if ($this->oneRow) {
            Shm::error('Ошибка доступа');
        }

        if (!isset($args['fields'])) {
            Response::validation('Fields required for insert operation');
        }

        $insertValue = $argsStructure->items['fields']->normalize($args['fields'], true);
        $insert = $this->structure->insertOne($insertValue);
        $insertedId = $insert->getInsertedId();

        // Колбэк после вставки
        $this->callAfterSave($insertedId, $args);

        return $this->structure->findOne([
            "_id" => $insertedId
        ]);
    }

    /**
     * Execute update operations (update, delete, array operations)
     * 
     * @param array $args Arguments
     * @param array $pipeline Database pipeline
     * @param StructureType $argsStructure Argument structure
     * @return mixed Operation result
     */
    private function executeUpdateOperation(array $args, array $pipeline, StructureType $argsStructure): mixed
    {

        $this->callPrepareArgs($args);


        $originalArgs = $args;
        $args = $argsStructure->normalize($args);

        $_id = $args['_id'] ?? null;
        if (!$_id) {
            Response::validation('Record ID required for update operation');
        }

        // Build full pipeline
        $fullPipeline = [
            ...$pipeline,
            ...$this->structure->getPipeline()
        ];

        // Verify record exists and user has access
        $findItem = $this->structure->aggregate([
            ...$fullPipeline,
            [
                '$match' => [
                    "_id" => mDB::id($_id),
                ],
            ],
            ['$limit' => 1]
        ])->toArray()[0] ?? null;

        if (!$findItem) {
            Response::validation('Ошибка доступа, у вас нет доступа для редактирования этой записи');
        }

        // Handle delete operation
        if (!$this->oneRow && ($args['delete'] ?? false)) {
            $this->structure->deleteOne(["_id" => mDB::id($_id)]);
            return null;
        }

        // Handle update operations
        $this->performUpdateOperations($args, $originalArgs, $_id);

        // Колбэк после обновления
        $this->callAfterSave($_id, $originalArgs);

        // Return updated record
        return $this->structure->findOne(["_id" => mDB::id($_id)]);
    }

    /**
     * Perform various update operations
     * 
     * @param array $args Normalized arguments
     * @param array $originalArgs Original arguments
     * @param mixed $_id Record ID
     */
    private function performUpdateOperations(array $args, array $originalArgs, mixed $_id): void
    {
        // Handle field updates
        if (isset($originalArgs['fields'])) {
            $setValue = $this->structure->normalize($originalArgs['fields']);
            $this->structure->updateOne(
                ['_id' => mDB::id($_id)],
                ['$set' => $setValue]
            );
        }

        // Handle addToSet operations
        if (isset($args['addToSet'])) {
            $this->performAddToSetOperation($args['addToSet'], $_id);
        }

        // Handle pull operations
        if (isset($args['pull'])) {
            $this->performPullOperation($args['pull'], $_id);
        }

        // Handle unset operations
        if (isset($args['unset'])) {
            $this->performUnsetOperation($args['unset'], $_id);
        }

        // Handle move operations
        if (isset($args['move'])) {
            $this->performMoveOperation($args['move'], $_id);
        }
    }

    /**
     * Perform addToSet operation for arrays
     */
    private function performAddToSetOperation(array $addToSet, mixed $_id): void
    {
        $operation = [];
        foreach ($addToSet as $key => $value) {
            $convertedValue = array_map(fn($val) => mDB::id($val), $value);
            $operation[$key] = ['$each' => $convertedValue];
        }

        if (!empty($operation)) {
            $this->structure->updateOne(
                ['_id' => mDB::id($_id)],
                ['$addToSet' => $operation]
            );
        }
    }

    /**
     * Perform pull operation for arrays
     */
    private function performPullOperation(array $pull, mixed $_id): void
    {
        $operation = [];
        foreach ($pull as $key => $value) {
            $convertedValue = array_map(fn($val) => mDB::id($val), $value);
            $operation[$key] = ['$in' => $convertedValue];
        }

        if (!empty($operation)) {
            $this->structure->updateOne(
                ['_id' => mDB::id($_id)],
                ['$pull' => $operation]
            );
        }
    }

    /**
     * Perform unset operation for fields
     */
    private function performUnsetOperation(array $unset, mixed $_id): void
    {
        // Note: Current implementation doesn't actually unset fields
        // This should be implemented if needed
    }

    /**
     * Perform move operation for sorting
     */
    private function performMoveOperation(array $move, mixed $_id): void
    {
        $currentId = mDB::id($_id);
        $aboveId = $move['aboveId'] ?? null;
        $belowId = $move['belowId'] ?? null;
        $this->structure->moveRow($currentId, $aboveId, $belowId);
    }
}