<?php

namespace Shm\ShmUtils;

use Shm\Shm;
use Shm\ShmCmd\Cmd;
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



        $structure->updateKeys();
        $structure->updatePath();

        $SearchPaths =   $structure->getSearchPaths();



        $items = $structure->find([
            "_needRecalculateSearch" => ['$ne' => false],
        ], [
            'limit' => 1,
        ]);



        foreach ($items as $item) {





            $values = [];







            foreach ($SearchPaths as $pathItem) {


                echo 'Processing search path item: ' . json_encode($pathItem) . PHP_EOL;
                echo 'Data item: ' . json_encode($item) . PHP_EOL;

                if (!isset($pathItem['path']) || !is_array($pathItem['path'])) {
                    throw new \Exception('Search path item must have a path key and it must be an array ' . json_encode($pathItem));
                }

                if (!is_array($pathItem)) {
                    throw new \Exception('Search path item must be an array ' . json_encode($pathItem));
                }


                $values[] =  DeepAccess::getByPathValues($item, $pathItem['path']);
            }




            $values = array_merge(...$values);


            echo 'Found ' . count($values) . ' values for item ' . $item->_id . ' in collection ' . $structure->collection . PHP_EOL;

            $values = array_map(function ($value) {
                return (string)$value;
            }, $values);
            $values = array_unique($values);
            $values = array_values($values);

            $search_string = implode(" ", $values);

            echo 'Updating search string for item ' . $item->_id . ' in collection ' . $structure->collection . PHP_EOL;
            echo 'Search string: ' . $search_string . PHP_EOL;

            $structure->_updateOne(
                [
                    "_id" => $item->_id,
                ],
                [
                    '$set' => [
                        "search_string" => $search_string,
                        "_needRecalculateSearch" => false,
                    ]
                ]
            );
        }
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


            exit;
        })->everyMinute();
    }
}
