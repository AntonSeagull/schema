<?php

namespace Shm\ShmTypes;

use InvalidArgumentException;
use Shm\ShmDB\mDB;

use Shm\Shm;
use Shm\ShmDB\mDBRedis;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmRPC\RPCBuffer;
use Traversable;

/**
 * IDs type for schema definitions
 * 
 * This class represents an array of IDs type that can reference multiple documents
 * and provides expansion capabilities for nested data.
 */
class IDsType extends BaseType
{
    public string $type = 'IDs';

    private mixed $documentResolver = null;




    public string | null $collection = null;


    public function __construct(callable  | StructureType $documentResolver = null, string | null $collection = null)
    {


        if ($documentResolver) {

            //If set documentResolver, collection must be
            if ($documentResolver && !$collection) {

                if (!$collection && $documentResolver instanceof StructureType && $documentResolver->collection) {
                    $this->collection = $documentResolver->collection;
                } else {
                    throw new \Exception("Collection must be set if documentResolver is set");
                }
            } else if ($collection) {
                $this->collection = $collection;
            }
        }


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
            'in' => Shm::IDs($this->documentResolver, $this->collection)->title('Содержит хотя бы один из'),
            'nin' => Shm::IDs($this->documentResolver, $this->collection)->title('Не содержит ни одного из'),
            'all' => Shm::IDs($this->documentResolver, $this->collection)->title('Содержит все из списка'), // корректно для $all
            'setIsSubset' => Shm::IDs($this->documentResolver, $this->collection)->title('Содержит только из списка'), // подчёркивает "не больше"
            'isEmpty' => Shm::enum([
                'true' => 'Да',
                'false' => 'Нет'
            ])->title('Не заполнено'),
        ])->editable();


        $itemTypeFilter->staticBaseTypeName("IDsFilterType");



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



        $TSType = new TSType('string[]');

        return $TSType;
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

    public function getDocument(): StructureType|null
    {
        if (is_callable($this->documentResolver)) {
            return call_user_func($this->documentResolver);
        }
        return null;
    }


    public function exportRow(mixed $value): string | array | null
    {

        $document = $this->getDocument();
        if (!$document) {
            throw new \Exception("Document not found for IDType: " . $this->key);
        }



        if ($document && !$document->hide) {



            if (is_array($value) || $value instanceof Traversable) {

                $value = (array)$value;

                if (count($value) === 0) {
                    return "";
                }


                $docs = [];

                foreach ($value as $index => $id) {
                    $item = mDBRedis::get($document->collection, (string)$id);
                    if ($item) {
                        $docs[] = $item;
                        unset($value[$index]);
                    }
                }


                $value = array_values($value);

                if (count($value) > 0) {
                    $items = mDB::collection($document->collection)->find(['_id' => ['$in' => array_map(fn($id) => mDB::id($id), $value)]])->toArray();
                    $docs = array_merge($docs, $items);
                }

                if (count($docs) === 0) {
                    return "";
                }

                $displayValuesResult = [];
                foreach ($docs as $index => $doc) {




                    $displayValues = $document->displayValues($doc);
                    if (is_array($displayValues) && count($displayValues) > 1) {
                        $displayValuesResult[] = implode(', ', $displayValues);
                    } else {
                        $displayValues = $document->fallbackDisplayValues($doc);
                        if (is_array($displayValues) && count($displayValues) > 1) {
                            $displayValuesResult[] = implode(', ', $displayValues);
                        }
                    }
                }

                return implode(' | ', $displayValuesResult);
            }
        } else {
            return "";
        }

        return "";
    }
}