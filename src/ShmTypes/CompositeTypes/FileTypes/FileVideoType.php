<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes;

use GraphQL\Type\Definition\ObjectType;

use Shm\CachedType\CachedInputObjectType;
use Shm\CachedType\CachedObjectType;
use Shm\Shm;
use Shm\ShmDB\mDB;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmUtils;
use Traversable;

class FileVideoType extends StructureType
{
    public string $type = 'video';

    protected StructureType $fields;



    public static function items(): array
    {
        return [
            "_id" => Shm::ID()->editable(true),
            "fileType" => Shm::string(),
            'name' => Shm::string(),
            'url' => Shm::string(),
            'cover' => Shm::string(),
            'duration' => Shm::number(),
            'width' => Shm::number(),
            'height' =>  Shm::number(),
            "type" => Shm::string(),
            'created_at' => Shm::number(),

        ];
    }


    public function __construct()
    {

        $this->items = self::items();

        $this->childrenEditable(false);
    }



    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if ((is_array($value) || $value instanceof Traversable)) {

            if (!isset($value['url']) || !$value['url']) {
                if (isset($value['_id']) && $value['_id']) {
                    $value = mDB::collection("_files")->findOne(['_id' => mDB::id($value['_id'])]);
                }
            }
        }

        return parent::normalize($value, $addDefaultValues, $processId);
    }


    public function baseTypeName()
    {
        return  ShmUtils::onlyLetters($this->type) . 'File';
    }



    public function getSearchPaths(): array
    {
        return [];
    }
}
