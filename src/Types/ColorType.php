<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;

class ColorType extends BaseType
{
    public string $type = 'color';

    public function __construct()
    {
        // Nothing extra for now
    }

    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return $this->default;
        }

        return (string) $value;
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);

        if ($value === null) {
            return;
        }

        if (!is_string($value)) {
            $field = $this->title ?? 'Value';
            throw new \InvalidArgumentException("{$field} must be a string.");
        }
    }

    public function GQLType(): Type | array | null
    {
        return Type::string();
    }

    public function GQLFilterTypeInput(): ?Type
    {
        return  Type::string();
    }



    public function GQLTypeInput(): ?Type
    {
        return Type::string();
    }
}