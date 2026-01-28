<?php

namespace Shm\ShmAdmin\AdminRPC\AdminRPCBI;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAdmin\AdminRPC\AdminRPCBI\BI\BIChart\BiChart;
use Shm\ShmAdmin\AdminRPC\AdminRPCBI\BI\BiSquare\BiSquare;
use Shm\ShmAdmin\SchemaCollections\ShmBIBoard;
use Shm\ShmAdmin\Types\BaseStructureType;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\StructureType;
use Shm\ShmTypes\UnixDateTimeType;
use Shm\ShmTypes\UnixDateType;

class AdminRPCBI
{




    public static function calculateBiBlockRpc()
    {
        return ShmRPC::lazy(function () {
            return [
                'type' => Shm::structure([
                    'values' => Shm::arrayOf(Shm::structure([
                        'key' => Shm::mixed(),
                        'value' => Shm::mixed(),
                    ])->staticBaseTypeName("BiBlockValueItem")),
                    'keyTransform' =>  ShmBIBoard::biBlockStructure()->findItemByKey('groupTransform'),
                ])->staticBaseTypeName("BiBlockValue"),
                'args' => ShmBIBoard::biBlockStructure(),
                'resolve' => function ($root, $args) {

                    $source = $args['source'];

                    if (!$source) {
                        return [
                            'values' => [],
                            'keyTransform' => null,
                        ];
                    }


                    $type = $args['type'];


                    if (!$type) {
                        return [
                            'values' => [],
                            'keyTransform' => null,
                        ];
                    }


                    if ($type == 'square') {
                        return BiSquare::rpc($args);
                    }

                    if ($type == "chart" || $type == "pie") {
                        return BiChart::rpc($args);
                    }

                    return [
                        'values' => [],
                        'keyTransform' => null,
                    ];
                }
            ];
        });
    }


    public static function updateBiBoardRpc()
    {
        return ShmRPC::lazy(function () {
            return [
                'type' => ShmBIBoard::structure(),
                'args' => [
                    '_id' => Shm::ID(),
                    'board' => ShmBIBoard::structure(),
                ],
                'resolve' => function ($root, $args) {

                    if (!isset($args['_id'])) {
                        return null;
                    }

                    $update =  ShmBIBoard::structure()->updateOne([
                        '_id' => $args['_id'],
                    ], [
                        '$set' => $args['board'],
                    ]);

                    return ShmBIBoard::structure()->findOne([
                        '_id' => $args['_id'],
                    ]);
                }
            ];
        });
    }

    public static function addBiBoardRpc()
    {
        return ShmRPC::lazy(function () {
            return [
                'type' => ShmBIBoard::structure(),
                'args' => ShmBIBoard::structure(),
                'resolve' => function ($root, $args) {


                    $insert =  ShmBIBoard::structure()->insertOne($args);

                    return ShmBIBoard::structure()->findOne([
                        '_id' => $insert->getInsertedId(),
                    ]);
                }
            ];
        });
    }

    public static function deleteBiBoardRpc()
    {
        return ShmRPC::lazy(function () {
            return [
                'type' => Shm::bool(),
                'args' => Shm::structure([
                    '_id' => Shm::ID(),
                ]),
                'resolve' => function ($root, $args) {

                    if (!isset($args['_id'])) {
                        return false;
                    }

                    ShmBIBoard::structure()->deleteOne([
                        '_id' => $args['_id'],
                    ]);

                    return true;
                }
            ];
        });
    }

    public static function getBiBoardsRpc()
    {
        return ShmRPC::lazy(function () {
            return [
                'type' => Shm::arrayOf(ShmBIBoard::structure()),
                'resolve' => function ($root, $args) {

                    return ShmBIBoard::structure()->find();
                }
            ];
        });
    }

    public static function allCollectionsRpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::arrayOf(BaseStructureType::get()),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);


                    $collections = AdminPanel::fullSchema()->getAllCollections();

                    foreach ($collections as $collection) {
                        $result[] = $collection->json();
                    }

                    return $result;
                }
            ];
        });
    }
}
