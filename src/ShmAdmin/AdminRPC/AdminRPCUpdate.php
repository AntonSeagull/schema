<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;

class AdminRPCUpdate
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::structure(
                    [
                        'data' => Shm::arrayOf(Shm::structure([
                            "_id" => Shm::ID(),
                            "*" => Shm::mixed(),
                        ])),
                    ]
                ),
                'args' => Shm::structure([

                    "_ids" => Shm::IDs()->default(null),
                    "collection" => Shm::nonNull(Shm::string()),
                    'values' => Shm::mixed(),

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


                    $rootType = $root->getType();
                    $rootType->items['data'] = Shm::arrayOf($structure);

                    $root->setType($rootType);




                    $values = $args['values'] ?? null;


                    if (!$values) {
                        Response::validation("Нет данных для обновления");
                    }

                    $values = $structure->normalize($values);


                    $values = $structure->removeOtherItems($values);




                    //remove _id
                    if (isset($values['_id'])) {
                        unset($values['_id']);
                    }

                    $ids = $args['_ids'] ?? null;



                    if ($structure->single) {

                        $pipeline = $structure->getPipeline();

                        $pipeline = [
                            ...$pipeline,
                            [
                                '$limit' => 1
                            ],
                        ];

                        $result = $structure->aggregate($pipeline)->toArray() ?? null;

                        $id = $result[0]['_id'] ?? null;



                        if (!$id) {

                            $insert = $structure->insertOne($values);

                            if (!$insert) {
                                Response::validation("Ошибка при добавлении данных");
                            }


                            return [
                                'data' => $structure->find([
                                    '_id' => $insert->getInsertedId(),
                                ])
                            ];
                        } else {

                            $structure->updateMany(
                                [
                                    "_id" => $id,
                                ],
                                [
                                    '$set' => $values
                                ]
                            );


                            return [
                                'data' => $structure->find([
                                    '_id' => $id
                                ]),
                            ];
                        }
                    }




                    if ($ids) {



                        $structure->updateMany(
                            [
                                "_id" => ['$in' => $ids],
                            ],
                            [
                                '$set' => $values
                            ]
                        );

                        return [
                            'data' => $structure->find([
                                '_id' => ['$in' => $ids],
                            ]),
                        ];
                    } else {
                        $insert =  $structure->insertOne($values);

                        if (!$insert) {
                            Response::validation("Ошибка при добавлении данных");
                        }

                        $result = $structure->findOne([
                            '_id' => $insert->getInsertedId(),
                        ]);

                        return [
                            'data' => [$result],
                        ];
                    }
                }

            ];
        });
    }
}
