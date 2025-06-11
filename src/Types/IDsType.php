<?php

namespace Shm\Types;

use F3Mongo\mDB;
use F3Mongo\MongoPlugin;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;
use Shm\GQLUtils\GQLBuffer;
use Shm\Shm;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;

class IDsType extends BaseType
{
    public string $type = 'IDs';

    public  StructureType | null $document = null;

    public function __construct(StructureType | null $document = null)
    {
        $this->document = $document;
    }

    private  function getId($id = false)
    {
        if ($id) {
            $id = (string) $id;

            if (preg_match('/^[0-9a-f]{24}$/i', $id) === 1) {
                return $id ? new \MongoDB\BSON\ObjectID($id) : new \MongoDB\BSON\ObjectID();
            } else {
                return false;
            }
        }
    }


    public function normalize(mixed $values, $addDefaultValues = false): mixed
    {

        if ($addDefaultValues &&  $values === null && $this->defaultIsSet) {
            return $this->default;
        }

        $_ids = [];

        foreach ($values as $value) {
            if ($value instanceof \MongoDB\BSON\ObjectID) {
                $_ids[] = $value;
            } else if (isset($value['_id'])) {
                $_ids[] = $this->getId($value["_id"]);
            } else if (is_string($value)) {
                $_ids[] = $this->getId($value);
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


        if ($this->document) {



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

        return  Shm::structure([
            'in' => Shm::arrayOf(Shm::string()->title('In')),
            'nin' => Shm::arrayOf(Shm::string()->title('Not In')),
            'all' => Shm::arrayOf(Shm::string()->title('All')),
            'isEmpty' => Shm::arrayOf(Shm::boolean()->title('Is Empty')),
        ])->fullEditable();
    }





    public function GQLTypeInput(): ?Type
    {
        return Type::listOf(Type::string());
    }

    public function tsType(): TSType
    {


        if ($this->document) {

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
}
