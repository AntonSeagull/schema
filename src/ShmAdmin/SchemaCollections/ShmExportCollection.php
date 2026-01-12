<?php


namespace Shm\ShmAdmin\SchemaCollections;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Shm\Collection\Collection;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmBlueprints\FileUpload\ShmFileUploadUtils;
use Shm\ShmCmd\Cmd;
use Shm\ShmDB\mDB;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmInit;

class ShmExportCollection extends Collection
{


    public  $collection = '_shm_exports';




    public  function schema(): StructureType
    {
        $schema = Shm::structure([

            'type' => Shm::enum([
                'statement' => 'Ведомость расчетов',
                'data' => 'Экспорт данные',
            ]),
            'filePath' => Shm::string()->hide(),
            'fileName' => Shm::string(),
            'fileUrl' => Shm::string(),
            'timezone' => Shm::string(),
            'title' => Shm::string(),
            'currency' => Shm::string(),
            'collection' => Shm::string(),
            'pipeline' => Shm::mixed(),
            'progress' => Shm::number()->default(0),
            'error' => Shm::string(),
            'status' => Shm::enum([
                'pending' => 'В очереди',
                'processing' => 'В обработке',
                'done' => 'Выполнено',
                'error' => 'Ошибка',
            ]),



        ]);





        if (Auth::subAccountAuth()) {

            $schema->insertValues([
                'progress' => 0,
                'owner' =>  Auth::getSubAccountID(),
                'ownerCollection' =>  SubAccountsSchema::$collection,
                'isSubAccount' => true,



            ]);


            if (!Cmd::cli()) {
                $schema->pipeline([
                    [
                        '$match' => [

                            'owner' =>  Auth::getSubAccountID(),
                            'ownerCollection' =>  SubAccountsSchema::$collection,
                            'isSubAccount' => true,

                        ]
                    ]
                ]);
            }
        } else {


            $schema->insertValues(
                [
                    'progress' => 0,
                    'owner' =>  Auth::getAuthID(),
                    'ownerCollection' =>  Auth::getAuthCollection(),
                    'isSubAccount' => false,

                ]
            );
            if (!Cmd::cli()) {
                $schema->pipeline([
                    [
                        '$match' => [
                            'owner' =>  Auth::getAuthID(),
                            'ownerCollection' =>  Auth::getAuthCollection(),
                            'isSubAccount' => false,
                        ]
                    ]
                ]);
            }
        }








        return   $schema;
    }

    private static function  findCollection(string $collection): Collection | null
    {



        if (is_dir(ShmInit::$rootDir . '/app/Collections')) {

            $files = scandir(ShmInit::$rootDir . '/app/Collections');
            foreach ($files as $file) {
                if (!in_array($file, ['.', '..']) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $className = str_replace('.php', '', $file);
                    $fullClassName = 'App\\Collections\\' . $className;


                    $class = new $fullClassName();

                    if ($class instanceof Collection) {
                        if ($class->collection  === $collection) {
                            return $class;
                        }
                    }
                }
            }
        }
        return null;
    }

    private static function getTableRow(StructureType $schema, $values): array
    {
        $row = [];
        foreach ($schema->items as $key => $item) {
            if ($item->inAdmin) {

                if ($item->type == "structure") {

                    $subRow = self::getTableRow($item, $values[$key] ?? []);
                    $row = array_merge($row, $subRow);
                    continue;
                }

                $row[] = $item->exportRow($values[$key] ?? null);
            }
        }
        return $row;
    }

    private static function getTableHeader(StructureType $schema, $prefix = ''): array
    {
        $header = [];
        foreach ($schema->items as $key => $value) {
            if ($value->inAdmin) {

                if ($value->type == "structure") {

                    $subHeader = self::getTableHeader($value, $prefix . ($value->title ?? $key) . ' -> ');
                    $header = array_merge($header, $subHeader);
                    continue;
                }

                $header[] = $prefix . ($value->title ?? $key);
            }
        }
        return $header;
    }


