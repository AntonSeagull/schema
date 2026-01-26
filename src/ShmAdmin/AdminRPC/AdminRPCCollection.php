<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAdmin\Types\BaseStructureType;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\CompositeTypes\ActionType;
use Shm\ShmTypes\IDsType;
use Shm\ShmTypes\IDType;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\MaterialIcons;
use Shm\ShmUtils\ShmInit;

class AdminRPCCollection
{

    public static function getDescriptions($collection)
    {

        $file = ShmInit::$rootDir . '/config/admin_descriptions/' . $collection . '.json';
        if (!file_exists($file)) {
            return [];
        }

        return json_decode(file_get_contents($file), true);
    }

    public static function getCollectionMenu($structure)
    {



        $groups = [];

        $groupStack = [];

        $allItems = [];

        foreach ($structure->items as $item) {

            if ($item instanceof ActionType) {

                if ($item->actionPosition != 'inline') {

                    continue;
                }
            }

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


                if (!in_array($group['key'], $groupStack)) {
                    $groupStack[] = $group['key'];
                }

                $groups[$group['key']] = $group;
            }
        }



        $children = [];

        if (in_array('default', $groupStack)) {
            $children[] = $groups['default'];
        }


        foreach ($groupStack as $groupKey) {

            if ($groupKey == 'default') {
                continue;
            }

            $children[] = $groups[$groupKey];
        }


        return [

            'menu' => [[
                'key' => 'content',
                'title' => 'Контент',
                'children' => $children,
            ]],
            'allItems' => $children,
        ];
    }

    public static function getRelations($collection)
    {


        $fields = AdminPanel::fullSchema()->findIDTypeByCollection($collection);

        $relations = [];
        foreach ($fields as $field) {

            if ($field instanceof IDsType || $field instanceof IDType) {

                $rootParent = $field->getRootParent();
                if ($rootParent instanceof StructureType) {

                    if ($rootParent?->collection == $collection) {
                        continue;
                    }

                    $relations[] = [
                        'collection' => $rootParent?->collection,
                        'key' => $field->getPathString(),

                        'icon' => $rootParent->assets['icon'] ?? null,
                        'title' => $rootParent->title,
                        'subtitle' => $field->title,
                    ];
                }
            }
        }
        return $relations;
    }

    public static function rpc()
    {

        return ShmRPC::lazy(function () {


            $relationType = Shm::structure([
                'icon' => Shm::string(),
                'title' => Shm::string(),
                'subtitle' => Shm::string(),
                'collection' => Shm::string(),
                'key' => Shm::string(),
            ])->staticBaseTypeName("CollectionRelation");

            $type = Shm::structure([
                '*' => Shm::structure([
                    'descriptionEN' => Shm::string(),
                    'descriptionRU' => Shm::string(),
                    'titleEN' => Shm::string(),
                    'titleRU' => Shm::string(),
                    'values' => Shm::structure([
                        '*' => Shm::structure([
                            'titleRU' => Shm::string(),
                            'titleEN' => Shm::string(),
                            'descriptionEN' => Shm::string(),
                            'descriptionRU' => Shm::string(),
                        ])
                    ]),
                    'items' => Shm::selfRef(function () use (&$type) {
                        return $type;
                    })
                ])->staticBaseTypeName("FieldDescriptionItem")

            ])->staticBaseTypeName("FieldDescription");


            $typeCollectionMenu =   Shm::structure([
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
            ]);

            return [
                'type' => Shm::structure([
                    'structure' => BaseStructureType::get(),
                    'descriptions' => $type,
                    'relations' => Shm::arrayOf($relationType),
                    'collectionMenu' => $typeCollectionMenu,
                ]),
                'args' => Shm::structure([
                    "collection" => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {

                    //   Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    if (!isset($args['collection'])) {
                        return [
                            'structure' => null,
                            'descriptions' => null,
                            'relations' => null,
                        ];
                    }

                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);


                    if (!$structure) {
                        return [
                            'structure' => null,
                            'descriptions' => null,
                            'relations' => null,
                        ];
                    }

                    return [
                        'structure' => $structure->json(),
                        'descriptions' => self::getDescriptions($structure->collection),
                        'relations' => self::getRelations($structure->collection),
                        'collectionMenu' => self::getCollectionMenu($structure),
                    ];
                }

            ];
        });
    }
}
