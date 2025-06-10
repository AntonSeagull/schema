<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;

class IntType extends BaseType
{
    public string $type = 'int';

    public function __construct()
    {
        // Nothing extra for now
    }


    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return $this->default;
        }
        return (int) $value;
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_int($value)) {
            $field = $this->title ?? 'Value';
            throw new \InvalidArgumentException("{$field} must be an integer.");
        }
    }

    public function GQLType(): Type | array | null
    {
        return Type::int();
    }

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
}
