<?php

namespace Shm\ShmTypes\CompositeTypes\GeoTypes;

use Shm\Shm;

use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmUtils;

class MongoPolygonType extends StructureType
{

    public string $type = 'mongoPolygon';

    protected StructureType $fields;


    public function __construct()
    {
        parent::__construct([
            'type' => Shm::string()->default("Polygon"),
            'coordinates' => Shm::arrayOf(Shm::arrayOf(Shm::float())),
        ]);
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
