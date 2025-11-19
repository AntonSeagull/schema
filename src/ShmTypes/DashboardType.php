<?php

namespace Shm\ShmTypes;

use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\BaseType;
use Shm\ShmUtils\RedisCache;
use Shm\ShmUtils\RedisStorage;

class DashboardType extends StructureType
{
    public string $type = 'dashboard';



    public string $dashboardBlockType = "card";


    public function cardBlock(): static
    {

        $this->dashboardBlockType = "card";
        return $this;
    }

    public function lineChartBlock(): static
    {
        $this->dashboardBlockType = "lineChart";
        return $this;
    }

    public function pieChartBlock(): static
    {
        $this->dashboardBlockType = "pieChart";
        return $this;
    }

    public function barChartBlock(): static
    {
        $this->dashboardBlockType = "barChart";
        return $this;
    }

    /** @var null|callable */
    public $calculateFunction = null;


    public function calculate(callable $function): static
    {
        $this->calculateFunction = $function;
        $this->haveCalculateFunction = true;
        return $this;
    }

    public $haveCalculateFunction = false;

    public function getCalculateFunction(): ?callable
    {



        return $this->calculateFunction;
    }

    public function executeCalculateFunction(): mixed
    {
        if ($this->calculateFunction === null) {
            return null;
        }



        $cal = ($this->calculateFunction)();

        return $cal;
    }




    public function defaultSum(StructureType $structure, string $key, $pipeline = []): self
    {



        $this->calculateFunction = function () use ($structure, $key, $pipeline) {

            $sum =  $structure->aggregate([
                ...$pipeline,
                [
                    '$match' => [
                        '$or' => [
                            [
                                $key => ['$gte' => 0]
                            ],
                            [
                                $key => ['$lte' => 0]

                            ]
                        ]

                    ]
                ],
                [
                    '$group' => [
                        '_id' => null,
                        "sum" => [
                            '$sum' => '$' . $key,
                        ],


                    ],
                ],
            ])->toArray()[0]['sum'] ?? 0;





            return [
                [
                    "value" => round($sum)
                ]
            ];
        };
        return $this;
    }


    public function defaultCount(StructureType $structure, $pipeline = []): self
    {



        $this->calculateFunction = function () use ($structure,  $pipeline) {


            $count =   $structure->aggregate([
                ...$pipeline,
                [
                    '$group' => [
                        '_id' => null,
                        "count" => [
                            '$sum' => 1
                        ],


                    ],
                ],
            ])->toArray()[0]['count'] ?? 0;


            return [
                [
                    "value" => round($count)
                ]
            ];;
        };
        return $this;
    }


    public function defaultAvg(StructureType $structure, $key,  $pipeline = []): self
    {



        $this->calculateFunction = function () use ($structure, $key, $pipeline) {

            $avg =   $structure->aggregate([
                ...$pipeline,
                [
                    '$match' => [
                        '$or' => [
                            [
                                $key => ['$gte' => 0]
                            ],
                            [
                                $key => ['$lte' => 0]

                            ]
                        ]

                    ]
                ],
                [
                    '$group' => [
                        '_id' => null,
                        "avg" => [
                            '$avg' => '$' . $key,
                        ],


                    ],
                ],
            ])->toArray()[0]['avg'] ?? 0;

            return [
                [
                    "value" => round($avg)
                ]
            ];
        };
        return $this;
    }




