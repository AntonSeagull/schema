<?php

namespace Shm\ShmTypes\CompositeTypes\GeoTypes;

use GraphQL\Type\Definition\ObjectType;

use Shm\CachedType\CachedInputObjectType;
use Shm\CachedType\CachedObjectType;
use Shm\Shm;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmUtils;

class GeoPointType extends StructureType
{
    public string $type = 'geopoint';

    protected StructureType $fields;


    public function __construct()
    {


        parent::__construct(
            [
                'address' => Shm::string(),
                'lat' => Shm::float(),
                'lng' => Shm::float(),
                'location' => Shm::monogoPoint(),
            ]
        );
    }

    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        $value = parent::normalize($value, $addDefaultValues, $processId);


        if ($value) {

            $lat = $value['lat'] ?? null;
            $lng = $value['lng'] ?? null;

            if ($lat && $lng) {
                $value['location'] = [
                    'type' => 'Point',
                    'coordinates' => [$lng, $lat]
                ];
            } else {
                $value['location'] = null;
            }
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




    public function baseTypeName()
    {
        return  ShmUtils::onlyLetters($this->type);
    }


    public function filterType($safeMode = false): ?BaseType
    {

        if ($this->filterType) {
            return $this->filterType;
        }

        $itemTypeFilter =  Shm::structure([
            'latitude' => Shm::float(),
            'longitude' => Shm::float(),
            'maxDistance' => Shm::int(),
        ])->fullEditable()->staticBaseTypeName("GeoPointFilterType");

        $this->filterType = $itemTypeFilter->title($this->title);
        return  $this->filterType;
    }
}
