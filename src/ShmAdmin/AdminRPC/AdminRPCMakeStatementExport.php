<?php

namespace Shm\ShmAdmin\AdminRPC;

use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAdmin\SchemaCollections\ShmExportCollection;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Inflect;
use Shm\ShmUtils\Response;
use Shm\ShmUtils\ShmInit;

class AdminRPCMakeStatementExport
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                'type' => Shm::bool(),
                'args' => Shm::structure([
                    'currency' => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {



                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);


                    $currentExport = ShmExportCollection::findOne([
                        'type' => 'statement',
                        'status' => ['$in' => ['pending', 'processing']]
                    ]);

                    if ($currentExport) {
                        Response::validation("У вас уже есть активный экспорт ведомости расчетов. Подождите пока он завершится.");
                    }


                    $timezone = date_default_timezone_get();

                    $fileName = 'export_payment_report_' . $timezone . '_' . date('d_m_Y_H_i') . '_' . $args['currency'] . '.xlsx';


                    //Remove special chars from filename $fileName
                    $fileName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $fileName);

                    $filePathDir = ShmInit::$rootDir . '/storage/exports';
                    if (!file_exists($filePathDir)) {
                        mkdir($filePathDir, 0755, true);
                    }


                    $key = Inflect::singularize(Auth::getAuthCollection());

                    $pipeline = [
                        ['$match' =>  [
                            'currency' => $args['currency'],
                            '$or' => [
                                ['manager' => Auth::getAuthID()],
                                [$key => Auth::getAuthID()],
                            ]
                        ]],
                        ['$sort' => ["created_at" => 1]],
                        ['$limit' => 10000]
                    ];

                    ShmExportCollection::insertOne([
                        'type' => 'statement',
                        'timezone' => $timezone,
                        'filePath' => $filePathDir . '/' . $fileName,
                        'fileName' => $fileName,
                        'title' => 'Ведомость расчетов в ' . $args['currency'] . ' на ' . date('d.m.Y H:i'),
                        'token' => Auth::$currentRequestToken,
                        'pipeline' => $pipeline,
                        'status' => 'pending',
                    ]);




                    return true;
                }

            ];
        });
    }
}
