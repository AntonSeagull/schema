<?php

namespace Shm\ShmTypes\CompositeTypes\GeoTypes;

use GraphQL\Type\Definition\ObjectType;

use Shm\CachedType\CachedInputObjectType;
use Shm\CachedType\CachedObjectType;
use Shm\Shm;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmUtils;

class MongoPointType extends StructureType
{
    public string $type = 'mongoPoint';

    protected StructureType $fields;


    public function __construct()
    {


        parent::__construct([
            'type' => Shm::string()->default("Point"),
            'coordinates' => Shm::arrayOf(Shm::float()),
        ]);
    }


    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_array($value)) {
            $field = $this->title ?? 'Value';
            throw new \InvalidArgumentException("{$field} must be an object/structure (associative array).");
        }
        foreach ($this->items as $name => $type) {
            try {
                $type->validate($value[$name] ?? null);
            } catch (\InvalidArgumentException $e) {
                $field = $this->title ?? $name;
                throw new \InvalidArgumentException("{$field}.{$name}: " . $e->getMessage());
            }
        }
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
