<?php

namespace Shm\Types\CompositeTypes\FileTypes;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;
use Shm\CachedType\CachedObjectType;
use Shm\Shm;
use Shm\Types\BaseType;
use Shm\Types\StructureType;

class FileAnyType extends BaseType
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

    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return $this->default;
        }
        if (!is_array($value)) {
            return null;
        }
        foreach ($this->items as $name => $type) {
            $value[$name] = $type->normalize($value[$name] ?? null);
        }
        return $value;
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
}
