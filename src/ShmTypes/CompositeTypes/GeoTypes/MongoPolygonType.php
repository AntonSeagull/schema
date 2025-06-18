<?php

namespace Shm\ShmTypes\CompositeTypes\GeoTypes;

use Shm\Shm;

use Shm\ShmTypes\StructureType;

class MongoPolygonType extends StructureType
{
    public string $type = 'mongoPolygonType';

    protected StructureType $fields;


    public function __construct()
    {
        parent::__construct([
            'type' => Shm::string()->default("Polygon"),
            'coordinates' => Shm::arrayOf(Shm::arrayOf(Shm::float())),
        ]);
    }




    public function getSearchPaths(): array
    {
        return [];
    }
}
