<?php

namespace Shm\ShmBlueprints;

use InvalidArgumentException;
use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmTypes\ArrayOfType;
use Shm\ShmTypes\StructureType;
use Shm\ShmTypes\UnixDateTimeType;
use Shm\ShmTypes\UnixDateType;

class ShmBlueprintQuery
{


    private StructureType $structure;





    public $filter = true;

    public $sort = true;





    public $withoutData = false;


    public function __construct(StructureType $structure)
    {
        $this->structure = $structure;
    }

    /**
     * @var callable|null
     */
    public $beforeQuery = null;

    /**
     * @var callable|null
     */
    public $afterQuery = null;

    /**
     * @var callable|null
     */


    private $pipelineFunction = null;

    /**
     * Set the pipeline for database operations
     * 
     * @param array|callable|null $pipeline MongoDB aggregation pipeline or function returning pipeline
     * @throws InvalidArgumentException If pipeline is neither array nor callable
     */
    public function pipeline(array|callable|null $pipeline): static
    {
        if ($pipeline === null) {
            $this->pipelineFunction = null;
            return $this;
        }

        if (is_array($pipeline)) {
            // Convert array to function for consistency
            $this->pipelineFunction = fn() => $pipeline;
        } elseif (is_callable($pipeline)) {
            $this->pipelineFunction = $pipeline;
        } else {
            throw new InvalidArgumentException('Pipeline должен быть массивом или функцией');
        }

        return $this;
    }

    /**
     * Get the validated pipeline for database operations
     * 
     * @return array MongoDB aggregation pipeline
     */
    public function getPipeline(): array
    {
        if ($this->pipelineFunction === null) {
            return [];
        }

        $pipeline = ($this->pipelineFunction)();

        if (empty($pipeline)) {
            return [];
        }

        // Validate the pipeline structure
        mDB::validatePipeline($pipeline);

        return $pipeline;
    }


    /**
     * Set whether to enable filtering
     */
    public function filter(bool $filter = true): static
    {
        $this->filter = $filter;
        return $this;
    }

    /**
     * Set whether to enable sorting
     */
    public function sort(bool $sort = true): static
    {
        $this->sort = $sort;
        return $this;
    }



    /**
     * Set a callback to be executed before the query
     *
     * @param callable $beforeQuery Callback function that receives query arguments and can modify them
     * @return static
     */
    public function before(callable $beforeQuery): static
    {
        $this->beforeQuery = $beforeQuery;
        return $this;
    }

    /**
     * Set a callback to be executed after the query
     *
     * @param callable $afterQuery Callback function that receives query result and can modify it
     * @return static
     */
    public function after(callable $afterQuery): static
    {
        $this->afterQuery = $afterQuery;
        return $this;
    }



    /**
     * Set whether to return data without the actual content
     */
    public function withoutData(bool $withoutData = true): static
    {
        $this->withoutData = $withoutData;
        return $this;
    }

