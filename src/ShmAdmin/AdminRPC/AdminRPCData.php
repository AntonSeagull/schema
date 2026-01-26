<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;

class AdminRPCData
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::structure([
                    'data' => Shm::arrayOf(Shm::structure([
                        "_id" => Shm::ID(),
                        "*" => Shm::mixed(),
                    ])),
                    'limit' => Shm::int(),
                    'hash' => Shm::string(),
                    'offset' => Shm::int(),
                    'total' => Shm::int(),
                ]),
                'args' => Shm::structure([

                    "_id" => Shm::ID()->default(null),
                    'table' => Shm::boolean()->default(false),
                    "collection" => Shm::nonNull(Shm::string()),
                    'limit' => Shm::int()->default(30),

                    'offset' =>  Shm::int()->default(0),
                    'search' => Shm::string()->default(''),
                    'sort' => Shm::structure([
                        'direction' => Shm::enum([
                            'ASC' => 'По возрастанию',
                            'DESC' => 'По убыванию',
                        ])->default('DESC'),
                        'field' => Shm::string(),
                    ]),
                    'filter' => Shm::mixed(),
                    'pipeline' => Shm::mixed(),

                ]),
                'resolve' => function ($root, $args) {



                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }


                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);




                    $structure->inTableThis(true);




                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }


                    $rootType = $root->getType();
                    $rootType->items['data'] = Shm::arrayOf($structure);

                    $root->setType($rootType);

                    $pipeline = $structure->getPipeline();



                    if ($structure->single) {



                        $pipeline = [
                            ...$pipeline,
                            [
                                '$limit' => 1
                            ],
                        ];

                        $result = $structure->aggregate($pipeline)->toArray() ?? null;


                        if (!$result) {

                            return [
                                'data' =>  [$structure->normalize([], true)]
                            ];
                        } else {

                            return  [
                                'data' => $result
                            ];
                        }
                    }







                    if (isset($args['_id'])) {





                        $pipeline = [
                            ...$pipeline,
                            [
                                '$match' => [
                                    "_id" => mDB::id($args['_id']),
                                ],
                            ],
                            [
                                '$limit' => 1
                            ],
                        ];




                        $result = $structure->aggregate($pipeline)->toArray() ?? null;




                        if (!$result) {

                            ShmRPC::error("Документ не найден или нет доступа");
                        } else {



                            return  [
                                'data' => $result,
                                'hash' => mDB::hashDocuments($result),
                            ];
                        }
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

                    if (isset($args['pipeline'])) {

                        $pipeline = [
                            ...$pipeline,
                            ...$args['pipeline'],
                        ];
                    }


                    if (isset($args['search'])) {

                        $pipeline[] = [
                            '$match' => [
                                'search_string' => ['$regex' => mb_strtolower(trim($args['search'])), '$options' => 'i'],
                            ],
                        ];
                    }







                    $total = 0;



                    Response::startTraceTiming("total_count");
                    $total =  $structure->aggregate([
                        ...$pipeline,
                        [
                            '$count' => 'total',
                        ],
                    ])->toArray()[0]['total'] ?? 0;
                    Response::endTraceTiming("total_count");

                    $_limit = $args['limit'] ?? null;


                    if ($_limit === 0) {
                        return [
                            'data' => [],
                            'limit' => 0,
                            'offset' => 0,
                            'total' => $total,
                        ];
                    }






                    if (isset($args['sort']) && isset($args['sort']['field']) && isset($args['sort']['direction'])) {

                        $pipeline[] = [
                            '$sort' => [
                                $args['sort']['field'] => $args['sort']['direction'] == "DESC" ? -1 : 1,
                            ],
                        ];
                    } else {

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
                    }

                    if (isset($args['offset']) && $args['offset'] > 0) {

                        $pipeline[] = [
                            '$skip' => $args['offset'],
                        ];
                    }


                    $pipeline[] = [
                        '$limit' => $args['limit'] ?? 20,
                    ];

                    if ($args['table'] ?? false) {
                        $hideProjection =  $structure->getProjection('inTable');


                        if ($hideProjection) {
                            $pipeline[] = [
                                '$project' => [
                                    ...$hideProjection,
                                    'updated_at' => 1
                                ]
                            ];
                        }
                    }




                    Response::startTraceTiming("data_aggregate");
                    $result = $structure->aggregate(
                        $pipeline

                    )->toArray();
                    Response::endTraceTiming("data_aggregate");





                    return [
                        'data' => $result,
                        'limit' => $args['limit'] ?? 20,
                        'offset' => $args['offset'] ?? 0,
                        'hash' => mDB::hashDocuments($result),
                        'total' => $total,
                    ];
                }

            ];
        });
    }
}
