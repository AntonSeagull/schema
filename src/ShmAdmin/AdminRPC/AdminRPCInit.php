<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmRPC\ShmRPC;

class AdminRPCInit
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::structure([



                    'auth' => Shm::structure([
                        'emailLogin' => Shm::boolean(),
                        'phoneLogin' => Shm::boolean(),
                        'socialLogin' => Shm::boolean(),
                        'emailReg' => Shm::boolean(),
                        'phoneReg' => Shm::boolean(),
                        'socialReg' => Shm::boolean(),
                    ]),

                    'title' => Shm::string(),
                    'icon' => Shm::string(),
                    'cover' => Shm::string(),
                    'color' => Shm::string(),
                    'subtitle' => Shm::string(),
                    'terms' => Shm::string(),
                    'privacy' => Shm::string()




                ]),
                'resolve' => function ($root, $args) {


                    $initData = AdminPanel::fullSchema();




                    $emailLogin = false;
                    $phoneLogin = false;
                    $socialLogin = false;

                    $emailReg = false;
                    $phoneReg = false;
                    $socialReg = false;

                    foreach (AdminPanel::$authStructures as $user) {

                        if ($user->findItemByType(Shm::email())) {

                            $emailLogin = true;
                        }
                        if ($user->findItemByType(Shm::phone())) {
                            $phoneLogin = true;
                        }
                        if ($user->findItemByType(Shm::social())) {
                            $socialLogin = true;
                        }
                    }


                    foreach (AdminPanel::$regStructures as $user) {

                        if ($user->findItemByType(Shm::email())) {

                            $emailReg = true;
                        }
                        if ($user->findItemByType(Shm::phone())) {
                            $phoneReg = true;
                        }
                        if ($user->findItemByType(Shm::social())) {
                            $socialReg = true;
                        }
                    }



                    return [
                        'auth' => [

                            'emailLogin' => $emailLogin,
                            'phoneLogin' => $phoneLogin,
                            'socialLogin' => $socialLogin,
                            'emailReg' => $emailReg,
                            'phoneReg' => $phoneReg,
                            'socialReg' => $socialReg,
                        ],
                        'title' => $initData->title,
                        'icon' => $initData->assets['icon'] ?? null,
                        'cover' => $initData->assets['cover'] ?? null,
                        'color' => $initData->assets['color'] ?? null,
                        'subtitle' => $initData->assets['subtitle'] ?? null,
                        'terms' => $initData->assets['terms'] ?? null,
                        'privacy' => $initData->assets['privacy'] ?? null,
                    ];
                }

            ];
        });
    }
}

