<?php

namespace Shm\ShmTypes;

use GraphQL\Type\Definition\Type;
use Shm\Shm;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;
use Traversable;

class ArrayOfType extends BaseType
{
    public string $type = 'array';


    public function __construct(BaseType $itemType)
    {


        if ($itemType instanceof StructureType) {

            $itemType->items['uuid'] = Shm::uuid();
        }

        $this->itemType = $itemType;
    }

    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if (!(is_array($value) || $value instanceof Traversable)) {
            return null;
        }


        if ($addDefaultValues && !$value && $this->defaultIsSet) {
            return $this->default;
        }

        if (!$value) {
            return [];
        }

        $newValue = [];


        foreach ($value as $valueItem) {
            if ($valueItem === null) {
                continue;
            }

            $newValue[] =  $this->itemType->normalize($valueItem, $addDefaultValues, $processId);
        }


        return $newValue;
    }



    public function removeOtherItems(mixed $value): mixed
    {
        if (!(is_array($value) || $value instanceof Traversable)) {
            return null;
        }

        $newValue = [];
        foreach ($value as $valueItem) {
            if ($valueItem === null) {
                continue;
            }

            $newValue[] =  $this->itemType->removeOtherItems($valueItem);
        }

        return $newValue;
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


    public function editable(bool $isEditable = true): static
    {
        $this->editable = $isEditable;
        $this->itemType->editable($isEditable);
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

        if ($this->filterType) {
            return $this->filterType;
        }

        $this->itemType->key = $this->key;

        $itemTypeFilter = $this->itemType->filterType();
        if (!$itemTypeFilter) {
            return null;
        }
        $itemTypeFilter->editable();

        $this->filterType = $itemTypeFilter;
        return  $this->filterType;
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


    public function columns(array | null $path = null): array
    {


        $this->columns = $this->itemType->columns($path ? [...$path, $this->key] : [$this->key]);

        return parent::columns($path);
    }
}
