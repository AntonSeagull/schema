<?php

namespace Shm\ShmTypes;

use Shm\ShmDB\mDB;
use F3Mongo\MongoPlugin;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;
use Shm\GQLUtils\GQLBuffer;

use Shm\Shm;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;
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

    public function GQLType(): Type | array | null
    {


        if ($this->document && !$this->document->hide) {



            $this->document->key = $this->key;
            $collection = $this->document->collection;
            $pipeline = $this->document->getPipeline();
            return [
                'type' => Type::listOf($this->document->GQLType()),
                'resolve' => function ($root, $args, $context, ResolveInfo $info) use ($collection, $pipeline) {

                    $fieldName = $info->fieldName;



                    if (!isset($root[$fieldName]) || empty($root[$fieldName])) {
                        return null;
                    }

                    if (isset($root[$fieldName][0]) && (is_string($root[$fieldName][0]) || $root[$fieldName][0] instanceof \MongoDB\BSON\ObjectID)) {
                        $ids = [];
                        foreach ($root[$fieldName] as $field) {
                            $ids[] = mDB::id($field);
                        }
                        GQLBuffer::add($ids, $collection, $pipeline);
                        return new Deferred(function () use ($ids, $collection) {
                            GQLBuffer::load($collection);
                            return GQLBuffer::get($ids, $collection);
                        });
                    }

                    if (isset($root[$fieldName][0]) && (is_array($root[$fieldName][0]) || is_object($root[$fieldName][0]))) {
                        return $root[$fieldName];
                    }

                    return null;
                },


            ];
        }


        return   Type::listOf(Type::string());
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








    public function GQLTypeInput(): ?Type
    {
        return Type::listOf(Type::string());
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
