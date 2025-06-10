<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use Shm\Shm;

class BoolType extends BaseType
{
    public string $type = 'bool';

    public function __construct()
    {
        // Nothing extra for now
    }

    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return $this->default;
        }
        return (bool) $value;
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_bool($value)) {
            $field = $this->title ?? 'Value';
            throw new \InvalidArgumentException("{$field} must be a boolean.");
        }
    }

    public function GQLFilterTypeInput(): ?Type
    {
        return  Type::boolean();
    }


    public function GQLType(): Type | array | null
    {
        return Type::boolean();
    }


    public function GQLTypeInput(): ?Type
    {
        return Type::boolean();
    }


    public function filterType(): ?BaseType
    {
        return Shm::enum([
            'true' => 'Да',
            'false' => 'Нет',
        ])->title($this->title)->editable()->inAdmin();
    }


    public $tsType = 'boolean';
}