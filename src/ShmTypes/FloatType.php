<?php

namespace Shm\ShmTypes;


use Shm\CachedType\CachedInputObjectType;
use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;

class FloatType extends BaseType
{
    public string $type = 'float';

    public function __construct()
    {
        // Nothing extra for now
    }

    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if ($addDefaultValues &&  $value === null && $this->defaultIsSet) {
            return $this->default;
        }

        if (is_numeric($value)) {
            return  $value;
        }
        return null;
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_float($value) && !is_int($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be a float.");
        }
    }


    public function filterType($safeMode = false): ?BaseType
    {



        $itemTypeFilter =  Shm::structure([
            'gte' => Shm::float()->title('Больше')->col(8),
            'eq' => Shm::float()->title('Равно')->col(8),
            'lte' => Shm::float()->title('Меньше')->col(8),
        ])->staticBaseTypeName("FloatFilterType");

        return  $itemTypeFilter->editable()->inAdmin($this->inAdmin)->title($this->title);
    }





    public function filterToPipeline($filter, array | null  $absolutePath = null): ?array
    {



        $path  = $absolutePath ? implode('.', $absolutePath) . '.' . $this->key : $this->key;


        $match = [];

        if (isset($filter['gte'])) {
            $match['$gte'] = (float) $filter['gte'];
        }
        if (isset($filter['eq'])) {
            $match['$eq'] = (float) $filter['eq'];
        }
        if (isset($filter['lte'])) {
            $match['$lte'] = (float) $filter['lte'];
        }
        if (empty($match)) {
            return null;
        }
        return [
            [
                '$match' => [
                    $path => $match
                ]
            ]
        ];



        return null;
    }


    public function computedReport(StructureType | null $root = null, $path = [], $pipeline = [])
    {

        if (!$root) {

            new \Exception("Root structure is not set for EnumType report. Path: " . implode('.', $path));
        }

        if (!$this->report) {
            return null;
        }

        $key = implode('.', $path);


        $data =  $root->aggregate([
            ...$pipeline,
            [
                '$match' => [
                    $key => ['$exists' => true, '$ne' => 0]
                ]
            ],
            [
                '$group' => [
                    '_id' => null,
                    'max_created_at' => ['$max' => '$created_at'],
                    'min' => ['$min' => '$' . $key],
                    'max' => ['$max' => '$' . $key],
                    'sum' => ['$sum' => '$' . $key],
                    'avg' => ['$avg' => '$' . $key],
                    'count' => ['$sum' => 1],
                ]
            ],

        ])->toArray()[0] ?? null;


        $max = $data['max'] ?? 0;
        $min = $data['min'] ?? 0;





        /*   if ($max != $min) {
            // 2. Вычисляем количество шагов и размер бина
            $steps = 30;
            $range = $max - $min;
            $actualSteps = min($steps, max(1, ceil($range / 0.000001))); // чтобы избежать деления на 0
            $binSize = $range / $actualSteps;

            // 3. Округляем binSize до удобного (например, до 0.1, 0.5, 1 и т.д. — если нужно)

            // 4. Агрегация с биннингом
            $pipeline = [
                [

                    '$match' => [
                        $key => ['$gte' => $min, '$lte' => $max]
                    ]

                ],
                [
                    '$project' => [
                        'bin' => [
                            '$round' => [
                                [
                                    '$add' => [
                                        $min,
                                        [
                                            '$multiply' => [
                                                ['$floor' => [
                                                    '$divide' => [['$subtract' => ['$' . $key, $min]], $binSize]
                                                ]],
                                                $binSize
                                            ]
                                        ]
                                    ]
                                ],
                                2 // округление до целого
                            ]
                        ]
                    ]
                ],
                [
                    '$group' => [
                        '_id' => '$bin',
                        'name' => ['$first' => '$bin'],
                        'value' => ['$sum' => 1],
                    ]
                ],
                [
                    '$sort' => ['_id' => 1]
                ]
            ];

            $histogram = $root->aggregate($pipeline)->toArray();
        }
*/
        //  var_dump($histogram);
        //  exit;

        $avg = $data['avg'] ?? 0;
        $sum = $data['sum'] ?? 0;
        $count = $data['count'] ?? 0;


        /*     $dailyData = [];
        //====
        $max_created_at = $data['max_created_at'] ?? 0;
        if ($max_created_at) {
            $pipeline = [
                [
                    '$match' => [
                        $key => ['$exists' => true, '$ne' => 0],
                        //30 дней назад
                        'created_at' => ['$gte' => $max_created_at - 60 * 60 * 24 * 30]
                    ]
                ],
                [
                    '$project' => [
                        'day' => [
                            '$dateToString' => [
                                'format' => '%d.%m.%Y',
                                'date' => ['$toDate' => ['$multiply' => ['$created_at', 1000]]],
                                'timezone' => date_default_timezone_get()
                            ]
                        ],
                        'created_at' => 1,
                        $key => 1
                    ]
                ],
                [
                    '$group' => [
                        '_id' => '$day',
                        'day' => ['$first' => '$day'],
                        'sum' =>  ['$sum' => '$' . $key],
                        'avg' => ['$avg' => '$' . $key],
                        'count' => ['$sum' => 1],
                        'max' => ['$max' => '$' . $key],
                        'min' => ['$min' => '$' . $key],
                        'created_at' => ['$first' => '$created_at'],
                    ]
                ],
                [
                    '$addFields' => [
                        'sum' => ['$round' => ['$sum', 2]],
                        'avg' => ['$round' => ['$avg', 2]],
                        'count' => ['$round' => ['$count', 2]],
                        'max' => ['$round' => ['$max', 2]],
                        'min' => ['$round' => ['$min', 2]],
                    ],
                ],
                [
                    '$sort' => ['created_at' => 1]
                ]
            ];

            $dailyData = $collection->aggregate($pipeline);
        }
        return [
            'max' => round($max, 2),
            'min' => round($min, 2),
            'avg' => round($avg, 2),
            'sum' => round($sum, 2),
            'count' => $count,
            'histogram' => $histogram ?? [],
            'dailyData' => $dailyData,
        ];*/



        return [

            'type' => $this->type,

            'title' => $this->title,

            'main' => [
                [
                    'view' => 'cards',
                    'title' =>  $this->title . ' — за все время',
                    'result' => [
                        [
                            'name' => 'Макс.',
                            'value' => round($max, 2),
                        ],
                        [
                            'name' => 'Мин.',
                            'value' => round($min, 2),
                        ],
                        [
                            'name' => 'Ср.',
                            'value' => round($avg, 2),
                        ],
                        [
                            'name' => 'Сумма',
                            'value' => round($sum, 2),
                        ]
                    ]
                ]
            ],


        ];
    }




    public function tsType(): TSType
    {
        $TSType = new TSType('number');


        return $TSType;
    }

    public function getSearchPaths(): array
    {



        return [
            [
                'path' => $this->path,
            ]
        ];
    }
}