    public function make()
    {




        if ($this->withoutData) {
            $dataType = Shm::arrayOf($this->structure);
        } else {

            $dataType = Shm::structure([
                'data' => Shm::arrayOf($this->structure),
                'limit' => Shm::int(),
                'dateDistinct' => Shm::arrayOf(Shm::structure([
                    'format' => Shm::string(),
                    'count' => Shm::int(),
                    'from' => Shm::int(),
                    'to' => Shm::int()
                ])->staticBaseTypeName("DateDistinct")),
                'offset' => Shm::int(),
                'hash' => Shm::string(),
                'total' => Shm::int(),
            ])->key($this->structure->key . 'Data');
        }

        $dateDistinctKeys = [];

        foreach ($this->structure->items as $key => $item) {

            if (!$item->hide && ($item instanceof UnixDateType || $item instanceof UnixDateTimeType)) {
                $dateDistinctKeys[] = $item->key;
            }
        }




        $args = [

            "_id" => Shm::ID()->default(null),


            'onlyHash' => $this->withoutData ? null : Shm::boolean()->default(false),

            'limit' => Shm::int()->default(30),
            'sample' => Shm::boolean(),
            'dateDistinct' => count($dateDistinctKeys) > 0 ? Shm::enum($dateDistinctKeys) : null,
            'offset' =>  Shm::int()->default(0),
            'all' => Shm::boolean()->default(false),
            'search' => Shm::string()->default(''),
            'sort' => Shm::structure([
                'direction' => Shm::enum([
                    'ASC' => 'По возрастанию',
                    'DESC' => 'По убыванию',
                ])->default('DESC'),
                'field' => Shm::string(),
            ])
        ];


        $filter = $this->structure->filterType()->fullCleanDefault();

        if ($filter) {
            $args['filter'] = $filter;
        }


        $argsStructure = Shm::structure($args);
        $withoutData = $this->withoutData;
        $structure = $this->structure;



        $_this = $this;


        $result = [
            'type' => $dataType,
            'args' =>  $argsStructure,
            'resolve' => function ($root, $args) use ($_this, $structure, $withoutData, $argsStructure) {



                $pipeline = $_this->getPipeline();



                $onlyHash = $args['onlyHash'] ?? false;

                $args =  $argsStructure->normalize($args, true);


                if (!$structure->collection) {
                    throw new \Exception("Collection not defined for structure: " . $structure->key);
                }


                $pipeline = [
                    ...$pipeline,
                    ...$structure->getPipeline()
                ];





                if (isset($args['_id'])) {

                    $pipeline = [
                        ...$pipeline,
                        [
                            '$match' => [
                                "_id" => mDB::id($args['_id']),
                            ],
                        ],
                        [
                            '$limit' => 1
                        ],
                    ];

                    $result = mDB::collection($structure->collection)->aggregate($pipeline)->toArray()[0] ?? null;


                    if (!$result) {
                        return $withoutData ? [] : [
                            'data' => [],
                        ];
                    } else {

                        $result = $structure->normalize($result);
                        return $withoutData ? [$result] : [
                            'data' => [$result],
                        ];
                    }
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




                if (isset($args['search']) && trim($args['search']) !== '') {

                    $pipeline[] = [
                        '$match' => [
                            'search_string' => ['$regex' => mb_strtolower(trim($args['search'])), '$options' => 'i'],
                        ],
                    ];
                }



                $total = 0;
                if (!$withoutData && !$onlyHash) {
                    $total =  $structure->aggregate([
                        ...$pipeline,
                        [
                            '$count' => 'total',
                        ],
                    ])->toArray()[0]['total'] ?? 0;
                }

                $dateDistinctField = $args['dateDistinct'] ?? null;

                $dateDistinctData = [];
                if (!$withoutData && !$onlyHash && $dateDistinctField) {


                    $dateDistinctFieldItem = $structure->items[$dateDistinctField] ?? null;

                    if ($dateDistinctFieldItem->type == 'unixdatetime' || $dateDistinctFieldItem->type == 'unixdate') {



                        $dateDistinctData =  $structure->aggregate([
                            ...$pipeline,
                            [
                                '$project' => [
                                    $dateDistinctField => 1
                                ]
                            ],
                            [
                                '$match' => [
                                    $dateDistinctField => ['$gt' => 0]
                                ]
                            ],


                            [
                                '$addFields' => [
                                    'dateToString' => [
                                        '$dateToString' => [
                                            'format' => '%d.%m.%Y',
                                            'date' => ['$toDate' => ['$multiply' => ['$' . $dateDistinctField, 1000]]],
                                            'timezone' => date_default_timezone_get()
                                        ]
                                    ]

                                ]
                            ],


                            [
                                '$group' => [
                                    '_id' => '$dateToString',
                                    'format' => ['$first' => '$dateToString'],
                                    'from' => ['$min' => '$' . $dateDistinctField],
                                    'to' => ['$max' => '$' . $dateDistinctField],
                                    'count' => ['$sum' => 1],

                                ]
                            ],
                            [
                                '$sort' => [
                                    'from' => -1
                                ]
                            ],



                        ])->toArray() ?? [];
                    }
                }

                $_limit = $args['limit'] ?? null;


                if (!$withoutData && $_limit === 0) {
                    return [
                        'data' => [],
                        'limit' => 0,
                        'offset' => 0,
                        'dateDistinct' => $dateDistinctData,
                        'total' => $total,
                    ];
                }



                $sortField = $args['sort']['field'] ?? null;
                $sortDirection = $args['sort']['direction'] ?? null;


                if ($sortField && $sortDirection) {

                    $pipeline[] = [
                        '$sort' => [
                            $sortField => $sortDirection == "DESC" ? -1 : 1,
                        ],
                    ];
                } else {


                    //Проверка нет ли в $pipeline sort
                    $hasSort = false;
                    foreach ($pipeline as $stage) {
                        if (isset($stage['$sort'])) {
                            $hasSort = true;
                            break;
                        }
                    }

                    if (!$hasSort) {

                        if ($structure->manualSort) {

                            $pipeline[] = [
                                '$sort' => [
                                    "_sortWeight" => -1,

                                ],
                            ];
                        } else {

                            $pipeline[] = [
                                '$sort' => [
                                    "_id" => -1,
                                ],
                            ];
                        }
                    }
                }

                if (isset($args['offset']) && $args['offset'] > 0) {

                    $pipeline[] = [
                        '$skip' => $args['offset'],
                    ];
                }




                $pipeline[] = [
                    '$limit' => $args['limit'] ?? 20,
                ];


                if ($onlyHash) {

                    $pipeline[] = [
                        '$project' => [
                            '_id' => 1,
                            'updated_at' => 1,
                        ],
                    ];
                }



                $result = $structure->aggregate(
                    $pipeline,

                )->toArray();




                if ($onlyHash) {


                    return [
                        'hash' => mDB::hashDocuments($result),
                    ];
                }



                $result =  Shm::arrayOf($structure)->normalize($result);



                if ($withoutData) {

                    return $result;
                } else {






                    return [
                        'data' => $result,
                        'limit' => $args['limit'] ?? 20,
                        'offset' => $args['offset'] ?? 0,
                        'dateDistinct' => $dateDistinctData,
                        'hash' =>  mDB::hashDocuments($result),
                        'total' => $total,
                    ];
                }
            },
        ];

        return $result;
    }
}
