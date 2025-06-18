<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;
use Shm\CachedType\CachedObjectType;
use Shm\Shm;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;

class FileAnyType extends StructureType
{
    public string $type = 'file';

    protected StructureType $fields;


    public function __construct()
    {

        $this->items = [
            "_id" => Shm::string(),
            'url' => Shm::string(),
            "cover" => Shm::string(),
        ];
    }




    public function GQLType(): Type | array | null
    {
        $fields = [];
        foreach ($this->items as $name => $type) {
            $fields[$name] = [
                'type' => $type->GQLType(),
            ];
        }
        return CachedObjectType::create([
            'name' => 'FileDefaultType',
            'fields' => function () use ($fields) {
                return $fields;
            },
        ]);
    }

    public function GQLTypeInput(): ?Type
    {
        $fields = [];
        foreach ($this->items as $name => $type) {
            $fields[$name] = [
                'type' => $type->GQLTypeInput(),
            ];
        }
        return CachedInputObjectType::create([
            'name' => 'FileDefaultTypeInput',
            'fields' => function () use ($fields) {
                return $fields;
            },
        ]);
    }


    public function getSearchPaths(): array
    {
        return [];
    }
}
