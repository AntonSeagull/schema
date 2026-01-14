<?php

namespace Shm\ShmTypes;

use InvalidArgumentException;
use Nette\Utils\Strings;
use Shm\ShmDB\mDB;


use Shm\Shm;
use Shm\ShmDB\mDBRedis;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmRPC\RPCBuffer;
use Shm\ShmUtils\DisplayValuePrepare;

/**
 * ID type for schema definitions
 * 
 * This class represents an ID type that can reference other documents
 * and provides expansion capabilities for nested data.
 */
class IDType extends BaseType
{
    public string $type = 'ID';

    private mixed $documentResolver = null;
    public int $defaultValue = 0;


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

    public function getDocument(): StructureType|null
    {

        if (is_callable($this->documentResolver)) {
            return call_user_func($this->documentResolver);
        }

        return null;
    }

    public function equals(mixed $a, mixed $b): bool
    {


        return (string) $a === (string) $b;
    }



    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if ($addDefaultValues &&  $value === null && $this->defaultIsSet) {
            return $this->getDefault();
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
            'eq' => Shm::ID($this->documentResolver, $this->collection)->title('Равно'),
            'in' => Shm::IDs($this->documentResolver, $this->collection)->title('Содержит хотя бы одно из'),
            'nin' => Shm::IDs($this->documentResolver, $this->collection)->title('Не содержит ни одного из'),
            'all' => Shm::IDs($this->documentResolver, $this->collection)->title('Содержит все из списка'),
            'isEmpty' => Shm::enum([
                'true' => 'Да',
                'false' => 'Нет'
            ])->title('Не заполнено'),
        ])->editable();


        $itemTypeFilter->staticBaseTypeName("IDFilterType");

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

            $id = $eq;

            $id = !($id instanceof \MongoDB\BSON\ObjectId) && isset($id['_id']) ? mDB::id($id['_id']) : mDB::id($id);


            $pipeline[] = [
                '$match' => [
                    $path => $id
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


        $TSType = new TSType('string');
        return $TSType;
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

    public function haveID(): bool
    {
        if ($this->documentResolver)
            return true;

        return false;
    }









    public function exportRow(mixed $value): string | array | null
    {

        if (!$value) {
            return "";
        }

        $document = $this->getDocument();


        if (!$document) {
            throw new \Exception("Document not found for IDType: " . $this->key);
        }

        return DisplayValuePrepare::prepareById($document, $value)['displayValue'] ?? "";
    }
}