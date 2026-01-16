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

class FileImageType extends StructureType
{
    public string $type = 'fileImage';

    public bool $compositeType = true;


    protected StructureType $fields;


    public $width = 300;
    public $height = 300;

    public $accept = 'image/*';

    public function setAccept(string $accept): self
    {
        $this->accept = $accept;
        return $this;
    }


    public function setResize(int $w = 300, int $h = 300, $k = null): static
    {

        return $this->resize($w, $h);
    }

    public function resize(int $w = 300, int $h = 300, $k = null): static
    {
        $this->width = $w;
        $this->height = $h;
        return $this;
    }


    public static function items(): array
    {
        return [
            "_id" => Shm::ID()->editable(true),
            "fileType" => Shm::string(),
            "name" => Shm::string(),
            "url" => Shm::string(),
            "url_medium" => Shm::string(),
            "url_small" => Shm::string(),
            "blurhash" => Shm::string(),
            "width" => Shm::float(),
            "height" => Shm::float(),
            "type" => Shm::string(),
            "created_at" => Shm::number(),

        ];
    }

    public function exportRow(mixed $value): string | array | null
    {

        if (isset($value['url']) && $value['url']) {
            return (string)$value['url'];
        } else {
            return '';
        }
    }


    public function filterType($safeMode = false): ?BaseType
    {
        return null;
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