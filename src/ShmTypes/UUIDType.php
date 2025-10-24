<?php

namespace Shm\ShmTypes;


use Shm\ShmRPC\ShmRPCCodeGen\TSType;

/**
 * UUID type for schema definitions
 * 
 * This class represents a UUID type with automatic generation of UUID v4
 * and validation capabilities.
 */
class UUIDType extends BaseType
{
    public string $type = 'uuid';

    /**
     * Constructor
     */
    public function __construct() {}

    /**
     * Generate UUID v4
     * 
     * @return string Generated UUID
     */
    private function generateUuidV4(): string
    {
        $data = \random_bytes(16);

        // Set version 4 and variant
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // version 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // RFC 4122 variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Normalize UUID value
     * 
     * @param mixed $value Value to normalize
     * @param bool $addDefaultValues Whether to add default values
     * @param string|null $processId Process ID for tracking
     * @return mixed Normalized value
     */
    public function normalize(mixed $value, $addDefaultValues = false, string|null $processId = null): mixed
    {
        if (!$value) {
            return $this->generateUuidV4();
        }
        return (string) $value;
    }

    /**
     * Validate UUID value
     * 
     * @param mixed $value Value to validate
     * @throws \Exception If validation fails
     */
    public function validate(mixed $value): void
    {
        parent::validate($value);

        if ($value === null) {
            return;
        }

        if (!is_string($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be a string.");
        }

        // Basic UUID format validation
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be a valid UUID format.");
        }
    }

    /**
     * Get TypeScript type
     * 
     * @return TSType TypeScript type representation
     */
    public function tsType(): TSType
    {
        return new TSType("string");
    }
}
