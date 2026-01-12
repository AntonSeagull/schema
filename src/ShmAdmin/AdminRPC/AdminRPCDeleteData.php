<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;

class AdminRPCDeleteData
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::bool(),
                'args' => Shm::structure([

                    "_ids" => Shm::IDs()->default(null),
                    "collection" => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }



                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);


                    $_ids = $args['_ids'] ?? null;

                    if (!$_ids) {
                        Response::validation("Нет данных для удаления");
                    }


                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }


                    $structure->deleteMany([
                        '_id' => ['$in' => $_ids]
                    ]);

                    return true;
                }
            ];
        });
    }
}
