<?php

namespace Shm\ShmTypes;

use InvalidArgumentException;
use Shm\ShmDB\mDB;

use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmRPC\RPCBuffer;
use Traversable;

class IDsType extends BaseType
{
    public string $type = 'IDs';

    public  StructureType | null $document = null;



    private $documentResolver = null;



    public function expand(): static
    {

        $this->expanded = true;

        if ($this->documentResolver !== null) {
            $resolver = $this->documentResolver;
            $result = $resolver(); // вызов замыкания
            if ($result instanceof StructureType || $result === null) {
                $this->document = $result;
            } else {
                throw new InvalidArgumentException('documentResolver must return StructureType or null');
            }
        }

        return $this;
    }





    public function __construct(callable  | StructureType $documentResolver = null)
    {

        if ($documentResolver instanceof StructureType) {
            $this->documentResolver = function () use ($documentResolver) {
                return $documentResolver;
            };
        } else {

            $this->documentResolver = $documentResolver;
        }
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



    public function filterType($safeMode = false): ?BaseType
    {


        $itemTypeFilter = Shm::structure([
            'in' => Shm::IDs($this->document)->title('Содержит хотя бы один из'),
            'nin' => Shm::IDs($this->document)->title('Не содержит ни одного из'),
            'all' => Shm::IDs($this->document)->title('Содержит все из списка'), // корректно для $all
            'setIsSubset' => Shm::IDs($this->document)->title('Содержит только из списка'), // подчёркивает "не больше"
            'isEmpty' => Shm::enum([
                'true' => 'Да',
                'false' => 'Нет'
            ])->title('Не заполнено'),
            'children' =>  !$safeMode && $this->document ? $this->document->filterType(true)->title($this->title . ' — дополнительные фильтры') : null,
        ])->editable();


        if (!$safeMode && !$this->document) {
            $itemTypeFilter->staticBaseTypeName("IDsFilterType");
        }



        return $itemTypeFilter->editable()->inAdmin($this->inAdmin)->title($this->title)->title($this->title);
    }


    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {

        $in = $filter['in'] ?? null;
        $nin = $filter['nin'] ?? null;
        $all = $filter['all'] ?? null;
        $isEmpty = $filter['isEmpty'] ?? null;
        $setIsSubset = $filter['setIsSubset'] ?? null;
        $eq = $filter['eq'] ?? null;

        $path = $absolutePath ? implode('.', $absolutePath) . '.' . $this->key : $this->key;

        $pipeline = [];

        if ($eq !== null) {
            $pipeline[] = [
                '$match' => [
                    $path => ['$eq' => array_map(fn($id) => !($id instanceof \MongoDB\BSON\ObjectId) && isset($id['_id']) ? mDB::id($id['_id']) : mDB::id($id), $eq)]
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
                    $path => ['$nin' => array_map(fn($id) => !($id instanceof \MongoDB\BSON\ObjectId) && isset($id['_id']) ? mDB::id($id['_id']) : mDB::id($id), $nin)]
                ]
            ];
        }
        if ($all !== null  && count($all) > 0) {
            $pipeline[] = [
                '$match' => [
                    $path => ['$all' => array_map(fn($id) => !($id instanceof \MongoDB\BSON\ObjectId) && isset($id['_id']) ? mDB::id($id['_id']) : mDB::id($id), $all)]
                ]
            ];
        }
        if ($isEmpty !== null) {

            if ($isEmpty == 'true') {
                $pipeline[] = [
                    '$match' => [
                        '$expr' => [
                            '$eq' => [
                                ['$size' => '$' . $path],
                                0
                            ]
                        ]
                    ]
                ];
            }
            if ($isEmpty == 'false') {
                $pipeline[] = [
                    '$match' => [
                        '$expr' => [
                            '$gt' => [
                                ['$size' => '$' . $path],
                                0
                            ]
                        ]
                    ]
                ];
            }
        }
        if ($setIsSubset !== null  && count($setIsSubset) > 0) {
            $pipeline[] = [
                '$match' => [
                    '$expr' => [
                        '$setIsSubset' => ['$' . $path, array_map(fn($id) => !($id instanceof \MongoDB\BSON\ObjectId) && isset($id['_id']) ? mDB::id($id['_id']) : mDB::id($id), $setIsSubset)]
                    ]
                ]
            ];
        }





        return $pipeline;
    }









    public function tsType(): TSType
    {


        if ($this->document && !$this->document->hide) {

            $documentTsType = $this->document->tsType();

            $TSType = new TSType($documentTsType->getTsTypeName() . '[]');


            return $TSType;
        } else {

            $TSType = new TSType('string[]');

            return $TSType;
        }
    }


    public function tsInputType(): TSType
    {


        $TSType = new TSType('string[]');

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

    public function haveID(): bool
    {
        if ($this->documentResolver)
            return true;

        return false;
    }


    public function computedReport(StructureType | null $root = null, $path = [], $pipeline = [])
    {


        return null;
    }


    public function getIDsPaths(array $path): array
    {



        if ($this->document && !$this->document->hide) {
            return [
                [
                    'path' => [...$path],
                    'many' => true,
                    'document' => $this->document,
                ]
            ];
        }

        return [];
    }
}
