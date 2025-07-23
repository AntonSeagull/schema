<?php

namespace Shm\ShmBlueprints;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmTypes\StructureType;

class ShmBlueprintQuery
{


    private StructureType $structure;



    public $pipeline = [];




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
    public $prepare = null;


    public function prepare(callable $prepare): self
    {
        $this->prepare = $prepare;
        return $this;
    }

    public function pipeline($pipeline = []): self
    {

        if (count($pipeline) > 0) {
            mDB::validatePipeline($pipeline);
        }


        $this->pipeline = $pipeline;
        return $this;
    }


    public function filter($filter = true): self
    {
        $this->filter = $filter;
        return $this;
    }

    public function sort($sort = true): self
    {
        $this->sort = $sort;
        return $this;
    }



    /**
     * Устанавливает функцию, которая будет выполнена перед запросом.
     *
     * @param callable $beforeQuery Функция-коллбэк, которая принимает аргументы запроса и может модифицировать их.
     * @return self
     */
    public function before(callable $beforeQuery): self
    {
        $this->beforeQuery = $beforeQuery;
        return $this;
    }

    /**
     * Устанавливает функцию, которая будет выполнена после запроса.
     *
     * @param callable $afterQuery Функция-коллбэк, которая принимает результат запроса и может модифицировать его.
     * @return self
     */
    public function after(callable $afterQuery): self
    {
        $this->afterQuery = $afterQuery;
        return $this;
    }



    public function withoutData($withoutData = true): self
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
                'offset' => Shm::int(),
                'hash' => Shm::string(),
                'total' => Shm::int(),
            ])->key($this->structure->key . 'Data');
        }


        $args = [

            "_id" => Shm::ID()->default(null),


            'onlyHash' => $this->withoutData ? null : Shm::boolean()->default(false),

            'limit' => Shm::int()->default(30),
            'sample' => Shm::boolean(),

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
        $pipeline = $this->pipeline;



        $result = [
            'type' => $dataType,
            'args' =>  $argsStructure,
            'resolve' => function ($root, $args, $context, $info) use ($structure, $withoutData, $pipeline, $argsStructure) {






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

                $_limit = $args['limit'] ?? null;


                if (!$withoutData && $_limit === 0) {
                    return [
                        'data' => [],
                        'limit' => 0,
                        'offset' => 0,
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

                    $hash = [];

                    foreach ($result as $val) {
                        $hash[] = $val['_id'] . $val['updated_at'];
                    }

                    $hash = md5(implode("", $hash));

                    return [
                        'hash' => $hash,
                    ];
                }



                $result =  Shm::arrayOf($structure)->normalize($result);



                if ($withoutData) {

                    return $result;
                } else {




                    $hash = [];

                    foreach ($result as $val) {
                        $hash[] = $val['_id'] . $val['updated_at'];
                    }

                    if (count($hash) > 0) {
                        $hash = md5(implode("", $hash));
                    } else {
                        $hash = null;
                    }

                    return [
                        'data' => $result,
                        'limit' => $args['limit'] ?? 20,
                        'offset' => $args['offset'] ?? 0,
                        'hash' => $hash,
                        'total' => $total,
                    ];
                }
            },
        ];

        return $result;
    }
}
