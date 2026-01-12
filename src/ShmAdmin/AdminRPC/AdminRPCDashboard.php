<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\DashboardType;
use Shm\ShmUtils\RedisCache;
use Shm\ShmUtils\Response;

class AdminRPCDashboard
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::arrayOf(Shm::structure([
                    'label' => Shm::mixed(),
                    'value' => Shm::mixed(),
                ])),
                'args' => [
                    'dashboardKey' => Shm::nonNull(Shm::string()),
                    'dashboardField' => Shm::nonNull(Shm::string()),
                ],
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    $dashboardKey = $args['dashboardKey'];
                    $dashboardField = $args['dashboardField'];

                    $structure = AdminPanel::fullSchema()->deepFindItemByKey($dashboardKey);


                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }

                    $dashboardItem = $structure->findItemByKey($dashboardField);

                    if (!$dashboardItem || $dashboardItem->type != 'dashboard') {
                        Response::validation("Данные не доступны для просмотра");
                    }

                    if ($dashboardItem instanceof DashboardType) {



                        $cacheKey = md5($dashboardKey . ' ' . $dashboardField . ' ' . Auth::getAuthID() . ' ' . Auth::getSubAccountID());

                        $cache = RedisCache::get($cacheKey);
                        if ($cache) {
                            return json_decode($cache, true);
                        }




                        $result = $dashboardItem->executeCalculateFunction();
                        if ($result) {
                            RedisCache::set($cacheKey, json_encode($result), 60);
                        }
                        return $result;
                    } else {
                        Response::validation("Данные не доступны для просмотра");
                    }
                }
            ];
        });
    }
}
