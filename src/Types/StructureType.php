<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;

use Shm\CachedType\CachedEnumType;
use Shm\CachedType\CachedInputObjectType;

use Shm\GQLUtils\AutoPostfix;
use Shm\CachedType\CachedObjectType;
use Shm\GQLUtils\Inflect;
use Shm\GQLUtils\Utils;

class StructureType extends BaseType
{
    public string $type = 'structure';


    public $collection = null;

    private $pipeline = [];


    public function pipeline(array $pipeline): self
    {
        $this->pipeline = $pipeline;
        return $this;
    }

    public function getPipeline(): array
    {
        return $this->pipeline;
    }

    public function collection(string $collection): self
    {
        $this->collection = $collection;
        return $this;
    }

    /**
     * @param array<string, BaseType> $items
     */
    public function __construct(array $items)
    {

        foreach ($items as $key => $field) {


            if (!$field instanceof BaseType) {
                throw new \InvalidArgumentException("Field '{$key}' must be an instance of BaseType.");
            }


            $field->key = $key;
        }


        $this->items = $items;
    }



    public function normalize(mixed $value, $addDefaultValues = false): mixed
    {
        if ($value === null) {
            return $this->default;
        }
        if (!is_array($value) && !is_object($value) || empty($value)) {
            return [
                '_' => 'structure'
            ];
        }

        if ($addDefaultValues) {
            foreach ($this->items as $name => $type) {
                $value[$name] = $type->normalize($value[$name] ?? null);
            }
        } else {

            foreach ($value as $key => $val) {
                if (isset($this->items[$key])) {
                    $value[$key] = $this->items[$key]->normalize($val);
                } else {
                    // If the key is not defined in items, we can either ignore it or throw an error
                    // For now, we will just ignore it
                    //   unset($value[$key]);
                }
            }
        }


        return $value;
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_array($value)) {
            $field = $this->title ?? 'Value';
            throw new \InvalidArgumentException("{$field} must be an object/structure (associative array).");
        }
        foreach ($this->items as $name => $type) {
            try {
                $type->validate($value[$name] ?? null);
            } catch (\InvalidArgumentException $e) {
                $field = $this->title ?? $name;
                throw new \InvalidArgumentException("{$field}.{$name}: " . $e->getMessage());
            }
        }
    }

    private function GQLTypeName()
    {
        if (!$this->key) {
            throw new \InvalidArgumentException("Key is not set for StructureType." . print_r($this->items, true));
        }

        if ($this->collection) {
            return Inflect::singularize(Utils::onlyLetters($this->collection));
        }
        return Inflect::singularize(Utils::onlyLetters($this->key)) .  AutoPostfix::get(array_keys($this->items));
    }

    public function GQLType(): Type | array | null
    {

        if (!$this->key) {
            throw new \InvalidArgumentException("Key is not set for StructureType." . print_r($this->items, true));
        }


        $fields = [];
        foreach ($this->items as $name => $type) {
            $fields[$name] = $type->GQLType();
        }
        $_this = $this;



        return CachedObjectType::create([
            'name' => $this->GQLTypeName() . 'Type',
            'fields' => function () use ($fields) {
                return $fields;
            },


        ]);
    }

    public function GQLTypeInput(): ?Type
    {


        if (!$this->key) {
            throw new \InvalidArgumentException("Key is not set for StructureType." . print_r($this->items, true));
        }

        $fields = [];
        foreach ($this->items as $name => $type) {

            if ($type->editable)
                $fields[$name] = $type->GQLTypeInput();
        }




        return CachedInputObjectType::create([
            'name' => $this->GQLTypeName() . 'Input',
            'fields' => function () use ($fields) {
                return $fields;
            },

        ]);
    }


    public function GQLFieldsEnum(): ?Type
    {

        if (!$this->key) {
            throw new \InvalidArgumentException("Key is not set for StructureType." . print_r($this->items, true));
        }


        $values = [];
        foreach ($this->items as $key => $value) {

            if ($value instanceof StructureType) {

                foreach ($value->items as $key2 => $value2) {

                    $values[$key . ucfirst($key2)] = ['value' => $key . '.' . $key2, 'description' => $value->title ?? $key2];
                }
            } else {
                $values[$key] = ['value' => $key, 'description' => $value->title ?? $key];
            }
        }

        if (count($values) == 0) {
            return null;
        }

        return CachedEnumType::create([
            'name' => $this->GQLTypeName() . 'FieldsEnum',
            'description' => "Поля " . $this->title,
            'values' => $values,
        ]);
    }


    public function GQLSortTypeInput(): ?Type
    {

        if (!$this->key) {
            throw new \InvalidArgumentException("Key is not set for StructureType." . print_r($this->items, true));
        }



        $fieldsEnum = $this->GQLFieldsEnum();




        $itemName = 'SortDirectionEnum';


        $fieldDirection = CachedEnumType::create([
            'name' => $itemName,
            'description' => "Направление сортировки",
            'values' => [
                "ASC" => ["value" => "ASC", "description" => "По возрастанию (ascending)"],
                "DESC" => ["value" => "DESC", "description" => "По убыванию (descending)"],
            ],
        ]);

        $itemName = $this->GQLTypeName() . 'SortInput';



        return CachedInputObjectType::create([
            'name' => $itemName,
            'fields' => [
                'field' => [
                    "type" => Type::nonNull($fieldsEnum),
                ],
                'direction' => [
                    "type" => Type::nonNull($fieldDirection),
                ],
            ],

        ]);
    }

    public function fullEditable(): static
    {

        $this->editable = true;

        foreach ($this->items as $key => $field) {

            $field->fullEditable();
        }

        return $this;
    }



    public function GQLFilterTypeInput(): ?Type
    {

        if (!$this->key) {
            throw new \InvalidArgumentException("Key is not set for StructureType." . print_r($this->items, true));
        }


        $itemName = $this->GQLTypeName() . 'FilterInput';


        $fields = [];

        foreach ($this->items as $key => $field) {


            $input = $field->GQLFilterTypeInput();

            if ($input) {
                $fields[$key] = $input;
            }
        }

        if (count($fields) == 0) {
            return null;
        }

        return CachedInputObjectType::create([
            'name' => $itemName,
            'fields' => function () use ($fields) {
                return $fields;
            },
        ]);
    }



    public function tsTypeName(): string
    {
        return  $this->GQLTypeName() . 'Type';
    }


    public function tsGQLFullRequest(): string
    {
        $result = [];

        $result[] = '{';

        foreach ($this->items as $key => $value) {

            $result[] = $key . $value->tsGQLFullRequest();
        }

        $result[] = '}';

        return implode("\n", $result);
    }


    public function tsComplexType(): string
    {


        $tsType = 'export interface ' . $this->tsTypeName() . ' {\n';

        foreach ($this->items as $key => $value) {

            if ($value->isComplexTsType()) {
                $result = $value->tsTypeName();
            } else {
                $result = $value->tsType();
            }

            $nullable = $value->nullable ? '?' : '';

            $tsType .= '  ' . $key . $nullable . ': ' . $result . ";\n";
        }

        $tsType .= "}\n";



        return $tsType;
    }
}