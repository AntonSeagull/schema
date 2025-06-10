<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;

class UnixDateType extends BaseType
{
    public string $type = 'unixdate';

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

        //Set 12:00:00 as default time if only date is provided for unix int date


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