<?php

namespace Shm\Types\CompositeTypes\GeoTypes;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;
use Shm\CachedType\CachedObjectType;
use Shm\Shm;
use Shm\Types\BaseType;
use Shm\Types\StructureType;

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

    public function GQLType(): Type | array | null
    {
        $fields = [];
        foreach ($this->items as $name => $type) {
            $fields[$name] = [
                'type' => $type->GQLType(),
            ];
        }
        return CachedObjectType::create([
            'name' => 'MongoPointType',
            'fields' => function () use ($fields) {
                return $fields;
            },
        ]);
    }

    public function GQLTypeInput(): ?Type
    {
        $fields = [];
        foreach ($this->items as $key => $type) {
            $fields[$key] = [
                'type' => $type->keyIfNot($key)->GQLTypeInput(),
            ];
        }
        return CachedInputObjectType::create([
            'name' => 'MongoPointTypeInput',
            'fields' => function () use ($fields) {
                return $fields;
            },
        ]);
    }
}