<?php

namespace Shm\ShmTypes;


use Shm\ShmRPC\ShmRPCCodeGen\TSType;

/**
 * Self reference type for schema definitions
 * 
 * This class represents a self-referencing type that can reference itself
 * with lazy resolution to avoid circular reference issues.
 */
class SelfRefType extends BaseType
{
    public string $type = 'selfRef';
    public mixed $callableType = null;
    private ?BaseType $resolved = null;
    private bool $resolving = false;

    /**
     * Constructor
     * 
     * @param callable $type Callable that returns the type definition
     */
    public function __construct(callable $type)
    {
        $this->callableType = $type;
    }

    /**
     * Normalize self reference value
     * 
     * @param mixed $value Value to normalize
     * @param bool $addDefaultValues Whether to add default values
     * @param string|null $processId Process ID for tracking
     * @return mixed Normalized value
     */
    public function normalize(mixed $value, $addDefaultValues = false, string|null $processId = null): mixed
    {
        return $this->resolveType()->normalize($value, $addDefaultValues, $processId);
    }

    /**
     * Validate self reference value
     * 
     * @param mixed $value Value to validate
     * @throws \Exception If validation fails
     */
    public function validate(mixed $value): void
    {
        parent::validate($value);
        $this->resolveType()->validate($value);
    }

    /**
     * Resolve the type reference
     * 
     * @return BaseType Resolved type
     * @throws \RuntimeException If circular reference is detected
     */
    public function resolveType(): BaseType
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        if ($this->resolving) {
            throw new \RuntimeException("Circular reference detected in SelfRefType.");
        }

        $this->resolving = true;
        $this->resolved = ($this->callableType)(); // Lazy resolution
        $this->resolving = false;

        return $this->resolved;
    }

    /**
     * Get TypeScript type
     * 
     * @return TSType TypeScript type representation
     */
    public function tsType(): TSType
    {
        return $this->resolveType()->tsType();
    }
}
