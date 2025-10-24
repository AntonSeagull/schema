<?php

namespace Shm\ShmBlueprints\Validators;

use Shm\ShmBlueprints\Exceptions\MutationException;

/**
 * Validator for mutation operations
 */
class MutationValidator
{
    /**
     * Validate pipeline structure
     * 
     * @param mixed $pipeline Pipeline to validate
     * @throws MutationException If pipeline is invalid
     */
    public static function validatePipeline(mixed $pipeline): void
    {
        if (!is_array($pipeline)) {
            throw MutationException::validation('Pipeline must be an array');
        }

        foreach ($pipeline as $stage) {
            if (!is_array($stage)) {
                throw MutationException::validation('Pipeline stage must be an array');
            }

            if (empty($stage)) {
                throw MutationException::validation('Pipeline stage cannot be empty');
            }
        }
    }

    /**
     * Validate mutation arguments
     * 
     * @param array $args Arguments to validate
     * @param bool $oneRow Whether this is a one-row operation
     * @throws MutationException If arguments are invalid
     */
    public static function validateArgs(array $args, bool $oneRow): void
    {
        if (!$oneRow && !isset($args['_id'])) {
            throw MutationException::validation('Record ID required for multi-row operations');
        }

        if (isset($args['fields']) && !is_array($args['fields'])) {
            throw MutationException::validation('Fields must be an array');
        }
    }

    /**
     * Validate record access
     * 
     * @param mixed $record Record to validate
     * @param string $operation Operation being performed
     * @throws MutationException If access is denied
     */
    public static function validateAccess(mixed $record, string $operation): void
    {
        if (!$record) {
            throw MutationException::access("No permission to {$operation} this record");
        }
    }

    /**
     * Validate array operations
     * 
     * @param array $operation Array operation data
     * @param string $operationType Type of operation (addToSet, pull, etc.)
     * @throws MutationException If operation is invalid
     */
    public static function validateArrayOperation(array $operation, string $operationType): void
    {
        if (empty($operation)) {
            throw MutationException::validation("{$operationType} operation cannot be empty");
        }

        foreach ($operation as $key => $value) {
            if (!is_array($value)) {
                throw MutationException::validation("{$operationType} value for field '{$key}' must be an array");
            }
        }
    }
}
