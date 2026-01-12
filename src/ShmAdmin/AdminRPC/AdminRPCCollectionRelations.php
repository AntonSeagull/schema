<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmRPC\ShmRPC;

class AdminRPCCollectionRelations
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'args' => Shm::structure([
                    "collection" => Shm::nonNull(Shm::string()),
                ]),
                'type' => Shm::arrayOf(Shm::structure([
                    'key' => Shm::string(),
                    'icon' => Shm::string(),
                    'title' => Shm::string(),
                    'foreignCollection' => Shm::string(),
                    'foreignKey' => Shm::string(),
                ])),
                'resolve' => function ($root, $args) {

                    if (!isset($args['collection'])) {
                        return [];
                    }

                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);

                    if (!$structure) {
                        return [];
                    }

                    return [];
                }
            ];
        });
    }
}
