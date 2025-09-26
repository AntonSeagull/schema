<?php

namespace Shm\ShmAdmin;

use GraphQL\Type\Schema;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Shm\ShmDB\mDB;
use Shm\Shm;

use Shm\ShmAdmin\SchemaCollections\SubAccountsSchema;
use Shm\ShmAdmin\Types\AdminType;
use Shm\ShmAdmin\Types\GroupType;
use Shm\ShmAdmin\Utils\DescriptionsUtils;
use Shm\ShmAuth\Auth;

use Shm\ShmRPC\ShmRPC;
use Shm\ShmRPC\ShmRPCClient\ShmRPCClient;
use Shm\ShmSupport\ShmSupport;
use Shm\ShmTypes\StructureType;
use Shm\ShmTypes\SupportTypes\StageType;
use Shm\ShmUtils\Config;
use Shm\ShmUtils\Inflect;
use Shm\ShmUtils\MaterialIcons;
use Shm\ShmUtils\Response;
use Shm\ShmUtils\ShmInit;

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
        self::$schema = $schema->type("admin");
    }

    public static function setUsers(StructureType  ...$users): void
    {
        self::$users = [
            ...$users,
            SubAccountsSchema::baseStructure()
        ];
    }

    private static function fullSchema(): StructureType
    {

        $schema = self::$schema;


        if (!Auth::subAccountAuth()) {
            $schema->addField("subAccounts", SubAccountsSchema::structure(self::$schema));
        } else {

            $schema = SubAccountsSchema::removeLockItemInSchema($schema);
        }


        return $schema;
    }


    public static function json()
    {
        $schema = self::fullSchema();


        $reportCollections = self::$schema->getAllCollections();

        $reportsItems = [];

        foreach ($reportCollections as $collection) {

            if ($collection->report) {
                $reportsItems['report_' . $collection->collection] = (clone  $collection)->type("report")->icon(MaterialIcons::ChartArc());
            }
        }


        if (count($reportsItems) > 0) {



            $schema->update([

                'reports' => AdminPanel::group($reportsItems)->title("Аналитика и отчеты")->icon(MaterialIcons::ChartArc())

            ]);
        }




        // $schema->filterType(true);

        // return null;
        return $schema->json();
    }

    private static $allTypes = [
        'fileAudio',
        'fileDocumentLink',
        'fileDocument',
        'fileImageID',
        'fileVideoID',
        'fileAudioID',
        'fileAudioLink',
        'fileDocumentID',
        'fileImageLink',
        'fileImage',
        'fileVideo',
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
        'url',
        'text',
        'html',
        'time',
        'range',
        'IDs',
        'geopoint',
        'array',
        'phone',
        'ID',
        'code',
        'unixdate',
        'mongoPoint',
        'adminGroup',
        'dashboard',
        'admin',
        "login",
        "password",
        "email",
        "mongoPoint",
        "mongoPolygon",
        "url",
        "rate",
        "gradient",
        'report',
        'geoRegion'
    ];


    private static function reportResultType()
    {


        $viewType = Shm::enum([
            'treemap' => 'Древовидная карта',
            'bar' => 'Гистограмма',
            'cards' => 'Карточки',
            'pie' => 'Круговая диаграмма',
            'heatmap' => 'Тепловая карта',
            'horizontalBar' => 'Горизонтальная гистограмма',
        ]);

        $reportItem = Shm::structure([
            'view' =>  $viewType,

            'title' => Shm::string(),
            'structure' => self::baseStructure(),
            'heatmap' => Shm::structure([
                'xAxis' => Shm::arrayOf(Shm::string()),
                'yAxis' => Shm::arrayOf(Shm::string()),
                'data' => Shm::arrayOf(Shm::arrayOf(Shm::float())),
            ])->staticBaseTypeName("HeatmapData"),
            'result' => Shm::arrayOf(Shm::structure([
                'value' => Shm::mixed(),
                'item' => Shm::structure([
                    '_id' => Shm::ID(),
                    "*" => Shm::mixed(),
                ]),
                'name' => Shm::string(),

            ]))

        ])->staticBaseTypeName("ReportItem");

        return Shm::arrayOf(
            Shm::structure(
                [

                    'type' => Shm::string(),
                    'title' => Shm::string(),
                    'main' => Shm::arrayOf($reportItem),
                    'extra' => Shm::arrayOf($reportItem)
                ]
            )->staticBaseTypeName("ReportResult")
        );
    }

    private static function baseStructure(): StructureType
    {
        $type = Shm::structure([
            "collection" => Shm::string(),
            "key" => Shm::string(),
            'itemType' => Shm::selfRef(function () use (&$type) {
                return $type;
            }),



            'codeLang' => Shm::string(), //for CodeType
            'manualSort' => Shm::bool(),
            'publicStages' => Shm::structure([
                "*" => Shm::string()
            ]),

            'buttonActions' => Shm::selfRef(function () use (&$type) {
                return $type;
            }),

            'computedArgs' => Shm::selfRef(function () use (&$type) {
                return $type;
            }),



            'computedReturnType' => Shm::selfRef(function () use (&$type) {
                return $type;
            }),


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

            'columnsWidth' => Shm::float(),

            'values' => Shm::structure([
                "*" => Shm::string()
            ]),

            'paymentBalance' => Shm::bool(),
            'paymentCurrency' => Shm::arrayOf(Shm::string()),


            "apikey" => Shm::bool(),
            'tablePriority' => Shm::int(),
            'unique' => Shm::boolean(),
            'report' => Shm::boolean(),
            'globalUnique' => Shm::boolean(),
            'canUpdateCond' => Shm::mixed(),
            'display' => Shm::bool(),
            'displayPrefix' => Shm::string(),
            'trim' => Shm::boolean(),
            'uppercase' => Shm::boolean(),
            'accept' => Shm::string(),
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
            "localCond" => Shm::mixed(),
            "defaultIsSet" => Shm::boolean(),

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


    private static function getUID()
    {


        if (! Auth::isAuthenticated()) return null;



        if (Auth::subAccountAuth()) {
            return SubAccountsSchema::$collection . ':' . Auth::getSubAccountID();
        }

        return Auth::getAuthCollection() . ':' . Auth::getAuthOwner();
    }


    private static function html()
    {


        $title = self::$schema->title ?? "Admin";

        $icon = self::$schema->assets['icon'] ?? null;
        $color = self::$schema->assets['color'] ?? "#000000";
        $apiUrl =  self::url() . $_SERVER['REQUEST_URI'];


        $url = self::url();



        $js =  $url . "/static/main.js?shm=" . ShmInit::$shmVersionHash;
        $css = $url . "/static/main.css?shm=" . ShmInit::$shmVersionHash;

        $html = '<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1, maximum-scale=1, minimum-scale=1, user-scalable=no, viewport-fit=cover" />
   
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

<script url="' . $apiUrl . '" src="' . $js . '"></script>

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

        ShmInit::$isAdmin = true;

        if (!isset($_GET['schema']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
            header_remove("X-Frame-Options");
            $html = self::html();

            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            exit;
        }




        ShmRPC::init([

            'compositeTypes' => [
                'type' => Shm::structure([
                    'geoPoint' => Shm::geoPoint(),
                    'geoRegion' => Shm::geoRegion(),


                ]),
            ],

            'geolocation' => ShmRPC::IPGeolocation(),

            'imageUpload' => ShmRPC::fileUpload()->image()->make(),
            'videoUpload' => ShmRPC::fileUpload()->video()->make(),
            'audioUpload' => ShmRPC::fileUpload()->audio()->make(),
            'documentUpload' => ShmRPC::fileUpload()->document()->make(),


            'authEmail' =>  ShmRPC::auth(...self::$users)->email()->make(),
            'authSoc' =>  ShmRPC::auth(...self::$users)->soc()->make(),
            'authPhone' => ShmRPC::auth(...self::$users)->msg()->make(),

            'profile' =>  [
                "type" => Shm::structure([

                    'structure' => self::baseStructure(),
                    'data' => Shm::mixed(),
                    'changePassword' => Shm::boolean(),
                    'subAccount' => Shm::boolean(),
                ]),

                'resolve' => function ($root, $args) {


                    Auth::authenticateOrThrow(...self::$users);


                    if (Auth::subAccountAuth()) {

                        $findStructure = SubAccountsSchema::baseStructure();
                    } else {

                        $findStructure = null;


                        foreach (self::$users as $user) {

                            if ($user->collection == Auth::getAuthCollection()) {
                                $findStructure = $user;
                                break;
                            }
                        }
                    }
                    if (!$findStructure) {
                        Response::validation("Ошибка доступа");
                    }


                    $passwordField =  $findStructure->findItemByType(Shm::password());

                    if ($passwordField)
                        $findStructure->items[$passwordField->key]->inAdmin(false);


                    $emailField = $findStructure->findItemByType(Shm::email());
                    $loginField = $findStructure->findItemByType(Shm::login());
                    $phoneField = $findStructure->findItemByType(Shm::phone());


                    if ($emailField) {
                        $findStructure->items[$emailField->key]->editable(false)->setCol(24);
                    }
                    if ($loginField) {
                        $findStructure->items[$loginField->key]->editable(false)->setCol(24);
                    }
                    if ($phoneField) {
                        $findStructure->items[$phoneField->key]->editable(false)->setCol(24);
                    }



                    return [
                        'structure' => $findStructure->json(),
                        'data' => $findStructure->removeOtherItems($findStructure->normalize($findStructure->findOne([
                            '_id' => Auth::subAccountAuth() ? Auth::getSubAccountID() :  Auth::getAuthOwner()
                        ]))),
                        'subAccount' => Auth::subAccountAuth(),
                        'changePassword' => $passwordField ? true : false,
                    ];
                }

            ],

            'updateProfile' => [
                'type' => Shm::structure([
                    "_id" => Shm::ID(),
                    "*" => Shm::mixed(),
                ]),


                'args' => Shm::structure([

                    'values' => Shm::mixed(),

                ]),
                'resolve' => function ($root, $args) {



                    Auth::authenticateOrThrow(...self::$users);


                    if (Auth::subAccountAuth()) {

                        $structure = SubAccountsSchema::baseStructure();
                    } else {

                        $structure = null;


                        foreach (self::$users as $user) {

                            if ($user->collection == Auth::getAuthCollection()) {
                                $structure = $user;
                                break;
                            }
                        }
                    }
                    if (!$structure) {
                        Response::validation("Ошибка доступа");
                    }



                    $root->setType($structure);



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




                    $structure->updateOneWithEvents(
                        [
                            "_id" => Auth::subAccountAuth() ? Auth::getSubAccountID() : Auth::getAuthOwner()
                        ],
                        [
                            '$set' => $values
                        ]
                    );

                    return  $structure->findOne([
                        '_id' => Auth::subAccountAuth() ? Auth::getSubAccountID() : Auth::getAuthOwner()
                    ]);
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
                    'uid' => Shm::string(),
                    'reports' => self::baseStructure(),
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

                        'uid' => self::getUID(),

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
                    'clone' => Shm::ID()->default(null),
                    "collection" => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$users);

                    if (!isset($args['collection'])) {
                        return [
                            "_" => null
                        ];
                    }

                    $structure = self::fullSchema()->findItemByCollection($args['collection']);


                    if (!$structure) {
                        return [
                            "_" => null
                        ];
                    }


                    $root->setType($structure);





                    $clone = $args['clone'] ?? null;
                    if ($clone) {
                        $cloneData = $structure->findOne([
                            '_id' => mDB::id($clone)
                        ]);

                        if ($cloneData) {

                            $cloneData = $structure->removeOtherItems($cloneData);


                            $cloneData = $structure->removeValuesByCriteria(function ($_this) {
                                return  !$_this->editable;
                            }, $cloneData);

                            $cloneData = $structure->normalize($cloneData, true);



                            return $cloneData;
                        }
                    }



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



                    $structure = self::fullSchema()->findItemByCollection($args['collection']);


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
                    "_id" => Shm::string(),
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



                    $structure = self::fullSchema()->findItemByCollection($args['collection']);



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

                    if (!$_limit) {
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


                    if (isset($args['_id']) && $args['_id']) {
                        $pipeline[] = [
                            '$match' => [
                                "_id" => mDB::id($args['_id']),
                            ],
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


                    return  mDB::hashDocuments($result);
                }

            ],

            'data' => [

                'onlyDisplayRelations' => true,
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


                    $structure = self::fullSchema()->findItemByCollection($args['collection']);




                    $structure->inTableThis(true);





                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }


                    $rootType = $root->getType();
                    $rootType->items['data'] = Shm::arrayOf($structure);

                    $root->setType($rootType);

                    $pipeline = $structure->getPipeline();



                    if ($structure->single) {



                        $pipeline = [
                            ...$pipeline,
                            [
                                '$limit' => 1
                            ],
                        ];

                        $result = $structure->aggregate($pipeline)->toArray() ?? null;


                        if (!$result) {

                            return [
                                'data' =>  [$structure->normalize([], true)]
                            ];
                        } else {

                            return  [
                                'data' => $result
                            ];
                        }
                    }



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

                        if ($args['table'] ?? false) {
                            $hideProjection =  $structure->getProjection('inTable');



                            if ($hideProjection) {
                                $pipeline[] = [
                                    '$project' => $hideProjection,
                                ];
                            }
                        }



                        $result = $structure->aggregate($pipeline)->toArray() ?? null;




                        if (!$result) {
                            return [
                                'data' => [],
                            ];
                        } else {



                            return  [
                                'data' => $result,
                                'hash' => mDB::hashDocuments($result),
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





                    return [
                        'data' => $result,
                        'limit' => $args['limit'] ?? 20,
                        'offset' => $args['offset'] ?? 0,
                        'hash' => mDB::hashDocuments($result),
                        'total' => $total,
                    ];
                }

            ],

            'filter' => [
                'type' => self::baseStructure(),
                'args' => [
                    'collection' => Shm::nonNull(Shm::string()),
                ],
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$users);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }

                    $structure = self::fullSchema()->findItemByCollection($args['collection']);

                    return $structure->filterType()->json();
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


                    $structure = self::fullSchema()->findItemByCollection($args['collection']);


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

            'report' => [
                'type' => self::reportResultType(),

                'args' => [
                    "collection" => Shm::nonNull(Shm::string()),
                    'filter' => Shm::mixed(),
                ],
                'resolve' => function ($root, $args) {

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }



                    $structure = self::fullSchema()->findItemByCollection($args['collection']);



                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }

                    $pipelineFilter = [];
                    if (isset($args['filter'])) {


                        $pipelineFilter =  $structure->filterToPipeline($args['filter']);
                    };



                    return  $structure->computedReport(null, [], $pipelineFilter);
                }

            ],



            //   'addManualTag' => [],


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




                    $structure = self::fullSchema()->findItemByCollection($args['collection']);







                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }


                    $rootType = $root->getType();
                    $rootType->items['data'] = Shm::arrayOf($structure);

                    $root->setType($rootType);





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



                    if ($structure->single) {

                        $pipeline = $structure->getPipeline();

                        $pipeline = [
                            ...$pipeline,
                            [
                                '$limit' => 1
                            ],
                        ];

                        $result = $structure->aggregate($pipeline)->toArray() ?? null;

                        $id = $result[0]['_id'] ?? null;



                        if (!$id) {

                            $insert = $structure->insertOneWithEvents($values);

                            if (!$insert) {
                                Response::validation("Ошибка при добавлении данных");
                            }


                            return [
                                'data' => $structure->find([
                                    '_id' => $insert->getInsertedId(),
                                ])
                            ];
                        } else {

                            $structure->updateManyWithEvents(
                                [
                                    "_id" => $id,
                                ],
                                [
                                    '$set' => $values
                                ]
                            );


                            return [
                                'data' => $structure->find([
                                    '_id' => $id
                                ]),
                            ];
                        }
                    }





                    if ($ids) {



                        $structure->updateManyWithEvents(
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
                        $insert =  $structure->insertOneWithEvents($values);

                        if (!$insert) {
                            Response::validation("Ошибка при добавлении данных");
                        }

                        $result = $structure->findOne([
                            '_id' => $insert->getInsertedId(),
                        ]);

                        return [
                            'data' => [$result],
                        ];
                    }
                }

            ],

            'runAction' => [
                'type' => Shm::structure([
                    'payload' => Shm::mixed(),
                ]),
                'args' => [
                    '_ids' => Shm::IDs(),
                    'collection' => Shm::nonNull(Shm::string()),
                    'action' => Shm::nonNull(Shm::string()),
                    '*' => Shm::mixed()
                ],

                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$users);

                    $structure = self::fullSchema()->findItemByCollection($args['collection']);

                    if (!$structure) {
                        Response::validation("Данные не доступны");
                    }

                    $buttonAction = $structure->findButtonAction($args['action']);

                    if (!$buttonAction) {
                        Response::validation("Действие не найдено");
                    }

                    $payload =  $buttonAction->computed($args);
                    return [
                        'payload' => $payload
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

                    Auth::authenticateOrThrow(...self::$users);

                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для просмотра");
                    }



                    $structure = self::fullSchema()->findItemByCollection($args['collection']);




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
                        if ($stage instanceof StageType) {
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
            ],









        ]);
    }
}