    public function defaultCountBy(StructureType $structure, $byKey,  $pipeline = []): self
    {




        $this->calculateFunction = function () use ($structure, $byKey, $pipeline) {



            $byKeyExpectType = $structure->items[$byKey] ?? null;




            $currentPipeline = [];

            if ($byKeyExpectType && ($byKeyExpectType->type ?? null) == "IDs") {

                $currentPipeline[] = [
                    '$unwind' => '$' . $byKey
                ];
            }

            $currentPipeline[] = [
                '$match' => [
                    $byKey => ['$exists' => true]
                ]
            ];

            $currentPipeline[] = [
                '$group' => [
                    "_id" => [
                        $byKey => '$' . $byKey,
                    ],
                    "count" => [
                        '$sum' => 1,
                    ],

                ],
            ];

            $currentPipeline[] = [
                '$sort' => [
                    'count' => -1,
                ],
            ];


            /**
             * Если ID или IDs именю связанный объект, то
             * формируем названиея
             */
            //Если это вложенные данные
            if ($byKeyExpectType && ($byKeyExpectType instanceof IDsType || $byKeyExpectType instanceof IDType)) {


                $currentPipeline[] = [
                    '$lookup' => ['from' => $byKeyExpectType->document->collection, 'localField' => "_id." . $byKey, 'foreignField' => '_id', 'as' => 'label']
                ];
                $currentPipeline[] = [
                    '$addFields' => ['label' => ['$first' => '$label']]
                ];


                $display = $byKeyExpectType->display ?? "title name surname phone email title_ru title.ru";

                $displayItems = explode(" ", $display);

                $concatArray = [];
                foreach ($displayItems as $val) {
                    $concatArray[] = [
                        '$cond' => [
                            // Проверка: является ли значение строкой или числом
                            ['$in' => [['$type' => '$label.' . $val], ['string', 'int', 'long', 'double', 'decimal']]],
                            // Если да, то проверяем, является ли значение числом
                            ['$cond' => [
                                ['$in' => [['$type' => '$label.' . $val], ['int', 'long', 'double', 'decimal']]],
                                ['$toString' => '$label.' . $val], // Если это число, преобразуем его в строку
                                '$label.' . $val, // Иначе, используем значение как есть (должно быть строкой)
                            ]],
                            '', // Для всех остальных типов используем пустую строку
                        ],
                    ];
                    $concatArray[] = " "; // Добавляем пробелы между значениями полей
                }
                array_pop($concatArray); // Удаляем последний ненужный пробел

                $currentPipeline[] = [
                    '$addFields' => [
                        'label' => [
                            '$concat' => $concatArray,
                        ],
                    ],


                ];
            }




            $result = $structure->aggregate([...$pipeline, ...$currentPipeline]);





            $data = [];
            foreach ($result as $item) {


                $data[] = [
                    "label" => trim((string) ($item['label'] ?? $item['_id'][$byKey])),
                    "value" => $item['count'],
                ];
            }
            return $data;
        };

        return $this;
    }


    public function defaultCountByMonths(StructureType $structure, $byDateKey,  $pipeline = []): self
    {




        $this->calculateFunction = function () use ($structure, $byDateKey, $pipeline) {



            $byKeyExpectType = $structure->items[$byDateKey] ?? null;

            if (!$byKeyExpectType || !in_array($byKeyExpectType->type ?? null, ["unixdate", "unixdatetime"])) {
                return null;
            }

            $currentPipeline = [];



            $currentPipeline[] = [
                '$match' => [
                    $byDateKey => ['$gt' => strtotime('-6 months', strtotime(date('Y-m-01')))]
                ]
            ];

            $currentPipeline[] = [

                '$addFields' => [
                    'year' => [
                        '$year' => [
                            '$toDate' => [
                                '$multiply' => ['$' . $byDateKey, 1000],
                            ],
                        ],
                    ],
                    'month' => [
                        '$month' => [
                            '$toDate' => [
                                '$multiply' => ['$' . $byDateKey, 1000],
                            ],
                        ],
                    ],
                ],

            ];

            $currentPipeline[] = [
                '$group' => [
                    '_id' => [
                        'year' => '$year',
                        'month' => '$month',
                    ],
                    'count' => [
                        '$sum' => 1,
                    ],
                ],
            ];


            $currentPipeline[] = [
                '$sort' => [
                    '_id.year' => 1,
                    '_id.month' => 1,
                ],
            ];



            $result = $structure->aggregate([...$pipeline, ...$currentPipeline]);

            $monthsList = [
                "1" => "январь",
                "2" => "февраль",
                "3" => "март",
                "4" => "апрель",
                "5" => "май",
                "6" => "июнь",
                "7" => "июль",
                "8" => "август",
                "9" => "сентябрь",
                "10" => "октябрь",
                "11" => "ноябрь",
                "12" => "декабрь",
            ];


            $data = [];
            foreach ($result as $item) {
                if (isset($item->_id->year) && isset($item->_id->month)) {
                    $data[] = [
                        "label" => $monthsList[$item['_id']['month']] . ' ' . $item['_id']['year'],
                        "value" => $item['count'],
                    ];
                }
            }
            return $data;
        };

        return $this;
    }

