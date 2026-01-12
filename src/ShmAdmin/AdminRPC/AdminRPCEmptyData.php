<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;

class AdminRPCEmptyData
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
                    'clone' => Shm::ID()->default(null),
                    "collection" => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    if (!isset($args['collection'])) {
                        return [
                            "_" => null
                        ];
                    }

                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);


                    if (!$structure) {
                        return [
                            "_" => null
                        ];
                    }


                    $root->setType($structure);




                    $clone = $args['clone'] ?? null;
                    if ($clone) {
                        $cloneData = $structure->findOne([
                            '_id' => mDB::id($clone)
                        ]);

                        if ($cloneData) {

                            $cloneData = $structure->removeOtherItems($cloneData);


                            $cloneData = $structure->removeValuesByCriteria(function ($_this) {
                                return  !$_this->editable;
                            }, $cloneData);

                            $cloneData = $structure->normalize($cloneData, true);



                            return $cloneData;
                        }
                    }



                    return $structure->normalize([], true);
                }

            ];
        });
    }
}
