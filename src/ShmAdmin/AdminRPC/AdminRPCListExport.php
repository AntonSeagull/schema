<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAdmin\SchemaCollections\ShmExportCollection;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;

class AdminRPCListExport
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::arrayOf(ShmExportCollection::structure()),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    return ShmExportCollection::find([], [
                        'sort' => ['_id' => -1],
                        'limit' => 3,
                    ]);
                }
            ];
        });
    }
}
