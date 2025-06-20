<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes;

use GraphQL\Type\Definition\ObjectType;

use Shm\CachedType\CachedInputObjectType;
use Shm\CachedType\CachedObjectType;
use Shm\Shm;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmUtils;

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


    public function baseTypeName()
    {
        return  ShmUtils::onlyLetters($this->type);
    }




    public function getSearchPaths(): array
    {
        return [];
    }
}