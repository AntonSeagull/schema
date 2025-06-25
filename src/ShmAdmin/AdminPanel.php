<?php

namespace Shm\ShmAdmin;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAdmin\Types\AdminType;
use Shm\ShmAdmin\Types\GroupType;
use Shm\ShmAdmin\Utils\DescriptionsUtils;
use Shm\ShmAuth\Auth;
use Shm\ShmBlueprints\Auth\ShmAuth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\Config;
use Shm\ShmUtils\Response;


class AdminPanel
{

    public static function group(array $item): GroupType
    {
        return new GroupType($item);
    }

    public static function admin(array $item): AdminType
    {
        return new AdminType($item);
    }



    public static AdminType $schema;

    /**
     * @var StructureType[]
     */
    public static array $users = [];

    /**
     * @param AdminType $schema
     */
    public static function setSchema(AdminType $schema)
    {
        self::$schema = $schema;
    }

    public static function setUsers(StructureType  ...$users): void
    {
        self::$users = $users;
    }


    private static function removeNullValues($data)
    {

        foreach ($data as $key => $val) {

            if ($val === null || $val === false || $val == []) {
                unset($data[$key]);
                continue;
            }
            if (is_array($val) || is_object($val)) {
                $data[$key] = self::removeNullValues($val);
                if ($val === null || $val === false) {
                    unset($data[$key]);
                }
            }
        }

        return $data;
    }

    public static function json()
    {

        self::$schema->filterType();

        self::$schema->columns();

        $data = json_decode(json_encode(get_object_vars(self::$schema)), true);
        $data = self::removeNullValues($data);

        return $data;
    }

    private static $allTypes = [
        'enum',
        'enums',
        'string',
        'structure',
        'color',
        'unixdatetime',
        'mixed',
        'bool',
        'float',
        'int',
        'selfRef',
        'uuid',
        'social',
        'text',
        'html',
        'time',
        'rangeunixdate',
        'file',
        'imagelink',
        'video',
        'IDs',
        'image',
        'geopoint',
        'array',
        'phone',
        'ID',
        'unixdate',
        'mongoPoint',
        'adminGroup',
        'dashboard',
        'admin'
    ];

    private static function baseStructure(): StructureType
    {
        $type = Shm::structure([
            "collection" => Shm::string(),
            "key" => Shm::string(),
            'itemType' => Shm::selfRef(function () use (&$type) {
                return $type;
            }),

            'publicStages' => Shm::structure([
                "*" => Shm::string()
            ]),
            'filterType' => Shm::selfRef(function () use (&$type) {
                return $type;
            }),
            'items' => Shm::structure([
                "*" => Shm::selfRef(function () use (&$type) {
                    return $type;
                }),
            ]),
            'document' => Shm::selfRef(function () use (&$type) {
                return $type;
            }),

            "columns" => Shm::arrayOf(Shm::structure([
                'key' => Shm::string(),
                'title' => Shm::string(),
                'dataIndex' => Shm::string(),
                'width' => Shm::int(),
                "type" => Shm::selfRef(function () use (&$type) {
                    return $type;
                }),
            ])),
            'values' => Shm::structure([
                "*" => Shm::string()
            ]),
            'hide' => Shm::bool(),
            "svgIcon" => Shm::string(),
            "single" => Shm::boolean(),
            "min" => Shm::float(),
            "max" => Shm::float(),
            "editable" => Shm::boolean(),
            "inAdmin" => Shm::boolean(),
            "inTable" => Shm::boolean(),
            "col" => Shm::integer(),
            "required" => Shm::boolean(),
            "nullable" => Shm::boolean(),
            "default"   => Shm::mixed(),
            "title" => Shm::string(),
            "type" => Shm::enum(self::$allTypes),
            "cond" => Shm::mixed(),
            "defaultIsSet" => Shm::boolean(),
            "collection" => Shm::string(),
            'assets' => Shm::structure([
                'icon' => Shm::string(),
                'cover' => Shm::string(),
                'color' => Shm::string(),
                'subtitle' => Shm::string(),
            ]),
            'group' => Shm::structure([
                'key' => Shm::string(),
                'svgIcon' => Shm::string(),
                'title' => Shm::string(),
            ]),



        ])->staticBaseTypeName("Structure");

        return $type;
    }