    public function defaultCountByDays(StructureType $structure, $byDateKey,  $pipeline = []): self
    {


        $this->calculateFunction = function () use ($structure, $byDateKey, $pipeline) {



            $byKeyExpectType = $structure->items[$byDateKey] ?? null;

            if (!$byKeyExpectType || !in_array($byKeyExpectType->type ?? null, ["unixdate", "unixdatetime"])) {
                return null;
            }

            $currentPipeline = [];



            $currentPipeline[] = [
                '$match' => [
                    $byDateKey => ['$gt' => strtotime('-2 months', strtotime(date('Y-m-01')))]
                ]
            ];

            $currentPipeline[] = [

                '$addFields' => [
                    'dayOfYear' => [
                        '$dayOfYear' => [
                            '$toDate' => [
                                '$multiply' => ['$' . $byDateKey, 1000],
                            ],
                        ],
                    ],
                    'year' => [
                        '$year' => [
                            '$toDate' => [
                                '$multiply' => ['$' . $byDateKey, 1000],
                            ],
                        ],
                    ],
                    'month' => [
                        '$month' => [
                            '$toDate' => [
                                '$multiply' => ['$' . $byDateKey, 1000],
                            ],
                        ],
                    ],
                ],

            ];

            $currentPipeline[] = [
                '$group' => [
                    '_id' => [
                        'dayOfYear' => '$dayOfYear',
                        'year' => '$year',
                        'month' => '$month',
                    ],
                    $byDateKey => ['$first' => '$' . $byDateKey],
                    'count' => [
                        '$sum' => 1,
                    ],
                ],
            ];


            $currentPipeline[] = [
                '$sort' => [
                    '_id.year' => 1,
                    '_id.month' => 1,
                    '_id.dayOfYear' => 1,
                ],
            ];



            $result = $structure->aggregate([...$pipeline, ...$currentPipeline]);



            $data = [];
            foreach ($result as $item) {
                if (isset($item->_id->year) && isset($item->_id->month)) {
                    $data[] = [
                        "label" => date('d.m.Y', $item[$byDateKey]),
                        "value" => $item['count'],
                    ];
                }
            }
            return $data;
        };

        return $this;
    }



    public function defaultSumByMonths(StructureType $structure, $bySumKey, $byDateKey,  $pipeline = []): self
    {

        $this->calculateFunction = function () use ($structure, $bySumKey, $byDateKey, $pipeline) {



            $byKeyExpectType = $structure->items[$byDateKey] ?? null;


            if (!($byKeyExpectType instanceof UnixDateType || $byKeyExpectType instanceof UnixDateTimeType)) {
                return null;
            }



            $currentPipeline = [];



            $currentPipeline[] = [
                '$match' => [
                    $byDateKey => ['$gt' => strtotime('-6 months', strtotime(date('Y-m-01')))]
                ]
            ];

            $currentPipeline[] = [

                '$addFields' => [
                    'year' => [
                        '$year' => [
                            '$toDate' => [
                                '$multiply' => ['$' . $byDateKey, 1000],
                            ],
                        ],
                    ],
                    'month' => [
                        '$month' => [
                            '$toDate' => [
                                '$multiply' => ['$' . $byDateKey, 1000],
                            ],
                        ],
                    ],
                ],

            ];

            $currentPipeline[] = [

                '$group' => [
                    '_id' => [
                        'year' => '$year',
                        'month' => '$month',
                    ],
                    'sum' => [
                        '$sum' => '$' . $bySumKey,
                    ],
                ],

            ];


            $currentPipeline[] = [
                '$sort' => [
                    '_id.year' => 1,
                    '_id.month' => 1,
                ],
            ];


            $result = $structure->aggregate([...$pipeline, ...$currentPipeline]);

            $monthsList = [
                "1" => "январь",
                "2" => "февраль",
                "3" => "март",
                "4" => "апрель",
                "5" => "май",
                "6" => "июнь",
                "7" => "июль",
                "8" => "август",
                "9" => "сентябрь",
                "10" => "октябрь",
                "11" => "ноябрь",
                "12" => "декабрь",
            ];


            $data = [];
            foreach ($result as $item) {
                if (isset($item->_id->year) && isset($item->_id->month)) {
                    $data[] = [
                        "label" => $monthsList[$item['_id']['month']] . ' ' . $item['_id']['year'],
                        "value" => round($item['sum']),
                    ];
                }
            }
            return $data;
        };

        return $this;
    }
}