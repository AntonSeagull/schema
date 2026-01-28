<?php

namespace Shm\ShmAdmin\SchemaCollections;

use Shm\Collection\Collection;
use Shm\Shm;
use Shm\ShmTypes\StructureType;

class ShmBIBoard extends Collection
{


    public  $collection = '_shm_bi_board';


    public static function biBlockStructure(): StructureType
    {
        $schema = Shm::structure([

            'uuid' => Shm::uuid(),

            'type' => Shm::enum([
                "chart",
                "pie",
                "table",
                "square",
                "funnel",
                "abc",
                "xyz"
            ])->staticBaseTypeName("BiBlock"),

            'title' => Shm::string(),

            'source' => Shm::string(),

            "filter" => Shm::mixed(),

            'groupTransform' => Shm::enum([
                "hour",
                "day",
                "month",
                "year",
                "week",
            ])->staticBaseTypeName("BiGroupTransform"),
            'groupByField' => Shm::string(),

            'valueField' => Shm::string(),

            'aggregateType' => Shm::enum([
                "countRoot",
                "sum",
                "avg",
                "min",
                "max"
            ])->staticBaseTypeName("BiAggregate"),


        ]);

        $schema->editable();

        $schema->staticBaseTypeName("BiBlock");

        return $schema;
    }

    public function schema(): StructureType
    {
        return Shm::structure([

            'title' => Shm::string()->editable(),

            'blocks' => Shm::arrayOf(self::biBlockStructure())->editable(),
        ]);
    }
}
