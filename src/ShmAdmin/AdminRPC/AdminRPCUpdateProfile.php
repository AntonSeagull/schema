<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAdmin\SchemaCollections\SubAccountsSchema;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;

class AdminRPCUpdateProfile
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::structure([
                    "_id" => Shm::ID(),
                    "*" => Shm::mixed(),
                ]),


                'args' => Shm::structure([

                    'values' => Shm::mixed(),

                ]),
                'resolve' => function ($root, $args) {



                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);


                    if (Auth::subAccountAuth()) {

                        $structure = SubAccountsSchema::baseStructure();
                    } else {

                        $structure = null;


                        foreach (AdminPanel::$authStructures as $user) {

                            if ($user->collection == Auth::getAuthCollection()) {
                                $structure = $user;
                                break;
                            }
                        }
                    }
                    if (!$structure) {
                        Response::validation("Ошибка доступа");
                    }



                    $root->setType($structure);



                    $values = $args['values'] ?? null;

                    if (!$values) {
                        Response::validation("Нет данных для обновления");
                    }

                    $values = $structure->normalize($values);
                    $values = $structure->removeOtherItems($values);

                    //remove _id
                    if (isset($values['_id'])) {
                        unset($values['_id']);
                    }




                    $structure->updateOne(
                        [
                            "_id" => Auth::subAccountAuth() ? Auth::getSubAccountID() : Auth::getAuthID()
                        ],
                        [
                            '$set' => $values
                        ]
                    );

                    return  $structure->findOne([
                        '_id' => Auth::subAccountAuth() ? Auth::getSubAccountID() : Auth::getAuthID()
                    ]);
                }

            ];
        });
    }
}
