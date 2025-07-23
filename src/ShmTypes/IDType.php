<?php

namespace Shm\ShmTypes;

use InvalidArgumentException;
use Shm\ShmDB\mDB;


use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmRPC\RPCBuffer;

class IDType extends BaseType
{
    public string $type = 'ID';

    public  StructureType | null $document = null;

    private $documentResolver = null;






    public function documentResolver()
    {

        if ($this->documentResolver !== null) {
            $resolver = $this->documentResolver;
            $result = $resolver(); // вызов замыкания
            if ($result instanceof StructureType || $result === null) {
                $this->document = $result;
            } else {
                throw new InvalidArgumentException('documentResolver must return StructureType or null');
            }
        }
    }

    public function __construct(callable | StructureType $documentResolver = null)
    {

        if ($documentResolver instanceof StructureType) {
            $this->document = $documentResolver;
        } else {

            $this->documentResolver = $documentResolver;
        }
    }



    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if ($addDefaultValues &&  $value === null && $this->defaultIsSet) {
            return $this->default;
        }


        if ($value instanceof \MongoDB\BSON\ObjectID) {
            return $value;
        } else if (isset($value['_id'])) {
            return mDB::id($value["_id"]);
        } else if (is_string($value)) {
            return mDB::id($value);
        } else {
            return null;
        }
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
    }


    public function filterType($safeMode = false): ?BaseType
    {



        $itemTypeFilter = Shm::structure([
            'eq' => Shm::ID($this->document)->title('Равно'),
            'in' => Shm::IDs($this->document)->title('Содержит хотя бы одно из'),
            'nin' => Shm::IDs($this->document)->title('Не содержит ни одного из'),
            'all' => Shm::IDs($this->document)->title('Содержит все из списка'),
            'isEmpty' => Shm::enum([
                'true' => 'Да',
                'false' => 'Нет'
            ])->title('Не заполнено'),
            'children' => !$safeMode && $this->document ? $this->document->filterType(true)->title($this->title . ' — дополнительные фильтры') : null,
        ])->editable();


        if (!$safeMode && !$this->document) {
            $itemTypeFilter->staticBaseTypeName("IDFilterType");
        }

        return $itemTypeFilter->editable()->inAdmin($this->inAdmin)->title($this->title);
    }



    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {

        $in = $filter['in'] ?? null;
        $nin = $filter['nin'] ?? null;
        $all = $filter['all'] ?? null;
        $eq = $filter['eq'] ?? null;
        $isEmpty = $filter['isEmpty'] ?? null;

        $path = $absolutePath ? implode('.', $absolutePath) . '.' . $this->key : $this->key;

        $pipeline = [];

        if ($eq !== null) {
            $pipeline[] = [
                '$match' => [
                    $path => mDB::id($eq)
                ]
            ];
        }


        if ($in !== null && count($in) > 0) {
            $pipeline[] = [
                '$match' => [
                    $path => ['$in' => array_map(fn($id) => !($id instanceof \MongoDB\BSON\ObjectId) && isset($id['_id']) ? mDB::id($id['_id']) : mDB::id($id), $in)]
                ]
            ];
        }
        if ($nin !== null  && count($nin) > 0) {
            $pipeline[] = [
                '$match' => [
                    $path => ['$nin' =>  array_map(fn($id) => !($id instanceof \MongoDB\BSON\ObjectId) && isset($id['_id']) ? mDB::id($id['_id']) : mDB::id($id), $nin)]
                ]
            ];
        }
        if ($all !== null  && count($all) > 0) {
            $pipeline[] = [
                '$match' => [
                    $path => ['$all' =>  array_map(fn($id) => !($id instanceof \MongoDB\BSON\ObjectId) && isset($id['_id']) ? mDB::id($id['_id']) : mDB::id($id), $all)]
                ]
            ];
        }
        if ($isEmpty !== null) {

            if ($isEmpty == 'true') {
                $pipeline[] = [
                    '$match' => [
                        '$or' => [
                            [$path => null],
                            [$path => ['$exists' => false]],
                        ]
                    ]
                ];
            }
            if ($isEmpty == 'false') {
                $pipeline[] = [
                    '$match' => [
                        '$or' => [
                            [$path => ['$exists' => true]],
                            [$path => ['$ne' => null]],
                        ]
                    ]
                ];
            }
        }


        return $pipeline;
    }





    public function tsType(): TSType
    {


        if (!$this->isFlatted() && $this->document && !$this->document->hide) {
            return $this->document->tsType();
        } else {

            $TSType = new TSType('string');
            return $TSType;
        }
    }


    public function tsInputType(): TSType
    {



        $TSType = new TSType('string');
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

    public function getIDsPaths(array $path): array
    {



        if ($this->isFlatted()) {


            return [];
        }

        if ($this->document && !$this->document->hide) {


            return [
                [
                    'path' => [...$path],
                    'many' => false,
                    'document' => $this->document,
                ]
            ];
        }

        return [];
    }
}
