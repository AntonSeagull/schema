<?php

namespace Shm\ShmTypes;

use Sentry\Util\Str;
use Shm\Shm;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmUtils\DeepAccess;
use Traversable;

class ArrayOfType extends BaseType
{
    public string $type = 'array';


    public function __construct(BaseType $itemType)
    {




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



    public function externalData($data)
    {


        if (!($this->itemType instanceof StructureType)) {
            return $data;
        }

        $structureType = $this->itemType;

        $structureType->updateKeys();
        $structureType->updatePath();
        $paths =  $structureType->getIDsPaths();



        foreach ($paths as $pathItem) {

            $val = [];


            foreach ($data as $item) {
                $val = [...$val,  ...DeepAccess::getByPath($item, $pathItem['path'])];
            }


            if (!$val || !is_array($val) || count($val) == 0) {
                continue;
            }


            $many = $pathItem['many'] ?? false;


            $mongoDocs =  mDB::collection($pathItem['document']->collection)->aggregate([

                ...$pathItem['document']->getPipeline(),
                [
                    '$match' => [
                        '_id' => ['$in' => $val]
                    ]
                ],

            ])->toArray();




            $mongoDocs = Shm::arrayOf($pathItem['document'])->removeOtherItems($mongoDocs);





            $documentsById = [];
            foreach ($mongoDocs as $doc) {


                $documentsById[(string) $doc['_id']] = $doc;
            }





            foreach ($data as &$item) {

                DeepAccess::applyRecursive($item, $pathItem['path'], function ($node) use ($many, $documentsById) {

                    if ($many) {

                        $result = [];

                        if (is_object($node) || is_array($node) || $node instanceof \Traversable) {

                            foreach ($node as $id) {
                                if (isset($documentsById[(string) $id])) {
                                    $result[] = $documentsById[(string) $id];
                                }
                            }
                        }

                        return $result;
                    } else {

                        if (isset($documentsById[(string) $node])) {
                            return $documentsById[(string) $node];
                        }
                    }

                    return null;
                });
            }
        }

        return $data;
    }
}