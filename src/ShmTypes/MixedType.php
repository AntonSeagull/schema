<?php

namespace Shm\ShmTypes;


use Shm\ShmRPC\ShmRPCCodeGen\TSType;

/**
 * Mixed type for schema definitions
 * 
 * This class represents a mixed type that can accept any value type
 * without validation or normalization.
 */
class MixedType extends BaseType
{
    public string $type = 'mixed';

    /**
     * Constructor
     */
    public function __construct() {}

    /**
     * Normalize mixed value
     * 
     * @param mixed $value Value to normalize
     * @param bool $addDefaultValues Whether to add default values
     * @param string|null $processId Process ID for tracking
     * @return mixed Normalized value (unchanged)
     */
    public function normalize(mixed $value, $addDefaultValues = false, string|null $processId = null): mixed
    {
        if ($addDefaultValues && $value === null && $this->defaultIsSet) {
            return $this->default;
        }
        return $value;
    }

    /**
     * Validate mixed value (always passes)
     * 
     * @param mixed $value Value to validate
     */
    public function validate(mixed $value): void
    {
        parent::validate($value);
        // Mixed type accepts any value, so no additional validation needed
    }

    /**
     * Get TypeScript type
     * 
     * @return TSType TypeScript type representation
     */
    public function tsType(): TSType
    {
        return new TSType("any");
    }
}
