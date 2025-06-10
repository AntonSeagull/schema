<?php

namespace Shm\Types;

use F3Mongo\mDB;
use GraphQL\Deferred;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;
use Shm\GQLUtils\GQLBuffer;

class IDType extends BaseType
{
    public string $type = 'ID';

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


    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return $this->default;
        }


        if ($value instanceof \MongoDB\BSON\ObjectID) {
            return $value;
        } else if (isset($value['_id'])) {
            return $this->getId($value["_id"]);
        } else if (is_string($value)) {
            return $this->getId($value);
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

        if ($this->document) {
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


    public function GQLFilterTypeInput(): ?Type
    {
        return  CachedInputObjectType::create([
            'name' => 'IdInputFilterInput',
            'fields' => [

                'in' => [
                    'type' => Type::listOf(Type::string()),
                ],
                'nin' => [
                    'type' => Type::listOf(Type::string()),

                ],
                'all' => [
                    'type' => Type::listOf(Type::string()),

                ],
                'notEmpty' => [
                    'type' => Type::boolean(),
                ],
                'isEmpty' => [
                    'type' => Type::boolean(),
                ],


            ],
        ]);
    }

    public function GQLTypeInput(): ?Type
    {
        return Type::string();
    }
}
