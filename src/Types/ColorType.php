<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use Shm\Shm;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;

class ColorType extends BaseType
{
    public string $type = 'color';

    public function __construct()
    {
        // Nothing extra for now
    }
    public function normalize(mixed $value, $addDefaultValues = false): mixed
    {

        if ($addDefaultValues &&  $value === null && $this->defaultIsSet) {
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


    public function filterType(): ?BaseType
    {

        return  Shm::string()->editable();
    }



    public function GQLTypeInput(): ?Type
    {
        return Type::string();
    }

    public function tsType(): TSType
    {
        $TSType = new TSType('String', 'string');


        return $TSType;
    }
}
