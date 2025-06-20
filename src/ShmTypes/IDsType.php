<?php

namespace Shm\ShmTypes;

use Shm\ShmDB\mDB;

use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmRPC\RPCBuffer;
use Traversable;

class IDsType extends BaseType
{
    public string $type = 'IDs';

    public  StructureType | null $document = null;

    public function __construct(StructureType | null $document = null)
    {
        $this->document = $document;
    }

    public function normalize(mixed $values, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if ($addDefaultValues && $values === null && $this->defaultIsSet) {
            return $this->default;
        }


        if (!is_array($values) && !is_object($values)) {
            return null;
        }

        $_ids = [];



        foreach ($values as $value) {
            if ($value instanceof \MongoDB\BSON\ObjectID) {
                $_ids[] = $value;
            } else if (isset($value['_id'])) {
                $_ids[] = mDB::id($value["_id"]);
            } else if (is_string($value)) {
                $_ids[] = mDB::id($value);
            } else {
            }
        }



        return $_ids;
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
    }



    public function filterType(): ?BaseType
    {

        if ($this->filterType) {
            return $this->filterType;
        }

        $itemTypeFilter = Shm::structure([
            'in' => Shm::arrayOf(Shm::string()->title('In')),
            'nin' => Shm::arrayOf(Shm::string()->title('Not In')),
            'all' => Shm::arrayOf(Shm::string()->title('All')),
            'isEmpty' => Shm::arrayOf(Shm::boolean()->title('Is Empty')),
        ])->fullEditable();

        $this->filterType = $itemTypeFilter;
        return  $this->filterType;
    }









    public function tsType(): TSType
    {


        if ($this->document && !$this->document->hide) {

            $documentTsType = $this->document->tsType();

            $TSType = new TSType($documentTsType->getTsTypeName() . 'Array', $documentTsType->getTsTypeName() . '[]');


            return $TSType;
        } else {

            $TSType = new TSType("IDs", 'string[]');

            return $TSType;
        }
    }


    public function tsInputType(): TSType
    {


        $TSType = new TSType("IDs", 'string[]');

        return $TSType;
    }


    public function updatePath(array | null $path = null): void
    {
        if ($this->key === null) {
            throw new \LogicException('Key must be set before updating path.');
        }

        $newPath = [...$path, $this->key];
        $this->path = $newPath;
    }

    public function getIDsPaths(): array
    {
        if ($this->document && !$this->document->hide) {
            return [
                [
                    'path' => $this->path,
                    'many' => true,
                    'document' => $this->document,
                ]
            ];
        }

        return [];
    }
}
