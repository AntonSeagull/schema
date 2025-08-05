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

class FileDocumentType extends StructureType
{
    public string $type = 'document';

    protected StructureType $fields;


    public static function items(): array
    {
        return [
            "_id" => Shm::ID()->editable(true),
            "fileType" =>  Shm::string(),
            'name' =>  Shm::string(),
            'url' => Shm::string(),
            'source' =>  Shm::string(),
            "type" => Shm::string(),
            'created_at' => Shm::string(),
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

    public function filterType($safeMode = false): ?BaseType
    {
        return null;
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
