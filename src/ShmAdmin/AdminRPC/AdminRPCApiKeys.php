<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;

class AdminRPCApiKeys
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::arrayOf(Shm::structure([
                    "_id" => Shm::ID(),
                    'apikey' => Shm::string(),
                    "title" => Shm::string(),
                    'last_used' => Shm::int(),
                    "created_at" => Shm::int(),
                ])),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    $apikeys = mDB::collection(Auth::$apikey_collection)->find(
                        [
                            'owner' => Auth::getAuthID(),
                        ],
                        [
                            'projection' => [
                                '_id' => 1,
                                'apikey' => 1,
                                'last_used' => 1,
                                'title' => 1,
                                'created_at' => 1,
                            ]
                        ]
                    )->toArray();

                    $result = [];

                    foreach ($apikeys as $apikey) {


                        $result[] = [
                            '_id' => (string)$apikey['_id'],
                            //От APIKEY остаавляем только первые 10 символов
                            'apikey' =>  substr($apikey['apikey'] ?? '', 0, 10) . '...',
                            'title' => $apikey['title'] ?? '',
                            'last_used' => $apikey['last_used'] ?? 0,
                            'created_at' => $apikey['created_at'] ?? 0,
                        ];
                    }

                    return $result;
                }
            ];
        });
    }
}
