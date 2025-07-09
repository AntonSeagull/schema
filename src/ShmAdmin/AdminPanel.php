<?php

namespace Shm\ShmAdmin;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAdmin\Types\AdminType;
use Shm\ShmAdmin\Types\GroupType;
use Shm\ShmAdmin\Utils\DescriptionsUtils;
use Shm\ShmAuth\Auth;

use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\Config;
use Shm\ShmUtils\Inflect;
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
        'range',
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
        'admin',
        "login",
        "password",
        "email",
        "mongoPoint",
        "mongoPolygon"
    ];

    private static function baseStructure(): StructureType
    {
        $type = Shm::structure([
            "collection" => Shm::string(),
            "key" => Shm::string(),
            'itemType' => Shm::selfRef(function () use (&$type) {
                return $type;
            }),


            'manualSort' => Shm::bool(),
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


            'values' => Shm::structure([
                "*" => Shm::string()
            ]),

            'canUpdate' => Shm::boolean(),
            'canDelete' => Shm::boolean(),
            'canCreate' => Shm::boolean(),
            'hide' => Shm::bool(),
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
                'icon' => Shm::string(),
                'title' => Shm::string(),
            ]),



        ])->staticBaseTypeName("Structure");

        return $type;
    }

    private static function url($path = "")
    {


        $scheme =  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';

        $host = $_SERVER['HTTP_HOST'] ?? "";

        $currentURL = $scheme . '://' . $host;

        return $currentURL . $path;
    }


    private static function html()
    {


        $title = self::$schema->title ?? "Admin";

        $icon = self::$schema->assets['icon'] ?? null;
        $color = self::$schema->assets['color'] ?? "#000000";
        $url = (string) self::url();

        $js =  $url . "/static/js/main.js";
        $css = $url . "/static/css/main.css";

        $html = '<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover" />
    <script async src="https://unpkg.com/pwacompat" crossorigin="anonymous"></script>
    <meta name="theme-color" content="#000000" />
    <meta name="description" content="" />
    <title>' . $title . '</title>

   
    <link rel="icon" href="' . $icon . '" type="image/x-icon" />
    <link rel="apple-touch-icon" href="' . $icon . '">
    <meta name="theme-color" content="black" />
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="' . $title . '">
    <meta name="msapplication-TileImage" content="' . $icon . '">
    <meta name="msapplication-TileColor" content="#000">

   

    <link rel="stylesheet" href="' . $css . '" />
   
</head>

<body>
    <noscript>You need to enable JavaScript to run this app.</noscript>
    <div id="load-preloader"
        style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: white; display: flex; justify-content: center; align-items: center;">
        <span class="init-loader"></span>
    </div>
    <div id="root"></div>
</body>

<script src="' . $js . '"></script>

<style>
    .init-loader {
        width: 38px;
        height: 38px;
        border: 3px solid;

        border-color: ' . $color . ' transparent;
        border-radius: 50%;
        display: inline-block;
        box-sizing: border-box;
        animation: rotation 1s linear infinite;
    }

    @keyframes rotation {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }
</style>

</html>
';

        return $html;
    }

    public static function rpc()
    {


        if (!isset($_GET['schema']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
            header_remove("X-Frame-Options");
            $html = self::html();

            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            exit;
        }




        ShmRPC::init([


            'authEmail' =>  ShmRPC::auth(...self::$users)->email()->make(),
            'authSoc' =>  ShmRPC::auth(...self::$users)->soc()->make(),
            'authPhone' => ShmRPC::auth(...self::$users)->msg()->make(),

            'profile' =>  [
                "type" => Shm::structure([

                    'structure' => self::baseStructure(),
                    'data' => Shm::mixed(),
                    'changePassword' => Shm::boolean(),
                ]),

                'resolve' => function ($root, $args) {


                    Auth::authenticateOrThrow(...self::$users);


                    $findStructure = null;

                    foreach (self::$users as $user) {

                        if ($user->collection == Auth::getAuthCollection()) {
                            $findStructure = $user;
                            break;
                        }
                    }

                    if (!$findStructure) {
                        Response::validation("Ошибка доступа");
                    }


                    $passwordField =  $findStructure->findItemByType(Shm::password());

                    if ($passwordField)
                        $findStructure->items[$passwordField->key]->inAdmin(false);

                    return [
                        'structure' => json_decode(json_encode(get_object_vars($findStructure)), true),
                        'data' => $findStructure->normalize($findStructure->findOne([
                            '_id' => Auth::getAuthOwner()
                        ])),
                        'changePassword' => $passwordField ? true : false,
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

            'emptyData' => [
                'type' => Shm::structure([
                    "_id" => Shm::ID(),
                    "*" => Shm::mixed(),
                ]),
                'args' => Shm::structure([

                    "collection" => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$users);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }

                    $structure = self::$schema->findItemByCollection($args['collection']);


                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }


                    $root['type'] = $structure;

                    $root['type']->updateKeys("type");



                    return $structure->normalize([], true);
                }

            ],



            'geocode' =>  [

                "type" => Shm::arrayOf(Shm::structure([
                    'id' => Shm::string(),
                    'name' => Shm::string(),
                    'description' => Shm::string(),
                    'coordinates' => Shm::structure([
                        'latitude' => Shm::float(),
                        'longitude' => Shm::float(),

                    ]),

                ])),
                'args' => [
                    'byCoords' => Shm::structure([
                        'latitude' => Shm::float(),
                        'longitude' => Shm::float(),
                    ]),
                    'byString' => Shm::structure(
                        [
                            'string' => Shm::string(),
                            'latutude' => Shm::float(),
                            'longitude' => Shm::float()
                        ]
                    )

                ],
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$users);

                    $byCoordsLat = $args['byCoords']['latitude'] ?? null;
                    $byCoordsLon = $args['byCoords']['longitude'] ?? null;

                    $byString = $args['byString']['string'] ?? null;
                    $byStringLat = $args['byString']['latitude'] ?? null;
                    $byStringLon = $args['byString']['longitude'] ?? null;

                    $queryParams = [
                        'apikey' => Config::get("yandexGeocodeKey"),
                        'format' => 'json',
                        'lang' => "ru_RU",
                        'kind' => "house"
                    ];

                    $geocodeSeted = false;

                    if ($byCoordsLat && $byCoordsLon) {
                        $queryParams['geocode'] = $byCoordsLon . ',' . $byCoordsLat;
                        $geocodeSeted = true;
                    } elseif ($byString) {
                        $queryParams['geocode'] = $byString;
                        $geocodeSeted = true;
                        if ($byStringLat && $byStringLon) {
                            $queryParams['ll'] = $byStringLon . ',' . $byStringLat;
                        }
                    }

                    if (!$geocodeSeted) return [];

                    $client = new Client();
                    $request = new Request('GET', 'https://geocode-maps.yandex.ru/1.x/?' . http_build_query($queryParams));
                    $res = $client->sendAsync($request)->wait();
                    $body = $res->getBody();

                    $body =  json_decode($body, true);

                    $featureMember = $body['response']['GeoObjectCollection']['featureMember'] ?? null;
                    $result = [];
                    foreach ($featureMember as $val) {




                        $cord = explode(' ', $val['GeoObject']['Point']['pos']);

                        $name = $val['GeoObject']['name'] ?? "";
                        $description = $val['GeoObject']['description'] ?? "";
                        if ($name) {
                            $result[] = [
                                'id' => md5($name . $description . $cord[0] . $cord[1]),
                                'name' => $name,
                                'description' => $description,
                                'coordinates' => [
                                    "longitude" =>  +$cord[0],
                                    "latitude" =>   +$cord[1]
                                ],
                            ];
                        }
                    }

                    return $result;
                },

            ],



            'deleteData' => [
                'type' => Shm::bool(),
                'args' => Shm::structure([

                    "_ids" => Shm::IDs()->default(null),
                    "collection" => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$users);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }



                    $structure = self::$schema->findItemByCollection($args['collection']);


                    $_ids = $args['_ids'] ?? null;

                    if (!$_ids) {
                        Response::validation("Нет данных для удаления");
                    }


                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }


                    $structure->deleteMany([
                        '_id' => ['$in' => $_ids]
                    ]);

                    return true;
                }
            ],


            'hash' => [
                'type' => Shm::string(),
                'args' => Shm::structure([
                    "collection" => Shm::nonNull(Shm::string()),
                    'limit' => Shm::int()->default(30),
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



                    Auth::authenticateOrThrow(...self::$users);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }



                    $structure = self::$schema->findItemByCollection($args['collection']);



                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }


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



                    $_limit = $args['limit'] ?? null;

                    if (! $_limit) {
                        return null;
                    }




                    if (isset($args['sort']) && isset($args['sort']['field']) && isset($args['sort']['direction'])) {

                        $pipeline[] = [
                            '$sort' => [
                                $args['sort']['field'] => $args['sort']['direction'] == "DESC" ? -1 : 1,
                            ],
                        ];
                    } else {

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

                    if (isset($args['offset']) && $args['offset'] > 0) {

                        $pipeline[] = [
                            '$skip' => $args['offset'],
                        ];
                    }


                    //Оставляем только _id и updated_at для хеширования
                    $pipeline[] = [
                        '$project' => [
                            '_id' => 1,
                            'updated_at' => 1,
                        ],
                    ];

                    $pipeline[] = [
                        '$limit' => $args['limit'] ?? 20,
                    ];


                    Response::startTraceTiming("data_aggregate");
                    $result = $structure->aggregate(
                        $pipeline

                    )->toArray();
                    Response::endTraceTiming("data_aggregate");


                    $hash = [];

                    foreach ($result as $val) {
                        $hash[] = $val['_id'] . $val['updated_at'];
                    }

                    return  md5(implode("", $hash));
                }

            ],

            'data' => [
                'type' => Shm::structure([
                    'data' => Shm::arrayOf(Shm::structure([
                        "_id" => Shm::ID(),
                        "*" => Shm::mixed(),
                    ])),
                    'limit' => Shm::int(),
                    'hash' => Shm::string(),
                    'offset' => Shm::int(),
                    'total' => Shm::int(),
                ]),
                'args' => Shm::structure([

                    "_id" => Shm::ID()->default(null),
                    'table' => Shm::boolean()->default(false),
                    "collection" => Shm::nonNull(Shm::string()),
                    'limit' => Shm::int()->default(30),

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



                    Auth::authenticateOrThrow(...self::$users);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }



                    $structure = self::$schema->findItemByCollection($args['collection']);



                    $structure->inTable(true);
                    if ($args['table'] ?? false) {
                        $structure->hideNotInTable();
                    }

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

                    Response::startTraceTiming("total_count");
                    $total =  $structure->aggregate([
                        ...$pipeline,
                        [
                            '$count' => 'total',
                        ],
                    ])->toArray()[0]['total'] ?? 0;
                    Response::endTraceTiming("total_count");

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

                    if (isset($args['offset']) && $args['offset'] > 0) {

                        $pipeline[] = [
                            '$skip' => $args['offset'],
                        ];
                    }


                    $pipeline[] = [
                        '$limit' => $args['limit'] ?? 20,
                    ];


                    Response::startTraceTiming("data_aggregate");
                    $result = $structure->aggregate(
                        $pipeline

                    )->toArray();
                    Response::endTraceTiming("data_aggregate");


                    $hash = [];

                    foreach ($result as $val) {
                        $hash[] = $val['_id'] . $val['updated_at'];
                    }

                    $hash = md5(implode("", $hash));



                    return [
                        'data' => $result,
                        'limit' => $args['limit'] ?? 20,
                        'offset' => $args['offset'] ?? 0,
                        'hash' => $hash,
                        'total' => $total,
                    ];
                }

            ],



            'apikeys' => [
                'type' => Shm::arrayOf(Shm::structure([
                    "_id" => Shm::ID(),
                    'apikey' => Shm::string(),
                    "title" => Shm::string(),
                    'last_used' => Shm::int(),
                    "created_at" => Shm::int(),
                ])),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$users);

                    $apikeys = mDB::collection(Auth::$apikey_collection)->find(
                        [
                            'owner' => Auth::getAuthOwner(),
                        ],
                        [
                            'projection' => [
                                '_id' => 1,
                                'apikey' => 1,
                                'last_used' => 1,
                                'title' => 1,
                                'created_at' => 1,
                            ]
                        ]
                    )->toArray();

                    $result = [];

                    foreach ($apikeys as $apikey) {


                        $result[] = [
                            '_id' => (string)$apikey['_id'],
                            //От APIKEY остаавляем только первые 10 символов
                            'apikey' =>  substr($apikey['apikey'] ?? '', 0, 10) . '...',
                            'title' => $apikey['title'] ?? '',
                            'last_used' => $apikey['last_used'] ?? 0,
                            'created_at' => $apikey['created_at'] ?? 0,
                        ];
                    }

                    return $result;
                }
            ],

            'removeApiKey' => [
                'type' => Shm::boolean(),
                'args' => [
                    '_id' => Shm::string(),
                ],
                'resolve' => function ($root, $args) {


                    Auth::authenticateOrThrow(...self::$users);

                    $apikeyId = $args['_id'] ?? null;

                    if (!$apikeyId) {
                        Response::validation("Не указан ID ключа");
                    }

                    $apikey = mDB::collection(Auth::$apikey_collection)->findOne([
                        '_id' => mDB::id($apikeyId),
                        'owner' => Auth::getAuthOwner(),
                    ]);

                    if (!$apikey) {
                        Response::validation("Ключ не найден или не принадлежит вам");
                    }

                    mDB::collection(Auth::$apikey_collection)->deleteOne([
                        '_id' => mDB::id($apikeyId),
                    ]);

                    return true;
                }
            ],

            'newApiKey' => [
                'type' => Shm::string(),
                'args' => [
                    'title' => Shm::string(),
                ],
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$users);

                    $title = $args['title'] ?? null;

                    if (!$title) {
                        Response::validation("Не указано название ключа");
                    }

                    $apikey = Auth::genApiKey($title, Auth::getAuthCollection(), Auth::getAuthOwner());

                    if (!$apikey) {
                        Response::validation("Ошибка при создании ключа");
                    }

                    return   $apikey;
                }

            ],


            'moveUpdate' => [
                'type' => Shm::bool(),
                'args' => Shm::structure([

                    "_id" => Shm::ID(),
                    'collection' => Shm::string(),
                    'aboveId' => Shm::ID(),
                    'belowId' => Shm::ID()
                ]),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$users);

                    if (!isset($args['collection'])) {
                        Response::validation("Изменения сортировки не доступна");
                    }


                    $structure = self::$schema->findItemByCollection($args['collection']);


                    if (!$structure) {
                        Response::validation("Изменения сортировки не доступна");
                    }


                    $_id = $args['_id'] ?? null;
                    $aboveId = $args['aboveId'] ?? null;
                    $belowId = $args['belowId'] ?? null;


                    $currentId = mDB::id($_id);

                    return  $structure->moveRow($currentId, $aboveId, $belowId);
                }

            ],



            'update' => [
                'type' => Shm::structure(
                    [
                        'data' => Shm::arrayOf(Shm::structure([
                            "_id" => Shm::ID(),
                            "*" => Shm::mixed(),
                        ])),
                    ]
                ),
                'args' => Shm::structure([

                    "_ids" => Shm::IDs()->default(null),
                    "collection" => Shm::nonNull(Shm::string()),
                    'values' => Shm::mixed(),

                ]),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$users);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }



                    $structure = self::$schema->findItemByCollection($args['collection']);




                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }


                    $root['type']->items['data'] = Shm::arrayOf($structure);

                    $root['type']->items['data']->updateKeys("data");


                    $values = $args['values'] ?? null;

                    if (!$values) {
                        Response::validation("Нет данных для обновления");
                    }

                    $values = $structure->normalize($values);
                    $values = $structure->removeOtherItems($values);

                    //remove _id
                    if (isset($values['_id'])) {
                        unset($values['_id']);
                    }

                    $ids = $args['_ids'] ?? null;

                    if ($ids) {



                        $structure->updateMany(
                            [
                                "_id" => ['$in' => $ids],
                            ],
                            [
                                '$set' => $values
                            ]
                        );

                        return [
                            'data' => $structure->find([
                                '_id' => ['$in' => $ids],
                            ]),
                        ];
                    } else {
                        $insert =  $structure->insertOne($values);

                        if (!$insert) {
                            Response::validation("Ошибка при добавлении данных");
                        }

                        $result = $structure->find([
                            '_id' => $insert->getInsertedId(),
                        ]);

                        return [
                            'data' => [$result],
                        ];
                    }
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

                    Auth::authenticateOrThrow(...self::$users);

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

            'descriptions' => [
                "type" => Shm::structure([
                    '*' => Shm::string()
                ]),
                'resolve' => function ($root, $args) {
                    Auth::authenticateOrThrow(...self::$users);

                    $fieldDescription = mDB::collection("_adminDescriptions")->findOne([
                        "ownerCollection" => Auth::getAuthCollection()
                    ]);


                    return $fieldDescription;
                }
            ],

            'payments' => [
                'type' => Shm::structure([

                    'balances' => Shm::structure([
                        "*" => Shm::float()
                    ])->staticBaseTypeName("PaymentsBalances"),
                    'data' => Shm::arrayOf(Shm::structure(
                        [

                            "amount" => Shm::float(),
                            "currency" => Shm::string(),
                            "description" => Shm::string(),
                            "created_at" => Shm::float(),
                        ]
                    )->staticBaseTypeName("PaymentsData")),

                ])->staticBaseTypeName("Payments"),
                'resolve' => function ($root, $args) {
                    Auth::authenticateOrThrow(...self::$users);


                    $paymentCollection = Auth::getAuthCollection() . "_payments";


                    $key = Inflect::singularize(Auth::getAuthCollection());
                    $filter = [
                        '$or' => [
                            ['manager' => Auth::getAuthOwner()],
                            [$key => Auth::getAuthOwner()],
                        ],
                    ];

                    $data = mDB::collection($paymentCollection)->find($filter, [
                        'sort' => [
                            "_id" => -1,
                        ],
                        'limit' => 1000,

                    ]);

                    $balancesData = mDB::collection($paymentCollection)->aggregate([
                        ['$match' => $filter],
                        ['$match' => ['deleted_at' => ['$exists' => false]]],

                        [
                            '$group' => [
                                '_id' => '$currency',
                                'value' => [
                                    '$sum' => '$amount'
                                ]
                            ]
                        ]
                    ])->toArray();


                    $balances = [];
                    foreach ($balancesData as $balance) {
                        $balances[$balance['_id']] = $balance['value'];
                    }


                    return [
                        'balances' =>        $balances,
                        'data' => $data
                    ];
                }
            ],

            'updateDescriptions' => [
                "type" => Shm::structure([
                    '*' => Shm::string()
                ]),
                'args' => [
                    "descriptions" => Shm::nonNull(Shm::structure([
                        '*' => Shm::string()
                    ]))
                ],
                'resolve' => function ($root, $args) {
                    Auth::authenticateOrThrow(...self::$users);



                    $fieldDescription = [];

                    //Добавляем новые значения
                    foreach ($args['descriptions'] as $key => $val) {

                        if ($key == "ownerCollection") {
                            continue;
                        }

                        if ($key == "_id") {
                            //Если это ID, то пропускаем
                            continue;
                        }

                        if ($key == "created_at" || $key == "updated_at") {
                            //Если это дата, то пропускаем
                            continue;
                        }

                        if ($val)
                            $fieldDescription[$key] = $val;
                    }


                    if (count($fieldDescription) > 0) {
                        mDB::collection("_adminDescriptions")->updateOne(
                            [
                                "ownerCollection" => Auth::getAuthCollection()
                            ],
                            [
                                '$set' => $fieldDescription
                            ],
                            [
                                'upsert' => true
                            ]
                        );
                    }


                    return $fieldDescription;
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

            ],

            'addLocalizationEntry' => [
                "type" => Shm::boolean(),
                'args' => Shm::structure([
                    "data" =>  Shm::nonNull(Shm::listOf(Shm::string())),
                ]),
                'resolve' => function ($root, $args) {



                    $updateData = []; // Создайте массив для хранения данных обновления
                    foreach ($args['data'] as $item) {


                        $key = md5($item);

                        $updateData[] = [
                            'updateOne' => [
                                [
                                    "key" => $key,

                                ],
                                [
                                    '$set' => [
                                        "key" => $key,
                                        "ru" => $item,
                                    ],
                                ],
                                [
                                    'upsert' => true,
                                ],
                            ],
                        ];
                    }


                    // Выполните обновление нескольких документов одним запросом
                    if (count($updateData) > 0) {
                        mDB::collection('_localization')->bulkWrite($updateData);
                    }


                    return true;
                },
            ]
        ]);
    }
}
