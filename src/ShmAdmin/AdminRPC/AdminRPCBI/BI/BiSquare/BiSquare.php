<?php

namespace Shm\ShmAdmin\AdminRPC\AdminRPCBI\BI\BiSquare;

use Shm\ShmAdmin\AdminPanel;

class BiSquare
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


        $valueField = $args['valueField'] ?? null;
        $aggregateType = $args['aggregateType'] ?? null;







        if (!$aggregateType) {
            return [
                'values' => [],
                'keyTransform' => null,
            ];
        }




        if ($aggregateType == "countRoot") {

            $pipeline[] = [
                '$group' => [
                    '_id' => null,
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
                    '_id' => null,
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
