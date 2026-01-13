<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAdmin\Types\BaseStructureType;
use Shm\ShmRPC\ShmRPC;

class AdminRPCCollection
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => BaseStructureType::get(),
                'args' => Shm::structure([
                    "collection" => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {

                    //   Auth::authenticateOrThrow(...AdminPanel::$authStructures);

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

                    return $structure->json();
                }

            ];
        });
    }
}
