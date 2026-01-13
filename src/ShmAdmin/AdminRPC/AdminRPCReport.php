<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAdmin\Types\BaseStructureType;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;

class AdminRPCReport
{
    private static function reportResultType()
    {


        $viewType = Shm::enum([
            'treemap' => 'Древовидная карта',
            'bar' => 'Гистограмма',
            'cards' => 'Карточки',
            'pie' => 'Круговая диаграмма',
            'heatmap' => 'Тепловая карта',
            'horizontalBar' => 'Горизонтальная гистограмма',
        ]);

        $reportItem = Shm::structure([
            'view' =>  $viewType,

            'title' => Shm::string(),
            'structure' => BaseStructureType::get(),
            'heatmap' => Shm::structure([
                'xAxis' => Shm::arrayOf(Shm::string()),
                'yAxis' => Shm::arrayOf(Shm::string()),
                'data' => Shm::arrayOf(Shm::arrayOf(Shm::float())),
            ])->staticBaseTypeName("HeatmapData"),
            'result' => Shm::arrayOf(Shm::structure([
                'value' => Shm::mixed(),
                'item' => Shm::structure([
                    '_id' => Shm::ID(),
                    "*" => Shm::mixed(),
                ]),
                'name' => Shm::string(),

            ]))

        ])->staticBaseTypeName("ReportItem");

        return Shm::arrayOf(
            Shm::structure(
                [

                    'type' => Shm::string(),
                    'title' => Shm::string(),
                    'main' => Shm::arrayOf($reportItem),
                    'extra' => Shm::arrayOf($reportItem)
                ]
            )->staticBaseTypeName("ReportResult")
        );
    }

    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => self::reportResultType(),

                'args' => [
                    "collection" => Shm::nonNull(Shm::string()),
                    'filter' => Shm::mixed(),
                ],
                'resolve' => function ($root, $args) {

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }



                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);



                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }

                    $pipelineFilter = [];
                    if (isset($args['filter'])) {


                        $pipelineFilter =  $structure->filterToPipeline($args['filter']);
                    };



                    return  $structure->computedReport(null, [], $pipelineFilter);
                }

            ];
        });
    }
}
