<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\CompositeTypes\ActionType;
use Shm\ShmUtils\Response;

class AdminRPCRunAction
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::structure([
                    'payload' => Shm::structure([
                        'url' => Shm::string(),
                    ]),
                ]),
                'args' => [
                    'collection' => Shm::nonNull(Shm::string()),
                    'action' => Shm::nonNull(Shm::string()),
                    'args' => Shm::mixed(),
                ],

                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);

                    if (!$structure) {
                        ShmRPC::error("Данные не доступны");
                    }


                    $action = $structure->findItemByKey($args['action']);


                    if (!$action || $action->type !== 'action') {
                        ShmRPC::error("Действие не найдено");
                    }

                    if ($action instanceof ActionType) {

                        $payload =  $action->callResolve($root, $args);
                        return [
                            'payload' => $payload
                        ];
                    } else {
                        ShmRPC::error("Действие не найдено");
                    }
                }
            ];
        });
    }
}
