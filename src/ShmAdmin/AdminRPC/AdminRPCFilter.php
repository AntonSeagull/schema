<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;

class AdminRPCFilter
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => AdminPanel::baseStructure(),
                'args' => [
                    'collection' => Shm::nonNull(Shm::string()),
                ],
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }

                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);

                    return $structure->filterType()->json();
                }
            ];
        });
    }
}
