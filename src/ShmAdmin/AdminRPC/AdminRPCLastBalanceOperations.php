<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\CompositeTypes\BalanceTypes\BalanceUtils;
use Shm\ShmUtils\Inflect;

class AdminRPCLastBalanceOperations
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' =>  Shm::arrayOf(
                    BalanceUtils::balancePaymentsStructure()
                ),
                'resolve' => function ($root, $args) {
                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    $findStructure = AdminPanel::findCurrentAuthStructure();

                    if (!$findStructure) {
                        return null;
                    }

                    $collection = $findStructure->collection;
                    $key = Inflect::singularize($collection);

                    $filter = [
                        '$or' => [
                            [$key  => Auth::getAuthID()],
                            ['manager' => Auth::getAuthID()],
                        ],   // в платежи ты писал $user->_id

                        'deleted_at' => ['$exists' => false],
                    ];

                    // сортировка и лимит (последние по времени)
                    $options = [
                        'sort'   => ['created_at' => -1],
                        'limit'  => 100,
                    ];

                    $cursor = mDB::collection($collection . '_payments')->find($filter, $options);


                    return iterator_to_array($cursor, false);
                }
            ];
        });
    }
}
