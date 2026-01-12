<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;

class AdminRPCDescriptions
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                "type" => Shm::structure([
                    '*' => Shm::string()
                ]),
                'resolve' => function ($root, $args) {


                    $fieldDescription = mDB::collection("_adminDescriptions")->findOne([
                        "ownerCollection" => Auth::getAuthCollection()
                    ]);


                    return $fieldDescription;
                }
            ];
        });
    }
}
