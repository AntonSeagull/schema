<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAdmin\SchemaCollections\ShmExportCollection;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Response;
use Shm\ShmUtils\ShmInit;

class AdminRPCMakeExport
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::bool(),
                'args' => Shm::structure([
                    'ids' => Shm::IDs(),
                    'title' => Shm::string(),
                    "collection" => Shm::nonNull(Shm::string()),
                    'filter' => Shm::mixed(),
                    'pipeline' => Shm::mixed(),

                ]),
                'resolve' => function ($root, $args) {



                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);


                    $currentExport = ShmExportCollection::findOne([
                        'type' => 'data',
                        'status' => ['$in' => ['pending', 'processing']]
                    ]);

                    if ($currentExport) {
                        Response::validation("У вас уже есть активный экспорт. Подождите пока он завершится.");
                    }


                    if (!isset($args['title']) || !$args['title']) {
                        Response::validation("Не указано название экспорта");
                    } else {
                        $args['title'] = trim($args['title']);
                    }


                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для экспорта");
                    }


                    $structure = AdminPanel::fullSchema()->findItemByCollection($args['collection']);




                    $structure->inTableThis(true);


                    if ($structure->single) {

                        Response::validation("Данные не доступны для экспорта");
                    }


                    if (!$structure) {
                        Response::validation("Данные не доступны для экспорта");
                    }


                    $rootType = $root->getType();
                    $rootType->items['data'] = Shm::arrayOf($structure);

                    $root->setType($rootType);

                    $pipeline = $structure->getPipeline();

                    if (isset($args['ids']) && count($args['ids']) > 0) {

                        $ids = array_map(function ($id) {
                            return mDB::id($id);
                        }, $args['ids']);

                        $pipeline = [
                            ...$pipeline,
                            [
                                '$match' => [
                                    '_id' => ['$in' => $ids]
                                ],
                            ],
                        ];
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

                    if (isset($args['pipeline'])) {
                        $pipeline = [
                            ...$pipeline,
                            ...$args['pipeline'],
                        ];
                    }



                    $timezone = date_default_timezone_get();

                    $fileName = 'export_' . $structure->collection . '_' . $timezone . '_' . date('d_m_Y_H_i') . '_' . md5($args['title']) . '.xlsx';


                    //Remove special chars from filename $fileName
                    $fileName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $fileName);

                    $filePathDir = ShmInit::$rootDir . '/storage/exports';
                    if (!file_exists($filePathDir)) {
                        mkdir($filePathDir, 0755, true);
                    }









                    ShmExportCollection::insertOne([
                        'type' => 'data',
                        'timezone' => $timezone,
                        'filePath' => $filePathDir . '/' . $fileName,
                        'fileName' => $fileName,
                        'title' => $args['title'],
                        'token' => Auth::$currentRequestToken,
                        'collection' => $structure->collection,
                        'pipeline' => $pipeline,
                        'status' => 'pending',
                    ]);




                    return true;
                }

            ];
        });
    }
}
