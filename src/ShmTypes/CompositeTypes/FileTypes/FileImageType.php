<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;
use Shm\CachedType\CachedObjectType;
use Shm\Shm;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;

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



    public function GQLType(): Type | array | null
    {
        $fields = [];
        foreach ($this->items as $name => $type) {
            $fields[$name] = [
                'type' => $type->GQLType(),
            ];
        }
        return CachedObjectType::create([
            'name' => 'ImageDefaultType',
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
            'name' => 'ImageDefaultTypeInput',
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
