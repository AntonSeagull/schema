<?php

namespace Shm\ShmTypes\Utils;

/**
 * JSON Logic Builder for constructing complex query conditions
 * 
 * This class provides a fluent interface for building JSON Logic expressions
 * that can be used for filtering and querying data. It supports various
 * comparison operations, array operations, and logical operators.
 * 
 * @example
 * ```php
 * $builder = new JsonLogicBuilder();
 * $logic = $builder
 *     ->equals('status', 'active')
 *     ->greaterThan('age', 18)
 *     ->in('role', ['admin', 'user'])
 *     ->and()
 *     ->build();
 * ```
 */
class JsonLogicBuilder
{
    /**
     * Array of JSON Logic expressions
     * @var array
     */
    private array $logic = [];

    /**
     * Array of field names used in the logic expressions
     * @var array
     */
    private array $fields = [];

    /**
     * Add an equality condition
     * 
     * @param string $field Field name to compare
     * @param mixed $value Value to compare against
     * @return static
     */
    public function equals(string $field, $value): static
    {
        // Handle false values specially - convert to not equals true
        if ($value === false) {
            return $this->notEquals($field, true);
        }

        $this->fields[] = $field;
        $this->logic[] = [
            "===" => [["var" => $field], $value]
        ];
        return $this;
    }

    /**
     * Add a not equals condition
     * 
     * @param string $field Field name to compare
     * @param mixed $value Value to compare against
     * @return static
     */
    public function notEquals(string $field, $value): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "!=" => [["var" => $field], $value]
        ];
        return $this;
    }

    /**
     * Add a greater than condition
     * 
     * @param string $field Field name to compare
     * @param mixed $value Value to compare against
     * @return static
     */
    public function greaterThan(string $field, $value): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            ">" => [["var" => $field], $value]
        ];
        return $this;
    }

    /**
     * Add a less than condition
     * 
     * @param string $field Field name to compare
     * @param mixed $value Value to compare against
     * @return static
     */
    public function lessThan(string $field, $value): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "<" => [["var" => $field], $value]
        ];
        return $this;
    }

    /**
     * Add a contains condition (value is contained in field)
     * 
     * @param string $field Field name to check
     * @param string $value Value that should be contained
     * @return static
     */
    public function contains(string $field, string $value): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "in" => [["var" => $field], $value]
        ];
        return $this;
    }

    /**
     * Add a missing field condition
     * 
     * @param string $field Field name that should be missing
     * @return static
     */
    public function missing(string $field): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "missing" => [$field]
        ];
        return $this;
    }

    /**
     * Add a condition that checks if some elements in an array field match the condition
     * 
     * @param string $field Array field name
     * @param array $condition Condition to apply to each array element
     * @return static
     */
    public function some(string $field, array $condition): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "some" => [["var" => $field], $condition]
        ];
        return $this;
    }

    /**
     * Add a condition that checks if field value is in the given array of values
     * Supports both single values and arrays
     * 
     * @param string $field Field name to check
     * @param array $values Array of values to check against
     * @return static
     */
    public function in(string $field, array $values): static
    {
        $this->fields[] = $field;

        // Condition for single value fields
        $singleValueCondition = [
            "or" => array_map(function ($value) use ($field) {
                return [
                    "===" => [["var" => $field], $value]
                ];
            }, $values)
        ];

        // Condition for array fields
        $arrayCondition = [
            "some" => [
                ["var" => $field],
                [
                    "or" => array_map(function ($value) {
                        return [
                            "===" => [["var" => ""], $value]
                        ];
                    }, $values)
                ]
            ]
        ];

        // Combine both conditions with OR
        $this->logic[] = [
            "or" => [$singleValueCondition, $arrayCondition]
        ];

        return $this;
    }

    /**
     * Add a condition that checks if all elements in an array field match the condition
     * 
     * @param string $field Array field name
     * @param array $condition Condition to apply to each array element
     * @return static
     */
    public function all(string $field, array $condition): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "all" => [["var" => $field], $condition]
        ];
        return $this;
    }

    /**
     * Add a condition that checks if field is null or missing
     * 
     * @param string $field Field name to check
     * @return static
     */
    public function isNull(string $field): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "or" => [
                ["missing" => [$field]],
                ["===" => [["var" => $field], null]]
            ]
        ];
        return $this;
    }

    /**
     * Combine all current conditions with AND operator
     * 
     * @return static
     */
    public function and(): static
    {
        $this->logic = [
            "and" => $this->logic
        ];
        return $this;
    }

    /**
     * Combine all current conditions with OR operator
     * 
     * @return static
     */
    public function or(): static
    {
        $this->logic = [
            "or" => $this->logic
        ];
        return $this;
    }

    /**
     * Build and return the JSON Logic expression
     * 
     * @return array The constructed JSON Logic expression
     */
    public function build(): array
    {
        return $this->logic;
    }

    /**
     * Get all field names used in the logic expressions
     * 
     * @return array Array of unique field names
     */
    public function getFields(): array
    {
        return array_unique($this->fields);
    }

    /**
     * Reset the builder to start fresh
     * 
     * @return static
     */
    public function reset(): static
    {
        $this->logic = [];
        $this->fields = [];
        return $this;
    }
}
