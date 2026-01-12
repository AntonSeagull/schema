<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;

class AdminRPCRunAction
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::structure([
                    'payload' => Shm::mixed(),
                ]),
                'args' => [
                    '_ids' => Shm::IDs(),
                    'collection' => Shm::nonNull(Shm::string()),
                    'action' => Shm::nonNull(Shm::string()),
                    '*' => Shm::mixed()
                ],

                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);

                    if (!$structure) {
                        Response::validation("Данные не доступны");
                    }

                    $buttonAction = $structure->findButtonAction($args['action']);

                    if (!$buttonAction) {
                        Response::validation("Действие не найдено");
                    }

                    $payload =  $buttonAction->computed($args);
                    return [
                        'payload' => $payload
                    ];
                }
            ];
        });
    }
}
