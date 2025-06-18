<?php

namespace Shm\ShmTypes;

use Shm\ShmDB\mDB;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;
use Shm\GQLUtils\GQLBuffer;

use Shm\Shm;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;
use Shm\ShmRPC\RPCBuffer;

class IDType extends BaseType
{
    public string $type = 'ID';

    public  StructureType | null $document = null;

    public function __construct(StructureType | null $document = null)
    {

        $this->document = $document;
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

    public function GQLType(): Type | array | null
    {

        if ($this->document && !$this->document->hide) {
            $this->document->key = $this->key;
            $collection = $this->document->collection;
            $pipeline = $this->document->getPipeline();
            return [
                'type' => $this->document->GQLType(),

                'resolve' => function ($root, $args, $context, ResolveInfo $info) use ($collection, $pipeline) {
                    $fieldName = $info->fieldName;

                    if (!isset($root[$fieldName]) || empty($root[$fieldName])) {
                        return null;
                    }
                    if (
                        is_string($root[$fieldName]) || $root[$fieldName] instanceof \MongoDB\BSON\ObjectID || (is_array($root[$fieldName]) && isset($root[$fieldName]['oid']))
                    ) {

                        if (is_array($root[$fieldName]) && isset($root[$fieldName]['oid'])) {
                            $id = mDB::id($root[$fieldName]['oid']);
                        } else {
                            $id = mDB::id($root[$fieldName]);
                        }

                        GQLBuffer::add([$id], $collection, $pipeline);
                        return new Deferred(function () use ($id, $collection) {
                            GQLBuffer::load($collection);
                            return GQLBuffer::get($id, $collection);
                        });
                    }

                    if (is_array($root[$fieldName]) || is_object($root[$fieldName])) {
                        return $root[$fieldName];
                    }
                    return null;
                }

            ];
        }


        return Type::string();
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
            'isEmpty' => Shm::boolean()->title('Is Empty'),
        ])->fullEditable();

        $this->filterType = $itemTypeFilter;
        return  $this->filterType;
    }




    public function GQLTypeInput(): ?Type
    {
        return Type::string();
    }

    public function tsType(): TSType
    {


        if ($this->document && !$this->document->hide) {
            return $this->document->tsType();
        } else {

            $TSType = new TSType('String', 'string');
            return $TSType;
        }
    }


    public function tsInputType(): TSType
    {



        $TSType = new TSType('String', 'string');
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
                    'many' => false,
                    'document' => $this->document,
                ]
            ];
        }

        return [];
    }
}
