<?php

namespace Shm\ShmTypes;


use Shm\ShmRPC\ShmRPCCodeGen\TSType;

/**
 * String type for schema definitions
 * 
 * This class represents a string type with various processing options
 * including trimming and case conversion.
 */
class StringType extends BaseType
{
    public string $type = 'string';
    public bool $trim = false;
    public bool $uppercase = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Nothing extra for now
    }

    /**
     * Enable trimming of string values
     * 
     * @param bool $trim Whether to trim strings
     * @return static
     */
    public function trim(bool $trim = true): static
    {
        $this->trim = $trim;
        return $this;
    }

    /**
     * Enable uppercase conversion of string values
     * 
     * @param bool $uppercase Whether to convert to uppercase
     * @return static
     */
    public function uppercase(bool $uppercase = true): static
    {
        $this->uppercase = $uppercase;
        return $this;
    }

    /**
     * Process string value according to configured options
     * 
     * @param mixed $value Value to process
     * @return mixed Processed value
     */
    private function processValue(mixed $value): mixed
    {
        if (!$value || !is_string($value)) {
            return $value;
        }

        if ($this->trim) {
            $value = trim($value);
        }
        if ($this->uppercase) {
            $value = mb_strtoupper($value);
        }
        return $value;
    }





    /**
     * Normalize string value
     * 
     * @param mixed $value Value to normalize
     * @param bool $addDefaultValues Whether to add default values
     * @param string|null $processId Process ID for tracking
     * @return mixed Normalized value
     */
    public function normalize(mixed $value, $addDefaultValues = false, string|null $processId = null): mixed
    {
        if ($addDefaultValues && $value === null && $this->defaultIsSet) {
            return $this->processValue($this->default);
        }
        return $this->processValue((string) $value);
    }

    /**
     * Validate string value
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
    }


    // $columnsWidth is inherited from BaseType


    public function tsType(): TSType
    {
        $TSType = new TSType("string");


        return $TSType;
    }

    public function getSearchPaths(): array
    {


        return [
            [
                'path' => $this->path,
            ]
        ];
    }

    public function exportRow(mixed $value): string | array | null
    {
        if ($value) {
            return (string)$value;
        } else {
            return "";
        }
    }

    public function fallbackDisplayValues($values): array | string | null
    {
        return $values;
    }
}
