<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;

class ArrayOfType extends BaseType
{
    public string $type = 'array';


    public function __construct(BaseType $itemType)
    {



        $this->itemType = $itemType;
    }

    public function normalize(mixed $value, $addDefaultValues = false): mixed
    {


        if ($addDefaultValues && !$value && $this->defaultIsSet) {
            return $this->default;
        }

        if (!$value) {
            return [];
        }


        $value = array_filter($value, fn($v) => $v !== null);
        if (count($value) === 0) {
            return [];
        }


        return array_filter(array_map(fn($v) => $this->itemType->normalize($v), $value), fn($v) => $v !== null);
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);

        if ($value === null) {
            return;
        }

        if (!is_array($value)) {
            $field = $this->title ?? 'Value';
            throw new \InvalidArgumentException("{$field} must be an array.");
        }

        foreach ($value as $k => $item) {
            try {
                $this->itemType->validate($item);
            } catch (\InvalidArgumentException $e) {
                $field = $this->title ?? "Element {$k}";
                throw new \InvalidArgumentException("{$field}[{$k}]: " . $e->getMessage());
            }
        }
    }

    public function GQLType(): Type | array | null
    {



        $this->itemType->key = $this->key;

        $inner = $this->itemType->GQLType();
        return $inner ? Type::listOf($inner) : null;
    }


    public function fullCleanDefault(): static
    {
        $this->defaultIsSet = false;
        $this->default = null;
        $this->itemType->fullCleanDefault();

        return $this;
    }


    public function fullEditable(bool $editable = true): static
    {

        $this->editable = $editable;

        $this->itemType->fullEditable($editable);

        return $this;
    }


    public function GQLTypeInput(): ?Type
    {


        $inner = $this->itemType->keyIfNot($this->key)->GQLTypeInput();

        return $inner ? Type::listOf($inner) : null;
    }

    public function filterType(): ?BaseType
    {

        $this->itemType->key = $this->key;

        $itemTypeFilter = $this->itemType->filterType();
        if (!$itemTypeFilter) {
            return null;
        }
        $itemTypeFilter->editable();

        return $itemTypeFilter;
    }

    public function tsType(): TSType
    {



        $TSType = new TSType($this->itemType->tsType()->getTsTypeName() . 'Array',  $this->itemType->tsType()->getTsTypeName() . '[]');



        return $TSType;
    }

    public function tsInputType(): TSType
    {
        $TSType = new TSType($this->itemType->tsType()->getTsTypeName() . 'Array',  $this->itemType->tsInputType()->getTsTypeName() . '[]');
        return $TSType;
    }
}
