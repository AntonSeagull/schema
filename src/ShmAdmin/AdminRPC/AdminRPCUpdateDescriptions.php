<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;

class AdminRPCUpdateDescriptions
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                "type" => Shm::structure([
                    '*' => Shm::string()
                ]),
                'args' => [
                    "descriptions" => Shm::nonNull(Shm::structure([
                        '*' => Shm::string()
                    ]))
                ],
                'resolve' => function ($root, $args) {
                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);



                    $fieldDescription = [];

                    //Добавляем новые значения
                    foreach ($args['descriptions'] as $key => $val) {

                        if ($key == "ownerCollection") {
                            continue;
                        }

                        if ($key == "_id") {
                            //Если это ID, то пропускаем
                            continue;
                        }

                        if ($key == "created_at" || $key == "updated_at") {
                            //Если это дата, то пропускаем
                            continue;
                        }


                        $fieldDescription[$key] = $val;
                    }


                    if (count($fieldDescription) > 0) {
                        mDB::collection("_adminDescriptions")->updateOne(
                            [
                                "ownerCollection" => Auth::getAuthCollection()
                            ],
                            [
                                '$set' => $fieldDescription
                            ],
                            [
                                'upsert' => true
                            ]
                        );
                    }


                    return $fieldDescription;
                }
            ];
        });
    }
}
