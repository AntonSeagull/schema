<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;

class ArrayOfType extends BaseType
{
    public string $type = 'array';


    public function __construct(BaseType $itemType)
    {


        $this->itemType = $itemType;
    }

    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return $this->default;
        }

        if (!is_array($value)) {
            return [];
        }

        return array_map(fn($v) => $this->itemType->normalize($v), $value);
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


    public function fullEditable(): static
    {

        $this->editable = true;

        $this->itemType->fullEditable();

        return $this;
    }


    public function GQLTypeInput(): ?Type
    {


        $inner = $this->itemType->keyIfNot($this->key)->GQLTypeInput();

        return $inner ? Type::listOf($inner) : null;
    }

    public function GQLFilterTypeInput(): ?Type
    {

        $this->itemType->key = $this->key;

        return $this->itemType->GQLFilterTypeInput();
    }

    public function tsGQLFullRequest(): string
    {
        return  $this->itemType->tsGQLFullRequest();
    }
}