<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;

class AdminRPCHash
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::string(),
                'args' => Shm::structure([
                    "_id" => Shm::ID(),
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
                    'stage' => Shm::string()
                ]),

                'resolve' => function ($root, $args) {



                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }



                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);



                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }


                    $pipeline = $structure->getPipeline();


                    if (isset($args['stage'])) {

                        $stage = $structure->findStage($args['stage']);

                        if ($stage) {
                            $pipeline = [
                                ...$pipeline,
                                ...$stage->getPipeline(),
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




                    if (isset($args['search'])) {

                        $pipeline[] = [
                            '$match' => [
                                'search_string' => ['$regex' => mb_strtolower(trim($args['search'])), '$options' => 'i'],
                            ],
                        ];
                    }



                    $_limit = $args['limit'] ?? null;

                    if (!$_limit) {
                        return null;
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


                    if (isset($args['_id']) && $args['_id']) {
                        $pipeline[] = [
                            '$match' => [
                                "_id" => mDB::id($args['_id']),
                            ],
                        ];
                    }


                    //Оставляем только _id и updated_at для хеширования
                    $pipeline[] = [
                        '$project' => [
                            '_id' => 1,
                            'updated_at' => 1,
                        ],
                    ];

                    $pipeline[] = [
                        '$limit' => $args['limit'] ?? 20,
                    ];




                    Response::startTraceTiming("data_aggregate");
                    $result = $structure->aggregate(
                        $pipeline

                    )->toArray();
                    Response::endTraceTiming("data_aggregate");


                    return  mDB::hashDocuments($result);
                }

            ];
        });
    }
}
