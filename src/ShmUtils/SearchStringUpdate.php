<?php

namespace Shm\ShmUtils;

use Shm\Shm;
use Shm\ShmAdmin\SchemaCollections\ShmExportCollection;
use Shm\ShmCmd\Cmd;
use Shm\ShmDB\mDB;
use Shm\ShmTypes\StructureType;

class SearchStringUpdate
{


    private static function updateIndex(StructureType $structure)
    {




        if (!$structure->collection) {
            echo 'Collection is not set for ' . get_class($structure) . PHP_EOL;
            return;
        }


        echo 'Updating search index for ' . $structure->collection . PHP_EOL;


        /*

        //TODO: Переписать
        $structure->updateKeys();

        $SearchPaths =   $structure->getSearchPaths();


        $items = $structure->find([
            "_needRecalculateSearch" => ['$ne' => false],
        ], [
            'limit' => 10,
            'sort' => [
                'updated_at' => -1
            ]
        ]);


        $bulkUpdate = [];

        foreach ($items as $item) {





            $values = [];







            foreach ($SearchPaths as $pathItem) {


                if (!isset($pathItem['path']) || !is_array($pathItem['path'])) {
                    throw new \Exception('Search path item must have a path key and it must be an array ' . json_encode($pathItem));
                }

                if (!is_array($pathItem)) {
                    throw new \Exception('Search path item must be an array ' . json_encode($pathItem));
                }


                $values[] =  DeepAccess::getByPathValues($item, $pathItem['path']);
            }


            $values = array_merge(...$values);



            $values = array_map(function ($value) {
                return (string)$value;
            }, $values);
            $values = array_unique($values);
            $values = array_values($values);

            $search_string = implode(" ", $values);


            $bulkUpdate[] = [
                'updateOne' => [
                    [
                        "_id" => $item->_id
                    ],
                    [
                        '$set' => [
                            "search_string" => $search_string,
                            "_needRecalculateSearch" => false,
                        ]
                    ],
                ]
            ];
        }

        if (count($bulkUpdate) > 0) {
            echo 'Executing bulk update for ' . count($bulkUpdate) . ' items in collection ' . $structure->collection . PHP_EOL;
            mDB::_collection($structure->collection)->bulkWrite($bulkUpdate);
        }*/
    }

    public static function cmdInit()
    {





        Cmd::command("searchIndex", function () {


            $classes = [];

            if (is_dir(ShmInit::$rootDir . '/app/Collections')) {



                $files = scandir(ShmInit::$rootDir . '/app/Collections');
                foreach ($files as $file) {
                    if (!in_array($file, ['.', '..']) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                        $className = str_replace('.php', '', $file);
                        $fullClassName = 'App\\Collections\\' . $className;

                        $class = new $fullClassName();

                        $classes[] = $class;
                    }
                }


                foreach ($classes as $class) {

                    self::updateIndex($class::structure());
                }
            }
        })->everyMinute();
    }
}
