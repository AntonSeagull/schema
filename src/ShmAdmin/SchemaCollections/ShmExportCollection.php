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
            'fileName' => Shm::string(),
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
                    'owner' =>  Auth::getAuthOwner(),
                    'ownerCollection' =>  Auth::getAuthCollection(),
                    'isSubAccount' => false,

                ]
            );
            if (!Cmd::cli()) {
                $schema->pipeline([
                    [
                        '$match' => [
                            'owner' =>  Auth::getAuthOwner(),
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

        if ($activeExport->status !== 'processing') {
            ShmExportCollection::updateOne([
                '_id' => $activeExport->_id
            ], [
                '$set' => [
                    'status' => 'processing',
                ]
            ]);
        }

        echo 'Processing export ID: ' . $activeExport->_id . PHP_EOL;

        $timezone = $activeExport->timezone ?? 'UTC';
        date_default_timezone_set($timezone);

        $pipeline =  ($activeExport->pipeline ?? []);
        $pipeline = mDB::bsonDocumentToArray($pipeline);
        $skip = $activeExport->skip ?? 0;

        Auth::setManualToken($activeExport->token);

        $collection = ShmExportCollection::findCollection($activeExport->collection);
        $total = $activeExport->total ?? 0;

        if ($total == 0) {

            $total = $collection::aggregate(
                [
                    ...$pipeline,
                    ['$count' => 'count']
                ]
            )->toArray()[0]['count'] ?? 0;

            if ($total == 0) {

                ShmExportCollection::updateOne([
                    '_id' => $activeExport->_id
                ], [
                    '$set' => [
                        'status' => 'error',
                        'progress' => 100,
                        'error' => "Не найдено записей для экспорта",
                    ]
                ]);

                echo 'No documents found for export in collection: ' . $activeExport->collection . PHP_EOL;
                return;
            } else {
                ShmExportCollection::updateOne([
                    '_id' => $activeExport->_id
                ], [
                    '$set' => [
                        'total' => $total,
                    ]
                ]);
            }
        }



        if (!$collection) {
            ShmExportCollection::updateOne([
                '_id' => $activeExport->_id
            ], [
                '$set' => [
                    'status' => 'error',
                    'error' => "Ошибка доступа к данным"
                ]
            ]);

            echo 'Collection not found: ' . $activeExport->collection . PHP_EOL;

            return;
        }

        $filePath = $activeExport->filePath;
        $fileName = $activeExport->fileName;

        $schema = $collection->schema();
        $schema->expand();

        if ($schema instanceof StructureType) {



            if (!file_exists($filePath)) {

                echo 'Creating new export file: ' . $filePath . PHP_EOL;

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                // Добавляем заголовки, если файла нет
                $colIndex = 1;

                $header = self::getTableHeader($schema);
                foreach ($header as $key => $value) {
                    $sheet->getColumnDimensionByColumn($colIndex)->setAutoSize(true);
                    $sheet->setCellValue([$colIndex, 1], $value);
                    $colIndex++;
                }


                // Сохраняем файл
                $writer = new Xlsx($spreadsheet);
                $writer->save($filePath);
                echo 'Export file created: ' . $filePath . PHP_EOL;
            }

            if ($skip) {
                echo 'Resuming export from skip: ' . $skip . PHP_EOL;
                $pipeline[] = ['$skip' => $skip];
            }
            $pipeline[] = ['$limit' => 10];


            echo 'Running aggregation pipeline on collection: ' . $collection->collection . PHP_EOL;
            echo 'Pipeline: ' . json_encode($pipeline) . PHP_EOL;

            $result = $collection::aggregate(
                $pipeline
            )->toArray();


            if (count($result) === 0) {

                $fileUrl = ShmFileUploadUtils::saveToS3($filePath, $fileName, "exports");
                //Удаляем локальный файл
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                ShmExportCollection::updateOne([
                    '_id' => $activeExport->_id
                ], [
                    '$set' => [
                        'fileUrl' => $fileUrl,
                        'status' => 'done',
                        'progress' => 100,
                    ]
                ]);

                echo 'Export completed: ' . $filePath . PHP_EOL;
                return;
            }

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            foreach ($result as $item) {
                $colIndex = 1;
                $rowIndex =  $sheet->getHighestRow() + 1;

                $row = self::getTableRow($schema, $item);


                foreach ($row as $cellValue) {
                    $sheet->setCellValue([$colIndex, $rowIndex], $cellValue);
                    $colIndex++;
                }

                $skip++;
            }


            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);
            $totalCount = $total;
            $progress = floor(($skip / $totalCount) * 100);
            if ($progress >= 100) {
                $progress = 100;
            }
            ShmExportCollection::updateOne([
                '_id' => $activeExport->_id
            ], [
                '$set' => [
                    'skip' => $skip,
                    'progress' => $progress,
                ]
            ]);
        }
    }

    public static function exportStep()
    {

        $activeExport = ShmExportCollection::findOne([
            'type' => 'data',
            'status' => ['$in' => ['pending', 'processing']]
        ], [
            'sort' => ['_id' => 1]
        ]);

        if ($activeExport) {

            try {
                self::exportStepData($activeExport);
            } catch (\Exception $e) {
                ShmExportCollection::updateOne([
                    '_id' => $activeExport->_id
                ], [
                    '$set' => [
                        'status' => 'error',
                        'error' => "Ошибка экспортa данных",
                        "_exception" => $e->getMessage(),
                    ]
                ]);
                echo 'Export error: ' . $e->getMessage() . PHP_EOL;
            }
        }

        $activeExport = ShmExportCollection::findOne([
            'type' => 'statement',
            'status' => ['$in' => ['pending']]
        ], [
            'sort' => ['_id' => 1]
        ]);

        if ($activeExport) {

            try {

                self::exportStepStatement($activeExport);
            } catch (\Exception $e) {
                ShmExportCollection::updateOne([
                    '_id' => $activeExport->_id
                ], [
                    '$set' => [
                        'status' => 'error',
                        'error' => "Ошибка экспортa данных",
                        "_exception" => $e->getMessage(),
                    ]
                ]);
                echo 'Export error: ' . $e->getMessage() . PHP_EOL;
            }
        }
    }


    private static function exportStepStatement($activeExport)
    {


        if ($activeExport->status !== 'processing') {
            ShmExportCollection::updateOne([
                '_id' => $activeExport->_id
            ], [
                '$set' => [
                    'status' => 'processing',
                ]
            ]);
        }

        echo 'Processing export ID: ' . $activeExport->_id . PHP_EOL;

        $timezone = $activeExport->timezone ?? 'UTC';
        date_default_timezone_set($timezone);

        $pipeline =  ($activeExport->pipeline ?? []);
        $pipeline = mDB::bsonDocumentToArray($pipeline);
        $filePath = $activeExport->filePath;
        $fileName = $activeExport->fileName;

        Auth::setManualToken($activeExport->token);





        $result = mDB::collection(Auth::getAuthCollection() . '_payments')->aggregate([
            ...$pipeline,

        ])->toArray();




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


        $exportData = [];
        $balance = 0;
        $balanceBefore = 0;
        foreach ($result as $index => $item) {
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




        if (count($exportData) > 10000) {
            //Оставляем в exportData только 10000 записей с конца

            $exportData = array_slice($exportData, -10000);

            $title = 'Ведомость расчетов';
        } else {


            $title = 'Ведомость расчетов (' . $start_date . ' - ' . $end_date . ')';
        }
        $fileName = $title . ' ' . md5(time()) . '.xlsx';



        $col = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG', 'AH', 'AI', 'AJ', 'AK', 'AL', 'AM', 'AN', 'AO', 'AP', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AV', 'AW', 'AX', 'AY', 'AZ', 'BA', 'BB', 'BC', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BK', 'BL', 'BM', 'BN', 'BO', 'BP', 'BQ', 'BR', 'BS', 'BT', 'BU', 'BV'];

        $spreadsheet = new Spreadsheet();

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->getDefaultColumnDimension()->setWidth(24);




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
        foreach ($head as $index => $val) {
            $sheet->setCellValue($col[$index] . (1 +  $_index), $val);
        }
        $_index++;



        $beforeDate = "";
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
        }




        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);


        $fileUrl = ShmFileUploadUtils::saveToS3($filePath, $fileName, "exports");
        //Удаляем локальный файл
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        ShmExportCollection::updateOne([
            '_id' => $activeExport->_id
        ], [
            '$set' => [
                'fileUrl' => $fileUrl,
                'status' => 'done',
                'progress' => 100,
            ]
        ]);

        echo 'Export completed: ' . $filePath . PHP_EOL;
    }
}
