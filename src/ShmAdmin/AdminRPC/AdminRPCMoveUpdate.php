<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;

class AdminRPCMoveUpdate
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::bool(),
                'args' => Shm::structure([

                    "_id" => Shm::ID(),
                    'collection' => Shm::string(),
                    'aboveId' => Shm::ID(),
                    'belowId' => Shm::ID()
                ]),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    if (!isset($args['collection'])) {
                        Response::validation("Изменения сортировки не доступна");
                    }


                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);


                    if (!$structure) {
                        Response::validation("Изменения сортировки не доступна");
                    }


                    $_id = $args['_id'] ?? null;
                    $aboveId = $args['aboveId'] ?? null;
                    $belowId = $args['belowId'] ?? null;


                    $currentId = mDB::id($_id);

                    return  $structure->moveRow($currentId, $aboveId, $belowId);
                }

            ];
        });
    }
}
