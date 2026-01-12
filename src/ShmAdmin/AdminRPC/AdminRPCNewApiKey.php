<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;

class AdminRPCNewApiKey
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::string(),
                'args' => [
                    'title' => Shm::string(),
                ],
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    $title = $args['title'] ?? null;

                    if (!$title) {
                        Response::validation("Не указано название ключа");
                    }

                    $apikey = Auth::genApiKey($title, Auth::getAuthCollection(), Auth::getAuthID());

                    if (!$apikey) {
                        Response::validation("Ошибка при создании ключа");
                    }

                    return   $apikey;
                }

            ];
        });
    }
}
