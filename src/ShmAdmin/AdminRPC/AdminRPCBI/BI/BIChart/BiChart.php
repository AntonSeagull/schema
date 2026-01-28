<?php

namespace Shm\ShmAdmin\AdminRPC\AdminRPCBI\BI\BIChart;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAdmin\Types\BaseStructureType;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\UnixDateTimeType;
use Shm\ShmTypes\UnixDateType;

class BiChart
{


    public static function rpc($args)
    {


        $structure = AdminPanel::fullSchema()->findItemByCollection($args['source']);

        if (!$structure) {

            return [
                'values' => [],
                'keyTransform' => null,
            ];
        }

        $pipeline = $structure->getPipeline();


        if (isset($args['filter'])) {

            $pipelineFilter =  $structure->filterToPipeline($args['filter']);

            if ($pipelineFilter) {

                $pipeline = [
                    ...$pipeline,
                    ...$pipelineFilter,
                ];
            }
        };

        $groupByField = $args['groupByField'] ?? null;
        $valueField = $args['valueField'] ?? null;
        $aggregateType = $args['aggregateType'] ?? null;
        $groupTransform = $args['groupTransform'] ?? null;


        $groupByFieldItem = $structure->findItemByKey($groupByField);

        if (!$groupByFieldItem) {
            return [
                'values' => [],
                'keyTransform' => null,
            ];
        }




        if (!$aggregateType) {
            return [
                'values' => [],
                'keyTransform' => null,
            ];
        }


        if ($groupByFieldItem instanceof UnixDateType || $groupByFieldItem instanceof UnixDateTimeType) {


            return BiChartByDateTime::rpc($args);
        }


        if ($aggregateType == "countRoot") {

            $pipeline[] = [
                '$group' => [
                    '_id' => '$' . $groupByField,
                    'value' => [
                        '$sum' => 1,
                    ],
                ],
            ];
        } else {


            if (!$valueField) {
                return [
                    'values' => [],
                    'keyTransform' => null,
                ];
            }


            $pipeline[] = [
                '$group' => [
                    '_id' => '$' . $groupByField,
                    'value' => [
                        '$' . $aggregateType => '$' . $valueField,
                    ],
                ],
            ];
        }
        $pipeline[] = [
            '$addFields' => [
                'key' => '$_id',
            ],
        ];



        $result = $structure->aggregate($pipeline)->toArray();

        return [
            'values' => $result,
        ];
    }
}
