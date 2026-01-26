<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAdmin\Types\BaseStructureType;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\StructureType;

class AdminRPCAnalytics
{

    private static function blockType(): StructureType
    {

        return Shm::structure([
            'type' => Shm::enum([
                "chart",
                "pie",
                "list",
                "square",
                "funnel",
                "abc",
                "xyz"
            ]),
            'title' => Shm::string(),
            'color' => Shm::color(),
            'collection' => Shm::string(),
            "filter" => Shm::mixed(),
            'chartData' => Shm::structure([
                "view" => Shm::enum([
                    "line",
                    "bar",
                    "area"
                ]),
                "xField" => Shm::string(),
                "yField" => Shm::string(),
                "yFieldMethod" => Shm::enum([
                    "countRoot",
                    "count",
                    "sum",
                    "avg",
                    "min",
                    "max"
                ]),
                "yFieldDateStep" => Shm::enum([
                    "hour",
                    "day",
                    "week",
                    "month",
                    "year"
                ]),
                "yFieldDateFrom" => Shm::enum([
                    "lastWeek",
                    "lastMonth",
                    "last3Months",
                    "last6Months"
                ]),


            ]),
            "pieData" => Shm::structure([
                "field" => Shm::string(),

            ]),




        ]);
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
