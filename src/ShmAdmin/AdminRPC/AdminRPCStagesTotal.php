<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\SupportTypes\StageType;
use Shm\ShmUtils\Response;

class AdminRPCStagesTotal
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::structure([

                    "*" => Shm::number(),

                ]),
                'args' => Shm::structure([

                    "collection" => Shm::nonNull(Shm::string()),
                    'search' => Shm::string()->default(''),
                    'filter' => Shm::mixed(),
                ]),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }



                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);




                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }


                    $pipeline = $structure->getPipeline();


                    $stages = $structure->getStages();

                    if (!$stages) {
                        return [];
                    }



                    if (isset($args['filter'])) {


                        $pipelineFilter =  $structure->filterToPipeline($args['filter']);


                        if ($pipelineFilter) {

                            $pipeline = [
                                ...$pipeline,
                                ...$pipelineFilter,
                            ];
                        }
                    };




                    if (isset($args['search'])) {

                        $pipeline[] = [
                            '$match' => [
                                'search_string' => ['$regex' => mb_strtolower(trim($args['search'])), '$options' => 'i'],
                            ],
                        ];
                    }



                    $facet = [];

                    foreach ($stages->items as $stage) {
                        if ($stage instanceof StageType) {
                            $facet[$stage->key] = [
                                ...$stage->getPipeline(),
                                [
                                    '$group' => [
                                        '_id' => null,
                                        'count' => ['$sum' => 1],
                                    ]
                                ]
                            ];
                        }
                    }

                    $stagesCounts = $structure->aggregate([
                        ...$pipeline,
                        ['$facet' => $facet]
                    ])->toArray()[0] ?? [];

                    $result = [];

                    foreach ($stages->items as $stage) {
                        $result[$stage->key] = $stagesCounts[$stage->key][0]['count'] ?? 0;
                    }

                    return $result;
                }

            ];
        });
    }
}
