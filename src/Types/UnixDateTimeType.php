<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;

class UnixDateTimeType extends BaseType
{
    public string $type = 'unixdatetime';

    public function __construct()
    {
        // Nothing extra for now
    }

    /**
     * Normalize the value to Unix timestamp (int).
     */
    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return $this->default;
        }
        return (int) $value;
    }

    /**
     * Validate that the value is a valid Unix timestamp (int).
     */
    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_int($value)) {
            $field = $this->title ?? 'Value';
            throw new \InvalidArgumentException("{$field} must be a Unix timestamp (integer).");
        }
    }

    /**
     * Return GraphQL scalar type for output.
     */
    public function GQLType(): Type | array | null
    {
        return Type::int();
    }

    /**
     * Return GraphQL scalar type for input.
     */
    public function GQLTypeInput(): ?Type
    {
        return Type::int();
    }

    public function GQLFilterTypeInput(): ?Type
    {
        return  CachedInputObjectType::create([
            'name' => 'IntInputFilterInput',
            'fields' => [
                'gte' => [
                    'type' => Type::int(),
                ],
                'eq' => [
                    'type' => Type::int(),
                ],
                'lte' => [
                    'type' => Type::int(),
                ],

            ],
        ]);
    }

    public $tsType = 'number'; // TypeScript type for this field
}