    public static function rpc()
    {

        ShmRPC::init([


            'authEmail' =>  ShmRPC::auth(...self::$users)->email()->make(),
            'authSoc' =>  ShmRPC::auth(...self::$users)->soc()->make(),
            'authPhone' => ShmRPC::auth(...self::$users)->msg()->make(),

            'profile' =>  [
                "type" => Shm::structure([
                    "_id" => Shm::string(),
                    "photo" => Shm::string(),
                    "name" => Shm::string(),
                    "phone" => Shm::float(),
                    "surname" => Shm::string(),
                    "email" => Shm::string(),
                    "social" => Shm::social(),
                ]),

                'resolve' => function ($root, $args) {


                    Auth::authenticateOrThrow(...self::$users);

                    $model =  Auth::getApiKeyStructure();


                    $emailField = $model->findItemByType(Shm::email());
                    $socialField = $model->findItemByType(Shm::social());
                    $nameField = $model->findItemByKey('name');
                    $surnameField = $model->findItemByKey('surname');
                    $photoField = $model->findItemByType(Shm::fileImageLink());
                    $phoneField = $model->findItemByType(Shm::phone());


                    $currentManager = Auth::getAuth();

                    return [
                        "_id" => $currentManager->_id,
                        "photo" => $currentManager[$photoField] ?? null,
                        "name" =>  $currentManager[$nameField] ?? null,
                        "surname" => $currentManager[$surnameField] ?? null,
                        "email" => $currentManager[$emailField] ?? null,
                        "phone" => $currentManager[$phoneField] ?? null,
                        "social" => $currentManager[$socialField] ?? [],
                    ];
                }

            ],

            'init' => [
                'type' => Shm::structure([

                    'auth' => Shm::structure([
                        'onlyAuth' => Shm::boolean(),
                        'types' => Shm::arrayOf(Shm::enum([
                            'email',
                            'phone',
                            'social'
                        ])),
                    ]),
                    'structure' => self::baseStructure(),
                    'socket' => Shm::structure([
                        'domain' => Shm::string(),
                        'prefix' => Shm::string(),
                    ]),

                ]),
                'resolve' => function ($root, $args) {
                    // Auth::authenticateOrThrow(...self::$users);

                    $initData = self::json();



                    $authTypes = [];
                    $onlyAuth = true;
                    foreach (self::$users as $user) {

                        if ($user->findItemByType(Shm::email())) {
                            $authTypes[] = 'email';
                        }
                        if ($user->findItemByType(Shm::phone())) {
                            $authTypes[] = 'phone';
                        }
                        if ($user->findItemByType(Shm::social())) {
                            $authTypes[] = 'social';
                        }

                        if (!$user->onlyAuth) {
                            $onlyAuth = false;
                        }
                    }


                    return [
                        'auth' => [
                            'onlyAuth' => $onlyAuth,
                            'types' => $authTypes
                        ],
                        'structure' => $initData,

                        "socket" => Config::get('socket'),
                    ];
                }

            ],

            'data' => [
                'type' => Shm::structure([
                    'data' => Shm::arrayOf(Shm::structure([
                        "_id" => Shm::ID(),
                        "*" => Shm::mixed(),
                    ])),
                    'limit' => Shm::int(),
                    'offset' => Shm::int(),
                    'total' => Shm::int(),
                ]),
                'args' => Shm::structure([

                    "_id" => Shm::ID()->default(null),
                    "collection" => Shm::nonNull(Shm::string()),
                    'limit' => Shm::int()->default(30),
                    'delete' => Shm::boolean()->default(false),
                    'offset' =>  Shm::int()->default(0),
                    'search' => Shm::string()->default(''),
                    'sort' => Shm::structure([
                        'direction' => Shm::enum([
                            'ASC' => 'По возрастанию',
                            'DESC' => 'По убыванию',
                        ])->default('DESC'),
                        'field' => Shm::string(),
                    ]),
                    'filter' => Shm::mixed(),
                    'stage' => Shm::string()
                ]),
                'resolve' => function ($root, $args) {

                    //  Auth::authenticateOrThrow(...self::$users);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }



                    $structure = self::$schema->findItemByCollection($args['collection']);




                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }


                    $root['type']->items['data'] = Shm::arrayOf($structure);

                    $root['type']->items['data']->updateKeys("data");

                    $pipeline = $structure->getPipeline();


                    if (isset($args['stage'])) {

                        $stage = $structure->findStage($args['stage']);

                        if ($stage) {
                            $pipeline = [
                                ...$pipeline,
                                ...$stage->getPipeline(),
                            ];
                        }
                    }



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

                        $result = $structure->aggregate($pipeline)->toArray() ?? null;


                        if (!$result) {
                            return [
                                'data' => [],
                            ];
                        } else {

                            return  [
                                'data' => $result
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




                    if (isset($args['search'])) {

                        $pipeline[] = [
                            '$match' => [
                                'search_string' => ['$regex' => mb_strtolower(trim($args['search'])), '$options' => 'i'],
                            ],
                        ];
                    }


                    $total = 0;

                    $total =  $structure->aggregate([
                        ...$pipeline,
                        [
                            '$count' => 'total',
                        ],
                    ])->toArray()[0]['total'] ?? 0;

                    $_limit = $args['limit'] ?? null;


                    if ($_limit === 0) {
                        return [
                            'data' => [],
                            'limit' => 0,
                            'offset' => 0,
                            'total' => $total,
                        ];
                    }





                    if (isset($args['sort']) && isset($args['sort']['field']) && isset($args['sort']['direction'])) {

                        $pipeline[] = [
                            '$sort' => [
                                $args['sort']['field'] => $args['sort']['direction'] == "DESC" ? -1 : 1,
                            ],
                        ];
                    } else {


                        $pipeline[] = [
                            '$sort' => [
                                "_sortWeight" => -1,
                                "_id" => -1,
                            ],
                        ];
                    }

                    if (isset($args['offset']) && $args['offset'] > 0) {

                        $pipeline[] = [
                            '$skip' => $args['offset'],
                        ];
                    }



                    $result = $structure->aggregate(
                        $pipeline,
                        [
                            'limit' => $args['limit'] ?? 30,
                        ]
                    )->toArray();





                    return [
                        'data' => $result,
                        'limit' => $args['limit'] ?? 30,
                        'offset' => $args['offset'] ?? 0,
                        'total' => $total,
                    ];
                }

            ],

            'stagesTotal' => [
                'type' => Shm::structure([

                    "*" => Shm::number(),

                ]),
                'args' => Shm::structure([

                    "collection" => Shm::nonNull(Shm::string()),
                    'search' => Shm::string()->default(''),
                    'filter' => Shm::mixed(),
                ]),
                'resolve' => function ($root, $args) {

                    //  Auth::authenticateOrThrow(...self::$users);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }



                    $structure = self::$schema->findItemByCollection($args['collection']);




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

            ],


            'itemDescription' => [
                "type" => Shm::string(),
                "args" =>  Shm::structure([
                    "collection" =>  Shm::nonNull(Shm::string()),
                    "type" => Shm::enum(['fields', 'groups', 'tabs', 'menu']),
                    "path" => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {
                    Auth::authenticateOrThrow(...self::$users);

                    $fieldDescription = mDB::collection("_collectionDescriptions")->findOne([
                        "key" => $args['collection'],
                    ]);


                    return $fieldDescription[$args['type']][Auth::getApiKeyStructure()->collection][$args['path']] ?? null;
                }

            ],
            "dataDescription" => [
                "type" => Shm::structure([
                    'fields' => Shm::listOf(Shm::structure([
                        'key' => Shm::string(),
                        'description' => Shm::string(),
                    ])),
                    'groups' => Shm::listOf(Shm::structure([
                        'key' => Shm::string(),
                        'description' => Shm::string(),
                    ])),
                    'tabs' => Shm::listOf(Shm::structure([
                        'key' => Shm::string(),
                        'description' => Shm::string(),
                    ])),
                    "menu" => Shm::listOf(Shm::structure([
                        'key' => Shm::string(),
                        'description' => Shm::string(),
                    ])),
                ]),

                'args' => Shm::structure([
                    "collection" => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {
                    Auth::authenticateOrThrow(...self::$users);


                    $result = [];

                    $fields = DescriptionsUtils::fields($args['collection'], Auth::getApiKeyStructure()->collection);

                    foreach ($fields as $key => $val) {
                        $result['fields'][] = [
                            "key" => $key,
                            "description" => $val
                        ];
                    }

                    $groups = DescriptionsUtils::groups($args['collection'], Auth::getApiKeyStructure()->collection);

                    foreach ($groups  as $key => $val) {
                        $result['groups'][] = [
                            "key" => $key,
                            "description" => $val
                        ];
                    }
                    $tabs = DescriptionsUtils::tabs($args['collection'], Auth::getApiKeyStructure()->collection);
                    foreach ($tabs as $key => $val) {
                        $result['tabs'][] = [
                            "key" => $key,
                            "description" => $val
                        ];
                    }

                    $menu = DescriptionsUtils::menu($args['collection'], Auth::getApiKeyStructure()->collection);
                    foreach ($menu as $key => $val) {
                        $result['menu'][] = [
                            "key" => $key,
                            "description" => $val
                        ];
                    }

                    return $result;
                }
            ],
            "blockDescription" => [
                "type" => Shm::string(),
                'args' => Shm::structure([
                    "key" => Shm::nonNull(Shm::string()),
                    "block" =>  Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {
                    Auth::authenticateOrThrow(...self::$users);

                    $fieldDescription = mDB::collection("_collectionDescriptions")->findOne([

                        "key" => $args['key'],
                    ]);



                    return $fieldDescription['fields'][Auth::getAuthStructure()->collection][$args['block']] ?? null;
                }

            ],



            'localizationEntries' => [

                "type" => Shm::listOf(Shm::structure([
                    'key' => Shm::string(),
                    'value' => Shm::string(),
                ])),
                'args' => Shm::structure([
                    "lang" => Shm::nonNull(Shm::enum([
                        'ru',
                        'en',
                    ])),
                ]),
                'resolve' => function ($root, $args) {

                    $aggregate = [
                        [
                            '$project' => [
                                "key" => 1,
                                "value" => '$' . $args['lang'],
                            ],
                        ],
                    ];

                    return mDB::collection("_localization")->aggregate($aggregate)->toArray();
                },

            ]
        ]);
    }
}
