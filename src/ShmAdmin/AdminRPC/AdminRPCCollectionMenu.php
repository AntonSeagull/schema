<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\MaterialIcons;

class AdminRPCCollectionMenu
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::structure([
                    'menu' => Shm::arrayOf(Shm::structure([
                        'key' => Shm::string(),
                        'icon' => Shm::string(),
                        'title' => Shm::string(),
                        'group' => Shm::string(),
                        'children' => Shm::arrayOf(Shm::structure([
                            'key' => Shm::string(),
                            'icon' => Shm::string(),
                            'title' => Shm::string(),
                            'group' => Shm::string(),
                        ])),
                    ])),
                    'allItems' => Shm::arrayOf(Shm::structure([
                        'key' => Shm::string(),
                        'icon' => Shm::string(),
                        'title' => Shm::string(),
                        'group' => Shm::string(),
                    ])),
                ]),
                'args' => Shm::structure([
                    "collection" => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {

                    if (!isset($args['collection'])) {
                        return [];
                    }

                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);


                    if (!$structure) {
                        return [];
                    }



                    $groups = [];

                    $allItems = [];

                    foreach ($structure->items as $item) {


                        if ($item->inAdmin) {

                            $group = [
                                'key' => $item->group['key'] ?? null,
                                'group' => $item->group['key'] ?? null,
                                'icon' => $item->group['icon'] ?? MaterialIcons::FolderTableOutline(),
                                'title' => $item->group['title'] ?? null,
                            ];
                            if ($item->group['key'] == 'default') {
                                $group['title'] = "Общее";
                            }


                            $groups[$group['key']] = $group;
                        }
                    }

                    $allItems = array_values($groups);



                    if ($structure->buttonActions) {

                        //   foreach ($structure->buttonActions?->items as $buttonAction) {

                        //      var_dump($buttonAction);
                        //      exit;
                        //  }
                    }




                    return [

                        'menu' => [[
                            'key' => 'content',
                            'title' => 'Контент',
                            'children' => array_values($groups),
                        ]],
                        'allItems' => $allItems,
                    ];
                }
            ];
        });
    }
}
