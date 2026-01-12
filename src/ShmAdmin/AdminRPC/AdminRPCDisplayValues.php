<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\DisplayValuePrepare;

class AdminRPCDisplayValues
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::structure([
                    "data" => Shm::arrayOf(Shm::structure([
                        '_id' => Shm::string(),
                        'displayValue' => Shm::string(),
                    ])),
                    'limit' => Shm::int(),
                    'offset' => Shm::int(),
                    'total' => Shm::int(),
                ]),
                'args' => Shm::structure([
                    "collection" => Shm::nonNull(Shm::string()),
                    "ids" => Shm::arrayOf(Shm::string()),
                    'limit' => Shm::int()->default(30),

                    'offset' =>  Shm::int()->default(0),
                    'search' => Shm::string()->default(''),
                    'filter' => Shm::mixed(),
                ]),
                'resolve' => function ($root, $args) {

                    if (!isset($args['collection'])) {
                        return [];
                    }

                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);


                    if (!$structure) {
                        return [];
                    }

                    $pipeline = [
                        ...$structure->getPipeline(),
                    ];
                    if (isset($args['ids']) && count($args['ids']) > 0) {

                        $ids = array_map(function ($id) {
                            return mDB::id($id);
                        }, $args['ids']);

                        $pipeline = [
                            ...$pipeline,
                            [
                                '$match' => [
                                    '_id' => ['$in' => $ids]
                                ],
                            ],
                        ];
                    }

                    if (isset($args['filter'])) {


                        $pipelineFilter =  $structure->filterToPipeline($args['filter']);


                        if ($pipelineFilter) {

                            $pipeline = [
                                ...$pipeline,
                                ...$pipelineFilter,
                            ];
                        }
                    };




                    if (isset($args['search'])) {

                        $pipeline[] = [
                            '$match' => [
                                'search_string' => ['$regex' => mb_strtolower(trim($args['search'])), '$options' => 'i'],
                            ],
                        ];
                    }


                    $total =  $structure->aggregate([
                        ...$pipeline,
                        [
                            '$count' => 'total',
                        ],
                    ])->toArray()[0]['total'] ?? 0;









                    if ($structure->manualSort) {

                        $pipeline[] = [
                            '$sort' => [
                                "_sortWeight" => -1,

                            ],
                        ];
                    } else {

                        $pipeline[] = [
                            '$sort' => [
                                "_id" => -1,
                            ],
                        ];
                    }


                    if (isset($args['offset']) && $args['offset'] > 0) {

                        $pipeline[] = [
                            '$skip' => $args['offset'],
                        ];
                    }


                    $pipeline[] = [
                        '$limit' => $args['limit'] ?? 30,
                    ];




                    $data =  $structure->aggregate($pipeline)->toArray();


                    return [
                        "data" => DisplayValuePrepare::prepare($structure, $data),
                        "limit" => $args['limit'] ?? 30,
                        "offset" => $args['offset'] ?? 0,
                        "total" => $total,
                    ];
                }
            ];
        });
    }
}