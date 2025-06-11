<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;

use Shm\CachedType\CachedInputObjectType;

use Shm\GQLUtils\AutoPostfix;
use Shm\CachedType\CachedObjectType;

use Shm\GQLUtils\Utils;
use Shm\Shm;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;
use Shm\ShmUtils\Inflect;

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

        $_items = [];
        foreach ($items as $key => $field) {

            if (!$field) {
                continue;
            }

            if (!$field instanceof BaseType) {
                throw new \InvalidArgumentException("Field '{$key}' must be an instance of BaseType.");
            }



            $field->key = $key;

            $_items[$key] = $field;
        }


        $this->items =  $_items;
    }



    public function normalize(mixed $value, $addDefaultValues = false): mixed
    {





        if ($addDefaultValues) {

            /**
             * If the value is null and default values are set, return the default value.
             */
            if (!is_array($value) && !is_object($value)) {
                return null;
            }


            foreach ($this->items as $name => $type) {

                $value[$name] =  $type->normalize($value[$name] ?? null, $addDefaultValues);
            }
        } else {


            foreach ($value as $key => $val) {
                if (isset($this->items[$key])) {
                    $value[$key] = $this->items[$key]->normalize($val);
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


    public function GQLBaseTypeName()
    {
        if (!$this->key) {
            throw new \InvalidArgumentException("Key is not set for StructureType." . print_r($this, true));
        }

        $typeName = '';

        if ($this->collection) {
            $typeName = Inflect::singularize(Utils::onlyLetters($this->collection));
        } else {
            $typeName = Inflect::singularize(Utils::onlyLetters($this->key)) .  AutoPostfix::get(array_keys($this->items));
        }

        return $typeName;
    }



    public function GQLTypeName()
    {
        return $this->GQLBaseTypeName() . 'Type';
    }

    public function GQLInputTypeName()
    {
        return $this->GQLBaseTypeName() . 'Input';
    }

    public function GQLFilterTypeName()
    {
        return $this->GQLBaseTypeName() . 'FilterInput';
    }

    public function findItemByKey(string $key): ?BaseType
    {

        if (isset($this->items[$key])) {
            return $this->items[$key];
        }

        return null;
    }

    public function findItemByType(string $type): ?BaseType
    {

        foreach ($this->items as $item) {
            if ($item->type === $type) {
                return $item;
            }
        }

        return null;
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
            'name' => $this->GQLTypeName(),
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
            'name' => $this->GQLInputTypeName(),
            'fields' => function () use ($fields) {
                return $fields;
            },

        ]);
    }



    public function fullCleanDefault(): static
    {
        $this->defaultIsSet = false;
        $this->default = null;

        foreach ($this->items as $key => $field) {

            $field->fullCleanDefault();
        }

        return $this;
    }

    public function fullEditable(bool $editable = true): static
    {

        $this->editable = $editable;

        foreach ($this->items as $key => $field) {

            $field->fullEditable($editable);
        }

        return $this;
    }

    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {



        if (!is_array($filter) && !is_object($filter)) {
            return null;
        }


        if ($absolutePath !== null) {
            $absolutePath = [...$absolutePath, $this->key];
        } else {
            $absolutePath = [];
        }
        $pipeline = [];

        foreach ($filter as $key => $value) {

            if ($value === null) {
                continue;
            }
            if (!isset($this->items[$key])) {
                continue;
            }




            $type = $this->items[$key];


            $pipelineItem = $type->filterToPipeline($value,  $absolutePath);

            $pipeline = [...$pipeline, ...($pipelineItem ?? [])];
        }


        return  $pipeline;
    }


    public function filterType(): ?BaseType
    {

        if (!$this->key) {
            throw new \InvalidArgumentException("Key is not set for StructureType." . print_r($this->items, true));
        }


        $fields = [];

        foreach ($this->items as $key => $field) {

            $input = $field->filterType();
            if ($input) {
                $fields[$key] = $input;
            }
        }
        if (count($fields) == 0) {
            return null;
        }
        return  Shm::structure($fields);
    }





    public function tsType(): TSType
    {
        $TSType = new TSType();



        $value = [];

        foreach ($this->items as $key => $item) {
            $separate = $item->nullable ? '?: ' : ': ';
            $value[] = $key .  $separate . $item->tsType()->getTsTypeName();
        }

        $TSType = new TSType($this->GQLTypeName(), '{\n' . implode(',\n', $value) . '\n}');




        return $TSType;
    }


    public function tsInputType(): TSType
    {
        $TSType = new TSType();



        $value = [];

        foreach ($this->items as $key => $item) {
            if (!$item->editable) {
                continue;
            }

            $separate = $item->nullable ? '?: ' : ': ';

            $value[] = $key .  $separate . $item->tsInputType()->getTsTypeName();
        }

        $TSType = new TSType($this->GQLInputTypeName(), '{\n' . implode(',\n', $value) . '\n}');




        return $TSType;
    }
}