    private static function exportStepData($activeExport)
    {
        echo '[' . date('Y-m-d H:i:s') . '] ===== Starting exportStepData =====' . PHP_EOL;
        echo '[' . date('Y-m-d H:i:s') . '] Export ID: ' . $activeExport->_id . PHP_EOL;
        echo '[' . date('Y-m-d H:i:s') . '] Current status: ' . ($activeExport->status ?? 'N/A') . PHP_EOL;

        if ($activeExport->status !== 'processing') {
            echo '[' . date('Y-m-d H:i:s') . '] Updating status to "processing"...' . PHP_EOL;
            ShmExportCollection::updateOne([
                '_id' => $activeExport->_id
            ], [
                '$set' => [
                    'status' => 'processing',
                ]
            ]);
            echo '[' . date('Y-m-d H:i:s') . '] Status updated to "processing"' . PHP_EOL;
        }

        $timezone = $activeExport->timezone ?? 'UTC';
        echo '[' . date('Y-m-d H:i:s') . '] Setting timezone: ' . $timezone . PHP_EOL;
        date_default_timezone_set($timezone);

        $pipeline =  ($activeExport->pipeline ?? []);
        $pipeline = mDB::bsonDocumentToArray($pipeline);
        $skip = $activeExport->skip ?? 0;
        echo '[' . date('Y-m-d H:i:s') . '] Pipeline loaded, skip: ' . $skip . PHP_EOL;

        echo '[' . date('Y-m-d H:i:s') . '] Setting manual auth token...' . PHP_EOL;
        Auth::setManualToken($activeExport->token);

        echo '[' . date('Y-m-d H:i:s') . '] Finding collection: ' . ($activeExport->collection ?? 'N/A') . PHP_EOL;
        $collection = ShmExportCollection::findCollection($activeExport->collection);
        $total = $activeExport->total ?? 0;
        echo '[' . date('Y-m-d H:i:s') . '] Collection found: ' . ($collection ? 'YES' : 'NO') . ', Current total: ' . $total . PHP_EOL;

        if ($total == 0) {
            echo '[' . date('Y-m-d H:i:s') . '] Total is 0, counting documents in collection...' . PHP_EOL;
            $total = $collection::aggregate(
                [
                    ...$pipeline,
                    ['$count' => 'count']
                ]
            )->toArray()[0]['count'] ?? 0;
            echo '[' . date('Y-m-d H:i:s') . '] Documents count result: ' . $total . PHP_EOL;

            if ($total == 0) {
                echo '[' . date('Y-m-d H:i:s') . '] ERROR: No documents found for export' . PHP_EOL;
                ShmExportCollection::updateOne([
                    '_id' => $activeExport->_id
                ], [
                    '$set' => [
                        'status' => 'error',
                        'progress' => 100,
                        'error' => "Не найдено записей для экспорта",
                    ]
                ]);
                echo '[' . date('Y-m-d H:i:s') . '] Export marked as error in database' . PHP_EOL;
                return;
            } else {
                echo '[' . date('Y-m-d H:i:s') . '] Updating total in database: ' . $total . PHP_EOL;
                ShmExportCollection::updateOne([
                    '_id' => $activeExport->_id
                ], [
                    '$set' => [
                        'total' => $total,
                    ]
                ]);
                echo '[' . date('Y-m-d H:i:s') . '] Total updated successfully' . PHP_EOL;
            }
        }



        if (!$collection) {
            echo '[' . date('Y-m-d H:i:s') . '] ERROR: Collection not found: ' . $activeExport->collection . PHP_EOL;
            ShmExportCollection::updateOne([
                '_id' => $activeExport->_id
            ], [
                '$set' => [
                    'status' => 'error',
                    'error' => "Ошибка доступа к данным"
                ]
            ]);
            echo '[' . date('Y-m-d H:i:s') . '] Export marked as error in database' . PHP_EOL;
            return;
        }

        $filePath = $activeExport->filePath;
        $fileName = $activeExport->fileName;
        echo '[' . date('Y-m-d H:i:s') . '] File path: ' . $filePath . PHP_EOL;
        echo '[' . date('Y-m-d H:i:s') . '] File name: ' . $fileName . PHP_EOL;

        echo '[' . date('Y-m-d H:i:s') . '] Getting collection schema...' . PHP_EOL;
        $schema = $collection->schema();
        $schemaType = $schema instanceof StructureType ? 'StructureType' : ($schema ? get_class($schema) : 'null');
        echo '[' . date('Y-m-d H:i:s') . '] Schema obtained: ' . $schemaType . PHP_EOL;


        if ($schema instanceof StructureType) {

            if (!file_exists($filePath)) {
                echo '[' . date('Y-m-d H:i:s') . '] Creating new export file: ' . $filePath . PHP_EOL;

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                // Добавляем заголовки, если файла нет
                $colIndex = 1;

                echo '[' . date('Y-m-d H:i:s') . '] Generating table headers from schema...' . PHP_EOL;
                $header = self::getTableHeader($schema);
                echo '[' . date('Y-m-d H:i:s') . '] Headers count: ' . count($header) . PHP_EOL;
                foreach ($header as $key => $value) {
                    $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
                    $sheet->setCellValue([$colIndex, 1], $value);
                    $colIndex++;
                }
                echo '[' . date('Y-m-d H:i:s') . '] Headers written to spreadsheet' . PHP_EOL;

                // Сохраняем файл
                echo '[' . date('Y-m-d H:i:s') . '] Saving spreadsheet to file...' . PHP_EOL;
                $writer = new Xlsx($spreadsheet);
                $writer->save($filePath);
                echo '[' . date('Y-m-d H:i:s') . '] Export file created successfully: ' . $filePath . PHP_EOL;
            } else {
                echo '[' . date('Y-m-d H:i:s') . '] Export file already exists: ' . $filePath . PHP_EOL;
            }

            if ($skip) {
                echo '[' . date('Y-m-d H:i:s') . '] Resuming export from skip: ' . $skip . PHP_EOL;
                $pipeline[] = ['$skip' => $skip];
            }
            $pipeline[] = ['$limit' => 10];

            echo '[' . date('Y-m-d H:i:s') . '] Running aggregation pipeline on collection: ' . $collection->collection . PHP_EOL;
            echo '[' . date('Y-m-d H:i:s') . '] Pipeline: ' . json_encode($pipeline, JSON_UNESCAPED_UNICODE) . PHP_EOL;

            echo '[' . date('Y-m-d H:i:s') . '] Executing aggregation...' . PHP_EOL;
            $result = $collection::aggregate(
                $pipeline
            )->toArray();
            echo '[' . date('Y-m-d H:i:s') . '] Aggregation completed. Results count: ' . count($result) . PHP_EOL;


            if (count($result) === 0) {
                echo '[' . date('Y-m-d H:i:s') . '] No more results, finalizing export...' . PHP_EOL;
                echo '[' . date('Y-m-d H:i:s') . '] Uploading file to S3...' . PHP_EOL;
                $fileUrl = ShmFileUploadUtils::saveToS3($filePath, $fileName, "exports");
                echo '[' . date('Y-m-d H:i:s') . '] File uploaded to S3: ' . $fileUrl . PHP_EOL;

                //Удаляем локальный файл
                if (file_exists($filePath)) {
                    echo '[' . date('Y-m-d H:i:s') . '] Deleting local file: ' . $filePath . PHP_EOL;
                    unlink($filePath);
                    echo '[' . date('Y-m-d H:i:s') . '] Local file deleted' . PHP_EOL;
                }

                echo '[' . date('Y-m-d H:i:s') . '] Updating export status to "done"...' . PHP_EOL;
                ShmExportCollection::updateOne([
                    '_id' => $activeExport->_id
                ], [
                    '$set' => [
                        'fileUrl' => $fileUrl,
                        'status' => 'done',
                        'progress' => 100,
                    ]
                ]);
                echo '[' . date('Y-m-d H:i:s') . '] Export completed successfully: ' . $filePath . PHP_EOL;
                echo '[' . date('Y-m-d H:i:s') . '] ===== exportStepData completed =====' . PHP_EOL;
                return;
            }

            echo '[' . date('Y-m-d H:i:s') . '] Loading existing spreadsheet...' . PHP_EOL;
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $currentRow = $sheet->getHighestRow();
            echo '[' . date('Y-m-d H:i:s') . '] Spreadsheet loaded. Current rows: ' . $currentRow . PHP_EOL;

            echo '[' . date('Y-m-d H:i:s') . '] Processing ' . count($result) . ' items...' . PHP_EOL;
            $processedCount = 0;
            foreach ($result as $item) {
                $colIndex = 1;
                $rowIndex =  $sheet->getHighestRow() + 1;

                $row = self::getTableRow($schema, $item);

                foreach ($row as $cellValue) {

                    if (is_array($cellValue)) {

                        $cellValue = implode(', ', $cellValue);
                    }


                    $sheet->setCellValue([$colIndex, $rowIndex], $cellValue);
                    $colIndex++;
                }

                $skip++;
                $processedCount++;
            }
            echo '[' . date('Y-m-d H:i:s') . '] Processed ' . $processedCount . ' items. Total skip: ' . $skip . PHP_EOL;

            echo '[' . date('Y-m-d H:i:s') . '] Saving spreadsheet...' . PHP_EOL;
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);
            echo '[' . date('Y-m-d H:i:s') . '] Spreadsheet saved' . PHP_EOL;

            $totalCount = $total;
            $progress = floor(($skip / $totalCount) * 100);
            if ($progress >= 100) {
                $progress = 100;
            }
            echo '[' . date('Y-m-d H:i:s') . '] Progress: ' . $progress . '% (' . $skip . '/' . $totalCount . ')' . PHP_EOL;

            echo '[' . date('Y-m-d H:i:s') . '] Updating export progress in database...' . PHP_EOL;
            ShmExportCollection::updateOne([
                '_id' => $activeExport->_id
            ], [
                '$set' => [
                    'skip' => $skip,
                    'progress' => $progress,
                ]
            ]);
            echo '[' . date('Y-m-d H:i:s') . '] Progress updated in database' . PHP_EOL;
            echo '[' . date('Y-m-d H:i:s') . '] ===== exportStepData iteration completed =====' . PHP_EOL;
        }
    }

    public static function exportStep()
    {
        echo '[' . date('Y-m-d H:i:s') . '] Starting export step check...' . PHP_EOL;

        $activeExport = ShmExportCollection::findOne([
            'type' => 'data',
            'status' => ['$in' => ['pending', 'processing']]
        ], [
            'sort' => ['_id' => 1]
        ]);

        if ($activeExport) {
            echo '[' . date('Y-m-d H:i:s') . '] Found data export: ID=' . $activeExport->_id . ', Collection=' . ($activeExport->collection ?? 'N/A') . ', Status=' . ($activeExport->status ?? 'N/A') . PHP_EOL;

            try {
                self::exportStepData($activeExport);
            } catch (\Exception $e) {
                echo '[' . date('Y-m-d H:i:s') . '] ERROR in data export ID=' . $activeExport->_id . ': ' . $e->getMessage() . PHP_EOL;
                echo '[' . date('Y-m-d H:i:s') . '] Stack trace: ' . $e->getTraceAsString() . PHP_EOL;
                ShmExportCollection::updateOne([
                    '_id' => $activeExport->_id
                ], [
                    '$set' => [
                        'status' => 'error',
                        'error' => "Ошибка экспортa данных",
                        "_exception" => $e->getMessage(),
                    ]
                ]);
                echo '[' . date('Y-m-d H:i:s') . '] Export error saved to database' . PHP_EOL;
            }
        } else {
            echo '[' . date('Y-m-d H:i:s') . '] No pending/processing data exports found' . PHP_EOL;
        }

        $activeExport = ShmExportCollection::findOne([
            'type' => 'statement',
            'status' => ['$in' => ['pending']]
        ], [
            'sort' => ['_id' => 1]
        ]);

        if ($activeExport) {
            echo '[' . date('Y-m-d H:i:s') . '] Found statement export: ID=' . $activeExport->_id . ', Status=' . ($activeExport->status ?? 'N/A') . PHP_EOL;

            try {

                self::exportStepStatement($activeExport);
            } catch (\Exception $e) {
                echo '[' . date('Y-m-d H:i:s') . '] ERROR in statement export ID=' . $activeExport->_id . ': ' . $e->getMessage() . PHP_EOL;
                echo '[' . date('Y-m-d H:i:s') . '] Stack trace: ' . $e->getTraceAsString() . PHP_EOL;
                ShmExportCollection::updateOne([
                    '_id' => $activeExport->_id
                ], [
                    '$set' => [
                        'status' => 'error',
                        'error' => "Ошибка экспортa данных",
                        "_exception" => $e->getMessage(),
                    ]
                ]);
                echo '[' . date('Y-m-d H:i:s') . '] Export error saved to database' . PHP_EOL;
            }
        } else {
            echo '[' . date('Y-m-d H:i:s') . '] No pending statement exports found' . PHP_EOL;
        }

        echo '[' . date('Y-m-d H:i:s') . '] Export step check completed' . PHP_EOL;
    }


    private static function exportStepStatement($activeExport)
    {
        echo '[' . date('Y-m-d H:i:s') . '] ===== Starting exportStepStatement =====' . PHP_EOL;
        echo '[' . date('Y-m-d H:i:s') . '] Export ID: ' . $activeExport->_id . PHP_EOL;
        echo '[' . date('Y-m-d H:i:s') . '] Current status: ' . ($activeExport->status ?? 'N/A') . PHP_EOL;

        if ($activeExport->status !== 'processing') {
            echo '[' . date('Y-m-d H:i:s') . '] Updating status to "processing"...' . PHP_EOL;
            ShmExportCollection::updateOne([
                '_id' => $activeExport->_id
            ], [
                '$set' => [
                    'status' => 'processing',
                ]
            ]);
            echo '[' . date('Y-m-d H:i:s') . '] Status updated to "processing"' . PHP_EOL;
        }

        $timezone = $activeExport->timezone ?? 'UTC';
        echo '[' . date('Y-m-d H:i:s') . '] Setting timezone: ' . $timezone . PHP_EOL;
        date_default_timezone_set($timezone);

        $pipeline =  ($activeExport->pipeline ?? []);
        $pipeline = mDB::bsonDocumentToArray($pipeline);
        $filePath = $activeExport->filePath;
        $fileName = $activeExport->fileName;
        echo '[' . date('Y-m-d H:i:s') . '] Pipeline loaded' . PHP_EOL;
        echo '[' . date('Y-m-d H:i:s') . '] File path: ' . $filePath . PHP_EOL;
        echo '[' . date('Y-m-d H:i:s') . '] File name: ' . $fileName . PHP_EOL;

        echo '[' . date('Y-m-d H:i:s') . '] Setting manual auth token...' . PHP_EOL;
        Auth::setManualToken($activeExport->token);





        $paymentsCollection = Auth::getAuthCollection() . '_payments';
        echo '[' . date('Y-m-d H:i:s') . '] Running aggregation on payments collection: ' . $paymentsCollection . PHP_EOL;
        echo '[' . date('Y-m-d H:i:s') . '] Pipeline: ' . json_encode($pipeline, JSON_UNESCAPED_UNICODE) . PHP_EOL;

        echo '[' . date('Y-m-d H:i:s') . '] Executing aggregation...' . PHP_EOL;
        $result = mDB::collection($paymentsCollection)->aggregate([
            ...$pipeline,

        ])->toArray();
        echo '[' . date('Y-m-d H:i:s') . '] Aggregation completed. Results count: ' . count($result) . PHP_EOL;




        $start_date = null;
        $end_date = null;


        $fields = [
            'Дата транзакции' => 'Дата проведения операции по начислению или списанию', // Пример: '03.01.2024'
            'Назначение платежа' => 'Описание основания или цели операции', // Пример: 'Погашение задолженности за период с 01.01.2024 по 02.01.2024'
            'Сумма поступления' => 'Сумма, начисленная на счет клиента', // Пример: '+10 рублей'
            'Сумма списания' => 'Сумма, списанная со счета клиента', // Пример: '-1 рубль'
            'Начальный баланс' => 'Баланс на счете до проведения операции', // Пример: '0 рублей'
            'Конечный баланс' => 'Баланс на счете после проведения операции', // Пример: '9 рублей'
            'Период покрытия задолжности' => 'Период, за который произведена оплата (как задолженность, так и аванс)', // Пример: '01.01.2024 - 02.01.2024'
            'Период покрытия авансовый' => 'Период, за который произведена оплата (как задолженность, так и аванс)', // Пример: '01.01.2024 - 02.01.2024'
        ];


        $head = [];
        foreach ($fields as $key => $value) {
            $head[] = $key;
        }


        echo '[' . date('Y-m-d H:i:s') . '] Processing payment data...' . PHP_EOL;
        $exportData = [];
        $balance = 0;
        $balanceBefore = 0;
        $processedPayments = 0;
        foreach ($result as $index => $item) {
            $processedPayments++;
            $balance += $item->amount;

            if ($start_date == null) {
                $start_date = date("d.m.Y", $item->created_at);
            }
            $end_date = date("d.m.Y", $item->created_at);


            //Поступление
            $earn = $item->amount > 0 ? abs($item->amount) : 0;
            //Списание
            $spend = $item->amount < 0 ? abs($item->amount) : 0;

            $periodDebt = "";
            $periodAdvance = "";


            if ($earn > 0) {

                if ($balanceBefore < 0) {

                    $val = $earn - abs($balanceBefore);
                    if ($val > 0) {




                        $periodDebt = "Погашение задолженности: " . abs($balanceBefore);
                        $periodAdvance = "Авансовый платеж: " . $val;
                    } else {
                        $periodDebt = "Погашение задолженности: " . $earn;
                    }
                } else {
                    $periodAdvance = "Авансовый платеж: " . $earn;
                }
            }




            $exportData[] = [
                //   $item->_id ?? "",
                date("d.m.Y", $item->created_at),
                $item->description ?? "",
                $earn,
                $spend,
                $balanceBefore,
                $balance,
                $periodDebt,
                $periodAdvance,

            ];

            $balanceBefore = $balance;
        }
        echo '[' . date('Y-m-d H:i:s') . '] Processed ' . $processedPayments . ' payments. Final balance: ' . $balance . PHP_EOL;




        if (count($exportData) > 10000) {
            echo '[' . date('Y-m-d H:i:s') . '] WARNING: Export data exceeds 10000 records, keeping only last 10000' . PHP_EOL;
            //Оставляем в exportData только 10000 записей с конца
            $exportData = array_slice($exportData, -10000);
            $title = 'Ведомость расчетов';
        } else {
            $title = 'Ведомость расчетов (' . $start_date . ' - ' . $end_date . ')';
        }
        echo '[' . date('Y-m-d H:i:s') . '] Export title: ' . $title . PHP_EOL;
        $fileName = $title . ' ' . md5(time()) . '.xlsx';
        echo '[' . date('Y-m-d H:i:s') . '] Generated file name: ' . $fileName . PHP_EOL;



        $col = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ', 'BA', 'BB', 'BC', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BK', 'BL', 'BM', 'BN', 'BO', 'BP', 'BQ', 'BR', 'BS', 'BT', 'BU', 'BV'];

        echo '[' . date('Y-m-d H:i:s') . '] Creating spreadsheet...' . PHP_EOL;
        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getDefaultColumnDimension()->setWidth(24);
        echo '[' . date('Y-m-d H:i:s') . '] Spreadsheet created, setting column width to 24' . PHP_EOL;




        $_index = 4;

        $len = $exportData[0] ? count($exportData[0]) - 1 : 4;

        // Объединяем 4 строки (например, ячейки A1 до D4)
        $sheet->mergeCells('A1:' . $col[$len] . '4');

        // Устанавливаем текст в объединенные ячейки
        $sheet->setCellValue($col[0] . '1', $title);

        // Настраиваем стиль для текста
        $sheet->getStyle('A1')->getFont()->setSize(18); // Устанавливаем размер шрифта 18
        $sheet->getStyle('A1')->getFont()->setBold(true); // Устанавливаем жирный шрифт

        // Выравнивание по центру горизонтально и вертикально
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $months = [
            '01' => 'Январь',
            '02' => 'Февраль',
            '03' => 'Март',
            '04' => 'Апрель',
            '05' => 'Май',
            '06' => 'Июнь',
            '07' => 'Июль',
            '08' => 'Август',
            '09' => 'Сентябрь',
            '10' => 'Октябрь',
            '11' => 'Ноябрь',
            '12' => 'Декабрь'
        ];


        //Добавляем шапку
        echo '[' . date('Y-m-d H:i:s') . '] Adding table headers...' . PHP_EOL;
        foreach ($head as $index => $val) {
            $sheet->setCellValue($col[$index] . (1 +  $_index), $val);
        }
        $_index++;
        echo '[' . date('Y-m-d H:i:s') . '] Headers added. Header count: ' . count($head) . PHP_EOL;



        echo '[' . date('Y-m-d H:i:s') . '] Writing export data to spreadsheet...' . PHP_EOL;
        $beforeDate = "";
        $rowsWritten = 0;
        foreach ($exportData as $index => $vals) {

            //Если  $beforeDate равне "" или месяц изменился
            //то добавляем горизонтальную линию в таблицу с названием месяца
            if ($beforeDate == "" || date("m", strtotime($beforeDate)) != date("m", strtotime($vals[0]))) {
                $sheet->mergeCells('A' . ($index + 1 +  $_index) . ':' . $col[$len] . ($index + 1 + $_index));

                $month = $months[date("m", strtotime($vals[0]))];
                $year = date("Y", strtotime($vals[0]));

                $sheet->setCellValue('A' . ($index + 1 +  $_index), $month . " " . $year);
                $sheet->getStyle('A' . ($index + 1 +  $_index))->getFont()->setBold(true);
                $sheet->getStyle('A' . ($index + 1 +  $_index))->getFont()->setSize(14);
                $_index++;
            }


            foreach ($vals as $indexVal => $val) {
                $cel = $col[$indexVal] . ($index + 1 +  $_index);
                $sheet->setCellValue($cel, $val);
                $sheet->getStyle($cel)->getAlignment()->setWrapText(true);
            }
            $beforeDate = $vals[0];
            $rowsWritten++;
        }
        echo '[' . date('Y-m-d H:i:s') . '] Written ' . $rowsWritten . ' rows to spreadsheet' . PHP_EOL;




        echo '[' . date('Y-m-d H:i:s') . '] Saving spreadsheet to file...' . PHP_EOL;
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
        echo '[' . date('Y-m-d H:i:s') . '] Spreadsheet saved to: ' . $filePath . PHP_EOL;

        echo '[' . date('Y-m-d H:i:s') . '] Uploading file to S3...' . PHP_EOL;
        $fileUrl = ShmFileUploadUtils::saveToS3($filePath, $fileName, "exports");
        echo '[' . date('Y-m-d H:i:s') . '] File uploaded to S3: ' . $fileUrl . PHP_EOL;

        //Удаляем локальный файл
        if (file_exists($filePath)) {
            echo '[' . date('Y-m-d H:i:s') . '] Deleting local file: ' . $filePath . PHP_EOL;
            unlink($filePath);
            echo '[' . date('Y-m-d H:i:s') . '] Local file deleted' . PHP_EOL;
        }

        echo '[' . date('Y-m-d H:i:s') . '] Updating export status to "done"...' . PHP_EOL;
        ShmExportCollection::updateOne([
            '_id' => $activeExport->_id
        ], [
            '$set' => [
                'fileUrl' => $fileUrl,
                'status' => 'done',
                'progress' => 100,
            ]
        ]);
        echo '[' . date('Y-m-d H:i:s') . '] Export completed successfully: ' . $filePath . PHP_EOL;
        echo '[' . date('Y-m-d H:i:s') . '] ===== exportStepStatement completed =====' . PHP_EOL;
    }
}
