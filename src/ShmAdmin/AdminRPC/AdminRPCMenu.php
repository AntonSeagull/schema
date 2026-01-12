<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmRPC\ShmRPC;

class AdminRPCMenu
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            $menuItemType = Shm::structure([
                'label' => Shm::string(),
                'icon' => Shm::string(),
                'key' => Shm::string(),
                "collection" => Shm::string(),
                "single" => Shm::boolean(),
                "children" => Shm::arrayOf(
                    Shm::structure([
                        'label' => Shm::string(),
                        'icon' => Shm::string(),
                        'key' => Shm::string(),
                        "collection" => Shm::string(),

                        "single" => Shm::boolean(),
                    ])->staticBaseTypeName("MenuItemChild")
                ),
            ])->staticBaseTypeName("MenuItem");

            return [
                'type' => Shm::structure([
                    'menu' => Shm::arrayOf($menuItemType),
                    'allItems' => Shm::arrayOf($menuItemType),
                ]),
                'resolve' => function ($root, $args) {

                    //  Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    $initData = AdminPanel::fullSchema();

                    $menu = [];
                    $allItems = [];

                    foreach ($initData->items as $item) {
                        if ($item->type == 'adminGroup') {
                            $_item = [
                                'label' => $item->title,
                                'icon' => $item->assets['icon'] ?? null,
                                'key' => $item->key,
                                "single" => $item->single,
                                'children' => [],
                            ];


                            foreach ($item->items as $subItem) {

                                $allItems[] = [
                                    'label' => $subItem->title,
                                    'icon' => $subItem->assets['icon'] ?? null,
                                    'key' => $subItem->key,
                                    "single" => $item->single,
                                    "collection" => $subItem->collection ?? null,
                                ];

                                $_item['children'][] = [
                                    'label' => $subItem->title,
                                    'icon' => $subItem->assets['icon'] ?? null,
                                    'key' => $subItem->key,
                                    "single" => $item->single,
                                    "collection" => $subItem->collection ?? null,
                                ];
                            }

                            $menu[] = $_item;
                        } else {


                            $menu[] = [
                                'label' => $item->title,
                                'icon' => $item->assets['icon'] ?? null,
                                'key' => $item->key,
                                "single" => $item->single,
                                "collection" => $item->collection ?? null,
                            ];

                            $allItems[] = [
                                'label' => $item->title,
                                'icon' => $item->assets['icon'] ?? null,
                                'key' => $item->key,
                                "single" => $item->single,
                                "collection" => $item->collection ?? null,
                            ];
                        }
                    }

                    return [
                        'menu' => $menu,
                        'allItems' => $allItems,
                    ];
                }
            ];
        });
    }
}
