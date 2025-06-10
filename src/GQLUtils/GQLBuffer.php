<?php

namespace Shm\GQLUtils;

use F3Mongo\mDB;

class GQLBuffer
{
    private static $ids = [];

    private static $results = [];
    private static $pipelines = [];



    public static function load($collection)
    {





        if (!empty(self::$results[$collection])) {
            return;
        }


        $filters = [];


        if (!isset(self::$ids[$collection]) || count(self::$ids[$collection]) == 0) {
            return;
        }

        $filters['_id'] = ['$in' => self::$ids[$collection]];


        $pipeline = [
            [
                '$match' => $filters
            ],
            ...self::$pipelines[$collection] ?? []
        ];


        $rows = mDB::collection($collection)->aggregate($pipeline);




        foreach ($rows as $row) {



            self::$results[$collection][(string) $row['_id']] = $row;
        }
    }

    public static function add(array $ids, string $collection, array $pipeline)
    {

        self::$pipelines[$collection] = $pipeline;

        self::$results[$collection] = [];


        if (!isset(self::$ids[$collection])) {
            self::$ids[$collection] = [];
        }

        $ids = array_map(function ($val) {
            return mDB::id($val);
        }, $ids);

        self::$ids[$collection] = array_merge(self::$ids[$collection], $ids);
    }

    public static function get($ids, string $collection)
    {


        if (is_array($ids) || $ids instanceof \MongoDB\Model\BSONArray) {


            $result = [];
            foreach ($ids as $id) {

                if ($id instanceof \MongoDB\Model\BSONDocument) {


                    $result[] = $id;
                } else if (isset(self::$results[$collection][(string) $id])) {
                    $result[] = self::$results[$collection][(string) $id];
                }
            }
        } else {
            $result = null;

            if ($ids instanceof \MongoDB\Model\BSONDocument) {
                $result = $ids;
            }

            if (isset(self::$results[$collection][(string) $ids])) {
                $result = self::$results[$collection][(string) $ids];
            }
        }



        return $result;
    }
}