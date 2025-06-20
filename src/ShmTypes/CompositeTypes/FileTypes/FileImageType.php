<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes;

use GraphQL\Type\Definition\ObjectType;

use Shm\CachedType\CachedInputObjectType;
use Shm\CachedType\CachedObjectType;
use Shm\Shm;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmUtils;

class FileImageType extends StructureType
{
    public string $type = 'image';

    protected StructureType $fields;


    public $width = 300;
    public $height = 300;


    public function setResize(int $w = 300, int $h = 300): self
    {

        return $this->resize($w, $h);
    }

    public function resize(int $w = 300, int $h = 300): self
    {
        $this->width = $w;
        $this->height = $h;
        return $this;
    }


    public function __construct()
    {

        $this->items = [
            "_id" => Shm::string(),
            'url' => Shm::string(),
            'url_medium' => Shm::string(),
            'url_small' => Shm::string(),
            "blurhash" => Shm::string(),
            "name" => Shm::string(),
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
