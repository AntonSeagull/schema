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

    public function filterType($safeMode = false): ?BaseType
    {

        if ($this->filterType) {
            return $this->filterType;
        }

        $itemTypeFilter =  Shm::structure([
            'geoNear' => Shm::structure([
                'latitude' => Shm::float(),
                'longitude' => Shm::float(),
                'maxDistance' => Shm::int(),
            ])->staticBaseTypeName("GeoNearFilterType"),



            'geoWithinCenter' => Shm::structure([
                'center' => Shm::arrayOf(Shm::float()),
                'maxDistance' => Shm::int(),
            ])->staticBaseTypeName("GeoWithinCenterFilterType"),


            'geoWithinPolygon' => Shm::arrayOf(Shm::arrayOf(Shm::arrayOf(Shm::float()))),


        ])->fullEditable()->staticBaseTypeName("GeoPointFilterType");

        $this->filterType = $itemTypeFilter->title($this->title);
        return  $this->filterType;
    }

    private function metersToDegrees(float $meters): float
    {
        return $meters / 111_320; // 1° ≈ 111.32 км = 111320 м
    }

    private function closePolygonLoop(array $ring): array
    {
        if ($ring[0] !== end($ring)) {
            $ring[] = $ring[0];
        }
        return $ring;
    }

    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {

        $geoNear = $filter['geoNear'] ?? null;

        $geoWithinCenter = $filter['geoWithinCenter'] ?? null;

        $geoWithinPolygon = $filter['geoWithinPolygon'] ?? null;

        $path = $absolutePath ? implode('.', $absolutePath) . '.' . $this->key : $this->key;

        $pipeline = [];

        if ($geoNear !== null) {

            $longitude = $geoNear['longitude'] ?? null;
            $latitude = $geoNear['latitude'] ?? null;
            $maxDistance = $geoNear['maxDistance'] ?? null;

            if ($longitude && $latitude) {
                $pipeline[] = [
                    '$geoNear' => [
                        'near' => [
                            'type' => 'Point',
                            'coordinates' => [$longitude, $latitude],
                        ],
                        'distanceField' => 'distance_near_' . $this->key,
                        'spherical' => true,
                        'maxDistance' => $maxDistance ?? null,
                        'key' => $path . '.coordinates',
                    ]
                ];

                $pipeline[] = [
                    '$sort' => [
                        'distance_near_' . $this->key => 1
                    ]
                ];
            }
        }

        if ($geoWithinCenter != null) {


            $center = $geoWithinCenter['center'] ?? null;
            $maxDistance = $geoWithinCenter['maxDistance'] ?? null;

            if ($center && is_array($center) && count($center) === 2) {
                $longitude = $center[0];
                $latitude = $center[1];

                if ($maxDistance !== null) {
                    $maxDistance = $this->metersToDegrees($maxDistance);
                }

                $pipeline[] = [
                    '$match' => [
                        $path  => [
                            '$geoWithin' => [
                                '$center' => [$center, $maxDistance]
                            ]
                        ]
                    ]
                ];
            }
        }


        if ($geoWithinPolygon != null) {


            if (is_array($geoWithinPolygon) && count($geoWithinPolygon) > 0) {

                $_geoWithinPolygon = [];
                foreach ($geoWithinPolygon as $polygon) {
                    $_geoWithinPolygon[] = [$this->closePolygonLoop($polygon)];
                }





                $geoWithinPolygon = $_geoWithinPolygon;

                //Проверям полигоны



                $countPolygons = count($geoWithinPolygon);


                if ($countPolygons == 1) {

                    $polygon = $geoWithinPolygon[0];

                    $pipeline[] = [
                        '$match' => [
                            $path  => [
                                '$geoWithin' => [
                                    '$geometry' =>    [
                                        'type' => 'Polygon',
                                        'coordinates' => $polygon
                                    ]
                                ]
                            ]
                        ]
                    ];
                } else {


                    $pipeline[] = [
                        '$match' => [
                            $path  => [
                                '$geoWithin' => [
                                    '$geometry' =>    [
                                        'type' => 'MultiPolygon',
                                        'coordinates' => $geoWithinPolygon
                                    ]
                                ]
                            ]
                        ]
                    ];
                }
            }
        }



        return $pipeline;
    }

    public function createIndex($absolutePath = null): array
    {

        if (!$absolutePath) {
            return [];
        }

        $path = implode('.', $absolutePath);

        return [$path => '2dsphere'];
    }
}
