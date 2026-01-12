<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAdmin\SchemaCollections\ShmExportCollection;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;

class AdminRPCDeleteExport
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::bool(),
                'args' => Shm::structure([
                    "_id" => Shm::ID()->default(null),
                ]),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    $_id = $args['_id'] ?? null;

                    if (!$_id) {
                        Response::validation("Нет данных для удаления");
                    }

                    $export = ShmExportCollection::findOne([
                        '_id' => mDB::id($_id)
                    ]);
                    if (!$export) {
                        Response::validation("Экспорт не найден");
                    }
                    ShmExportCollection::structure()->deleteOne([
                        '_id' => mDB::id($_id)
                    ]);

                    if (file_exists($export['filePath'])) {
                        unlink($export['filePath']);
                    }

                    return true;
                }
            ];
        });
    }
}
