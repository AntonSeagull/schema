<?php

namespace Shm\ShmTypes\CompositeTypes\GeoTypes;

use Shm\Shm;

use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmUtils;

class MongoPolygonType extends StructureType
{

    public string $type = 'mongoPolygon';

    protected StructureType $fields;

    public bool $compositeType = true;



    public function __construct()
    {
        parent::__construct([
            'type' => Shm::static('Polygon'),
            'coordinates' => Shm::arrayOf(
                Shm::arrayOf(
                    Shm::arrayOf(
                        Shm::float()
                    )
                )
            )
        ]);
    }


    public function normalize(mixed $value, $addDefaultValues = false, ?string $processId = null): mixed
    {
        if ($value === null) {
            return null;
        }


        if (!$value['coordinates'] || count($value['coordinates']) === 0) {
            return null;
        }

        $value = parent::normalize($value, $addDefaultValues, $processId);
        $coordinates = $value['coordinates'];



        $validCoordinates = [];
        foreach ($coordinates as $coordinate) {





            $first = $coordinate[0];
            $last = $coordinate[count($coordinate) - 1];

            if ($first !== $last) {
                $coordinate[] = $first;
            }


            $validCoordinates[] = $coordinate;
        }



        return [
            'coordinates' => $validCoordinates,
            'type' => 'Polygon'
        ];
    }




    public function baseTypeName()
    {
        return  ShmUtils::onlyLetters($this->type);
    }
}
