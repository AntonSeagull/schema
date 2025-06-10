<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;

class FloatType extends BaseType
{
    public string $type = 'float';

    public function __construct()
    {
        // Nothing extra for now
    }

    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return $this->default;
        }
        return (float) $value;
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_float($value) && !is_int($value)) {
            $field = $this->title ?? 'Value';
            throw new \InvalidArgumentException("{$field} must be a float.");
        }
    }

    public function GQLType(): Type | array | null
    {
        return Type::float();
    }

    public function GQLTypeInput(): ?Type
    {
        return Type::float();
    }

    public function GQLFilterTypeInput(): ?Type
    {
        return  CachedInputObjectType::create([
            'name' => 'NumberInputFilterInput',
            'fields' => [
                'gte' => [
                    'type' => Type::float(),
                ],
                'eq' => [
                    'type' => Type::float(),
                ],
                'lte' => [
                    'type' => Type::float(),
                ],

            ],
        ]);
    }
}
