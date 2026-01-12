<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;

class AdminRPCRemoveApiKey
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::boolean(),
                'args' => [
                    '_id' => Shm::ID(),
                ],
                'resolve' => function ($root, $args) {


                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    $apikeyId = $args['_id'] ?? null;

                    if (!$apikeyId) {
                        Response::validation("Не указан ID ключа");
                    }

                    $apikey = mDB::collection(Auth::$apikey_collection)->findOne([
                        '_id' => mDB::id($apikeyId),
                        'owner' => Auth::getAuthID(),
                    ]);

                    if (!$apikey) {
                        Response::validation("Ключ не найден или не принадлежит вам");
                    }

                    mDB::collection(Auth::$apikey_collection)->deleteOne([
                        '_id' => mDB::id($apikeyId),
                    ]);

                    return true;
                }
            ];
        });
    }
}
