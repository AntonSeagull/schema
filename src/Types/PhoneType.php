<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;

class PhoneType extends BaseType
{
    public string $type = 'phone';

    public function __construct()
    {
        // Nothing extra for now
    }

    public function normalize(mixed $value, $addDefaultValues = false): mixed
    {

        if ($addDefaultValues &&  $value === null && $this->defaultIsSet) {
            return $this->default;
        }
        if (is_string($value)) {
            $value = preg_replace('/\D/', '', $value);
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

    public function tsType(): TSType
    {
        $TSType = new TSType("Phone", "number");



        return $TSType;
    }
}
