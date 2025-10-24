<?php

namespace Shm\ShmTypes;

use Shm\ShmRPC\ShmRPCCodeGen\TSType;

/**
 * Static type for schema definitions
 * 
 * This class represents a static type that always returns the same value
 * regardless of input.
 */
class StaticType extends BaseType
{
    public string $type = 'static';
    private mixed $staticValue;

    /**
     * Constructor
     * 
     * @param mixed $staticValue Static value to return
     * @throws \Exception If static value is null or empty
     */
    public function __construct(mixed $staticValue)
    {
        if (!$staticValue) {
            throw new \Exception('Static value cannot be null or empty');
        }

        $this->staticValue = $staticValue;
    }

    /**
     * Get static value for TypeScript
     * 
     * @return mixed Static value formatted for TypeScript
     */
    public function getStaticValueTS(): mixed
    {
        if (is_string($this->staticValue)) {
            return json_encode($this->staticValue);
        }

        return $this->staticValue;
    }

    /**
     * Normalize value (always returns static value)
     * 
     * @param mixed $value Value to normalize (ignored)
     * @param bool $addDefaultValues Whether to add default values (ignored)
     * @param string|null $processId Process ID for tracking (ignored)
     * @return mixed Static value
     */
    public function normalize(mixed $value, $addDefaultValues = false, string|null $processId = null): mixed
    {
        return $this->staticValue;
    }

    /**
     * Validate value (always passes for static type)
     * 
     * @param mixed $value Value to validate (ignored)
     */
    public function validate(mixed $value): void
    {
        parent::validate($value);
        // Static type always returns the same value, so validation always passes
    }

    /**
     * Get TypeScript type
     * 
     * @return TSType TypeScript type representation
     */
    public function tsType(): TSType
    {
        $TSType = new TSType("string");
        // $TSType->value = $this->getStaticValueTS();
        return $TSType;
    }
}
