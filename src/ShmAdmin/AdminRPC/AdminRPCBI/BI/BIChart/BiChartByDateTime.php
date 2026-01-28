<?php

namespace Shm\ShmAdmin\AdminRPC\AdminRPCBI\BI\BIChart;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAdmin\Types\BaseStructureType;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\UnixDateTimeType;
use Shm\ShmTypes\UnixDateType;

class BiChartByDateTime
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




        if (!$groupTransform) {
            return [
                'values' => [],
                'keyTransform' => null,
            ];
        }

        if (!in_array($groupTransform, ["hour", "day", "month", "year", "week"])) {
            return [
                'values' => [],
                'keyTransform' => null,
            ];
        }


        $pipeline[] = [
            '$addFields' => [

                '_bi_date' => [
                    '$toDate' => [
                        '$multiply' => ['$' . $groupByField, 1000],
                    ],
                ],
            ],

        ];




        $pipeline[] = [
            '$addFields' => [
                'year' => [
                    '$year' => '$_bi_date',
                ],
                'month' => [
                    '$month' => '$_bi_date',
                ],
                'day' => [
                    '$dayOfMonth' => '$_bi_date',
                ],
                'week' => [
                    '$week' => '$_bi_date',
                ],

                'hour' => [
                    '$hour' => '$_bi_date',
                ],
            ],
        ];



        $_group = [];

        if ($groupTransform == "hour") {
            $_group = [
                '_id' => [
                    'year' => '$year',
                    'month' => '$month',
                    'day' => '$day',
                    'hour' => '$hour',
                ]
            ];
        }
        if ($groupTransform == "day") {
            $_group = [
                '_id' => [
                    'year' => '$year',
                    'month' => '$month',
                    'day' => '$day',
                ]
            ];
        }
        if ($groupTransform == "month") {
            $_group = [
                '_id' => [
                    'year' => '$year',
                    'month' => '$month',
                ]
            ];
        }
        if ($groupTransform == "year") {
            $_group = [

                '_id' => [
                    'year' => '$year',
                ]

            ];
        }
        if ($groupTransform == "week") {
            $_group = [

                '_id' => [
                    'year' => '$year',
                    'week' => '$week',
                ]
            ];
        }




        if ($aggregateType == "countRoot") {

            $_group['value'] = [
                '$sum' => 1,
            ];
            $_group['_minGroupByField'] = ['$min' => '$' . $groupByField];
        } else {


            if (!$valueField) {
                return [];
            }

            $_group['value'] = [
                '$' . $aggregateType => '$' . $valueField,
            ];

            $_group['_minGroupByField'] = ['$min' => '$' . $groupByField];
        }

        $pipeline[] = [
            '$group' => $_group,
        ];

        $pipeline[] = [
            '$sort' => [
                '_minGroupByField' => 1,
            ],
        ];




        $_result = $structure->aggregate($pipeline)->toArray();

        $result = [];

        foreach ($_result as $item) {


            $_minGroupByField = $item['_minGroupByField'] ?? null;
            if (!$_minGroupByField) {
                continue;
            }

            $key = $_minGroupByField;




            if (!$key) {
                continue;
            }

            $result[] = [
                'key' => $key,
                'value' => $item['value'],
            ];
        }


        return [
            'keyTransform' => $groupTransform,
            'values' => $result,
        ];
    }
}
