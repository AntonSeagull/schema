<?php

namespace Shm\ShmAdmin;

use GraphQL\Type\Schema;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAdmin\AdminRPC\AdminRPCProfile;
use Shm\ShmAdmin\AdminRPC\AdminRPCUpdateProfile;
use Shm\ShmAdmin\AdminRPC\AdminRPCInit;
use Shm\ShmAdmin\AdminRPC\AdminRPCMenu;
use Shm\ShmAdmin\AdminRPC\AdminRPCDisplayValues;
use Shm\ShmAdmin\AdminRPC\AdminRPCData;
use Shm\ShmAdmin\SchemaCollections\ShmExportCollection;
use Shm\ShmAdmin\SchemaCollections\SubAccountsSchema;
use Shm\ShmAdmin\ShmAdminRPC\RPCCompositeTypes;
use Shm\ShmAdmin\ShmAdminRPC\ShmAdminRPCCompositeTypes;
use Shm\ShmAdmin\Types\AdminType;
use Shm\ShmAdmin\Types\GroupType;
use Shm\ShmAdmin\Utils\DescriptionsUtils;
use Shm\ShmAuth\Auth;

use Shm\ShmRPC\ShmRPC;
use Shm\ShmRPC\ShmRPCClient\ShmRPCClient;
use Shm\ShmSupport\ShmSupport;
use Shm\ShmTypes\CompositeTypes\BalanceTypes\BalanceUtils;
use Shm\ShmTypes\DashboardType;
use Shm\ShmTypes\StructureType;
use Shm\ShmTypes\SupportTypes\StageType;
use Shm\ShmUtils\Config;
use Shm\ShmUtils\DisplayValuePrepare;
use Shm\ShmUtils\Inflect;
use Shm\ShmUtils\MaterialIcons;
use Shm\ShmUtils\RedisCache;
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
    public static array $authStructures = [];


    /**
     * @var StructureType[]
     */
    public static array $regStructures = [];

    /**
     * @param AdminType $schema
     */
    public static function setSchema(AdminType $schema)
    {
        self::$schema = $schema->type("admin");
    }

    public static function setAuthStructures(array $authStructures): void
    {



        self::$authStructures = [
            ...$authStructures,
            SubAccountsSchema::baseStructure()
        ];
    }

    public static function setRegStructure(array  $regStructures): void
    {
        self::$regStructures = $regStructures;
    }

    public static function fullSchema(): StructureType
    {

        $schema = self::$schema;


        if (!Auth::subAccountAuth()) {
            $schema->addField("subAccounts", SubAccountsSchema::structure(self::$schema));
        } else {

            $schema = SubAccountsSchema::removeLockItemInSchema($schema);
        }


        return $schema;
    }

    public static function findCurrentAuthStructure(): ?StructureType
    {
        if (! Auth::isAuthenticated()) return null;

        if (Auth::subAccountAuth()) {
            return SubAccountsSchema::baseStructure();
        }

        foreach (self::$authStructures as $user) {

            if ($user->collection == Auth::getAuthCollection()) {
                return $user;
            }
        }

        return null;
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
        'geoRegion',
        'balance'
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

    public static function baseStructure(): StructureType
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


            'columnsWidth' => Shm::float(),

            'values' => Shm::structure([
                "*" => Shm::string()
            ]),

            'haveCalculateFunction' => Shm::boolean(),


            'dashboardBlockType' => Shm::enum(['card', 'lineChart', 'pieChart', 'barChart']),

            'gateways' => Shm::structure([
                "minAmount" => Shm::float(),
                "maxAmount" => Shm::float(),
                "title" => Shm::string(),
                "description" => Shm::string(),
                "icon" => Shm::string(),
                "key" => Shm::string(),
            ]),

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
            'currency' => Shm::string(),
            'currencySymbol' => Shm::string(),
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
                'terms' => Shm::string(),
                'privacy' => Shm::string(),
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

        return Auth::getAuthCollection() . ':' . Auth::getAuthID();
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
                    'gradient' => Shm::gradient(),


                ]),
            ],

            'geolocation' => ShmRPC::IPGeolocation(),

            'imageUpload' => ShmRPC::fileUpload()->image(),
            'videoUpload' => ShmRPC::fileUpload()->video(),
            'audioUpload' => ShmRPC::fileUpload()->audio(),
            'documentUpload' => ShmRPC::fileUpload()->document(),


            'authEmail' =>  ShmRPC::auth()->email()->auth(self::$authStructures)->reg(self::$regStructures),
            'authEmailPrepare' =>  ShmRPC::auth()->email()->auth(self::$authStructures)->reg(self::$regStructures)->prepare(),
            'authSoc' =>  ShmRPC::auth()->soc()->auth(self::$authStructures)->reg(self::$regStructures),
            'authPhone' => ShmRPC::auth()->msg()->auth(self::$authStructures)->reg(self::$regStructures),

            'profile' => AdminRPCProfile::rpc(),

            'updateProfile' => AdminRPCUpdateProfile::rpc(),


            'init' => AdminRPCInit::rpc(),






            'menu' => AdminRPCMenu::rpc(),

            'collectionRelations' => [
                'args' => Shm::structure([
                    "collection" => Shm::nonNull(Shm::string()),
                ]),
                'type' => Shm::arrayOf(Shm::structure([
                    'key' => Shm::string(),
                    'icon' => Shm::string(),
                    'title' => Shm::string(),
                    'foreignCollection' => Shm::string(),
                    'foreignKey' => Shm::string(),
                ])),
                'resolve' => function ($root, $args) {

                    if (!isset($args['collection'])) {
                        return [];
                    }

                    $structure = self::fullSchema()->findItemByCollection($args['collection']);

                    if (!$structure) {
                        return [];
                    }

                    return [];
                }
            ],

            'collectionMenu' => [
                'type' => Shm::structure([
                    'menu' => Shm::arrayOf(Shm::structure([
                        'key' => Shm::string(),
                        'icon' => Shm::string(),
                        'title' => Shm::string(),
                        'group' => Shm::string(),
                        'children' => Shm::arrayOf(Shm::structure([
                            'key' => Shm::string(),
                            'icon' => Shm::string(),
                            'title' => Shm::string(),
                            'group' => Shm::string(),
                        ])),
                    ])),
                    'allItems' => Shm::arrayOf(Shm::structure([
                        'key' => Shm::string(),
                        'icon' => Shm::string(),
                        'title' => Shm::string(),
                        'group' => Shm::string(),
                    ])),
                ]),
                'args' => Shm::structure([
                    "collection" => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {

                    if (!isset($args['collection'])) {
                        return [];
                    }

                    $structure = self::fullSchema()->findItemByCollection($args['collection']);


                    if (!$structure) {
                        return [];
                    }



                    $groups = [];

                    $allItems = [];

                    foreach ($structure->items as $item) {


                        if ($item->inAdmin) {

                            $group = [
                                'key' => $item->group['key'] ?? null,
                                'group' => $item->group['key'] ?? null,
                                'icon' => $item->group['icon'] ?? MaterialIcons::FolderTableOutline(),
                                'title' => $item->group['title'] ?? null,
                            ];
                            if ($item->group['key'] == 'default') {
                                $group['title'] = "Общее";
                            }


                            $groups[$group['key']] = $group;
                        }
                    }

                    $allItems = array_values($groups);



                    if ($structure->buttonActions) {

                        //   foreach ($structure->buttonActions?->items as $buttonAction) {

                        //      var_dump($buttonAction);
                        //      exit;
                        //  }
                    }




                    return [

                        'menu' => [[
                            'key' => 'content',
                            'title' => 'Контент',
                            'children' => array_values($groups),
                        ]],
                        'allItems' => $allItems,
                    ];
                }
            ],

            'displayValues' => AdminRPCDisplayValues::rpc(),


            'collection' => [
                'type' => self::baseStructure(),
                'args' => Shm::structure([
                    "collection" => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {

                    //   Auth::authenticateOrThrow(...self::$authStructures);

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

                    return $structure->json();
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

                    Auth::authenticateOrThrow(...self::$authStructures);

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

            'dashboard' => [
                'type' => Shm::arrayOf(Shm::structure([
                    'label' => Shm::mixed(),
                    'value' => Shm::mixed(),
                ])),
                'args' => [
                    'dashboardKey' => Shm::nonNull(Shm::string()),
                    'dashboardField' => Shm::nonNull(Shm::string()),
                ],
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$authStructures);

                    $dashboardKey = $args['dashboardKey'];
                    $dashboardField = $args['dashboardField'];

                    $structure = self::fullSchema()->deepFindItemByKey($dashboardKey);


                    if (!$structure) {
                        Response::validation("Данные не доступны для просмотра");
                    }

                    $dashboardItem = $structure->findItemByKey($dashboardField);

                    if (!$dashboardItem || $dashboardItem->type != 'dashboard') {
                        Response::validation("Данные не доступны для просмотра");
                    }

                    if ($dashboardItem instanceof DashboardType) {



                        $cacheKey = md5($dashboardKey . ' ' . $dashboardField . ' ' . Auth::getAuthID() . ' ' . Auth::getSubAccountID());

                        $cache = RedisCache::get($cacheKey);
                        if ($cache) {
                            return json_decode($cache, true);
                        }




                        $result = $dashboardItem->executeCalculateFunction();
                        if ($result) {
                            RedisCache::set($cacheKey, json_encode($result), 60);
                        }
                        return $result;
                    } else {
                        Response::validation("Данные не доступны для просмотра");
                    }
                }
            ],


            'geocode' =>  [

                "type" => Shm::arrayOf(Shm::geoPoint()),
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

                    Auth::authenticateOrThrow(...self::$authStructures);

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

                    $geocodeSet = false;

                    if ($byCoordsLat && $byCoordsLon) {
                        $queryParams['geocode'] = $byCoordsLon . ',' . $byCoordsLat;
                        $geocodeSet = true;
                    } elseif ($byString) {
                        $queryParams['geocode'] = $byString;
                        $geocodeSet = true;
                        if ($byStringLat && $byStringLon) {
                            $queryParams['ll'] = $byStringLon . ',' . $byStringLat;
                        }
                    }

                    if (!$geocodeSet) return [];

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
                                'uuid' => md5($name . $description . $cord[0] . $cord[1]),
                                'name' => $name,
                                'address' => $name,
                                'context' => $description,
                                'lat' => +$cord[1],
                                'lng' => +$cord[0],
                                'meta' => [
                                    'label' => null,
                                    'floor' => null,
                                    'entrance' => null,
                                    'apartment' => null,
                                    'comment' => null,
                                    'phone' => null,
                                ],
                                'location' => [
                                    'type' => 'Point',
                                    'coordinates' => [
                                        +$cord[0],
                                        +$cord[1]
                                    ]
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

                    Auth::authenticateOrThrow(...self::$authStructures);

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
                    "_id" => Shm::ID(),
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



                    Auth::authenticateOrThrow(...self::$authStructures);

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

            'data' => AdminRPCData::rpc(),



            'deleteExport' => [
                'type' => Shm::bool(),
                'args' => Shm::structure([
                    "_id" => Shm::ID()->default(null),
                ]),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$authStructures);

                    $_id = $args['_id'] ?? null;

                    if (!$_id) {
                        Response::validation("Нет данных для удаления");
                    }

                    $export = ShmExportCollection::findOne([
                        '_id' => mDB::id($_id)
                    ]);
                    if (!$export) {
                        Response::validation("Экспорт не найден");
                    }
                    ShmExportCollection::structure()->deleteOne([
                        '_id' => mDB::id($_id)
                    ]);

                    if (file_exists($export['filePath'])) {
                        unlink($export['filePath']);
                    }

                    return true;
                }
            ],

            'makeExport' => [

                'type' => Shm::bool(),
                'args' => Shm::structure([
                    'ids' => Shm::IDs(),
                    'title' => Shm::string(),
                    "collection" => Shm::nonNull(Shm::string()),
                    'filter' => Shm::mixed(),
                    'stage' => Shm::string()
                ]),
                'resolve' => function ($root, $args) {



                    Auth::authenticateOrThrow(...self::$authStructures);


                    $currentExport = ShmExportCollection::findOne([
                        'type' => 'data',
                        'status' => ['$in' => ['pending', 'processing']]
                    ]);

                    if ($currentExport) {
                        Response::validation("У вас уже есть активный экспорт. Подождите пока он завершится.");
                    }


                    if (!isset($args['title']) || !$args['title']) {
                        Response::validation("Не указано название экспорта");
                    } else {
                        $args['title'] = trim($args['title']);
                    }


                    if (!isset($args['collection'])) {
                        Response::validation("Данные не доступны для экспорта");
                    }


                    $structure = self::fullSchema()->findItemByCollection($args['collection']);




                    $structure->inTableThis(true);


                    if ($structure->single) {

                        Response::validation("Данные не доступны для экспорта");
                    }


                    if (!$structure) {
                        Response::validation("Данные не доступны для экспорта");
                    }


                    $rootType = $root->getType();
                    $rootType->items['data'] = Shm::arrayOf($structure);

                    $root->setType($rootType);

                    $pipeline = $structure->getPipeline();

                    if (isset($args['ids']) && count($args['ids']) > 0) {

                        $ids = array_map(function ($id) {
                            return mDB::id($id);
                        }, $args['ids']);

                        $pipeline = [
                            ...$pipeline,
                            [
                                '$match' => [
                                    '_id' => ['$in' => $ids]
                                ],
                            ],
                        ];
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




                    if (isset($args['filter'])) {


                        $pipelineFilter =  $structure->filterToPipeline($args['filter']);




                        if ($pipelineFilter) {

                            $pipeline = [
                                ...$pipeline,
                                ...$pipelineFilter,
                            ];
                        }
                    };



                    $timezone = date_default_timezone_get();

                    $fileName = 'export_' . $structure->collection . '_' . $timezone . '_' . date('d_m_Y_H_i') . '_' . md5($args['title']) . '.xlsx';


                    //Remove special chars from filename $fileName
                    $fileName = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $fileName);

                    $filePathDir = ShmInit::$rootDir . '/storage/exports';
                    if (!file_exists($filePathDir)) {
                        mkdir($filePathDir, 0755, true);
                    }








                    ShmExportCollection::insertOne([
                        'type' => 'data',
                        'timezone' => $timezone,
                        'filePath' => $filePathDir . '/' . $fileName,
                        'fileName' => $fileName,
                        'title' => $args['title'],
                        'token' => Auth::$currentRequestToken,
                        'collection' => $structure->collection,
                        'pipeline' => $pipeline,
                        'status' => 'pending',
                    ]);




                    return true;
                }

            ],

            'makeStatementExport' => [

                'type' => Shm::bool(),
                'args' => Shm::structure([
                    'currency' => Shm::nonNull(Shm::string()),
                ]),
                'resolve' => function ($root, $args) {



                    Auth::authenticateOrThrow(...self::$authStructures);


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

            ],

            'listExport' => [
                'type' => Shm::arrayOf(ShmExportCollection::structure()),
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$authStructures);

                    return ShmExportCollection::find([], [
                        'sort' => ['_id' => -1],
                        'limit' => 3,
                    ]);
                }
            ],

            'filter' => [
                'type' => self::baseStructure(),
                'args' => [
                    'collection' => Shm::nonNull(Shm::string()),
                ],
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...self::$authStructures);

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

                    Auth::authenticateOrThrow(...self::$authStructures);

                    $apikeys = mDB::collection(Auth::$apikey_collection)->find(
                        [
                            'owner' => Auth::getAuthID(),
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
                    '_id' => Shm::ID(),
                ],
                'resolve' => function ($root, $args) {


                    Auth::authenticateOrThrow(...self::$authStructures);

                    $apikeyId = $args['_id'] ?? null;

                    if (!$apikeyId) {
                        Response::validation("Не указан ID ключа");
                    }

                    $apikey = mDB::collection(Auth::$apikey_collection)->findOne([
                        '_id' => mDB::id($apikeyId),
                        'owner' => Auth::getAuthID(),
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

                    Auth::authenticateOrThrow(...self::$authStructures);

                    $title = $args['title'] ?? null;

                    if (!$title) {
                        Response::validation("Не указано название ключа");
                    }

                    $apikey = Auth::genApiKey($title, Auth::getAuthCollection(), Auth::getAuthID());

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

                    Auth::authenticateOrThrow(...self::$authStructures);

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

                    Auth::authenticateOrThrow(...self::$authStructures);

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

                            $insert = $structure->insertOne($values);

                            if (!$insert) {
                                Response::validation("Ошибка при добавлении данных");
                            }


                            return [
                                'data' => $structure->find([
                                    '_id' => $insert->getInsertedId(),
                                ])
                            ];
                        } else {

                            $structure->updateMany(
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

                    Auth::authenticateOrThrow(...self::$authStructures);

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

                    Auth::authenticateOrThrow(...self::$authStructures);

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


                    $fieldDescription = mDB::collection("_adminDescriptions")->findOne([
                        "ownerCollection" => Auth::getAuthCollection()
                    ]);


                    return $fieldDescription;
                }
            ],

            'generatePaymentLink' => [
                'type' => Shm::string(),
                'args' => Shm::structure([
                    'currency' =>  Shm::nonNull(Shm::string()),
                    'gateway' => Shm::nonNull(Shm::string()),
                    'amount' => Shm::nonNull(Shm::float()),
                ]),
                'resolve' => function ($root, $args) {

                    /*                    Auth::authenticateOrThrow(...self::$authStructures);

                    $findStructure = self::findCurrentAuthStructure();

                    if (!$findStructure) {
                        Response::validation("Ошибка оплаты. Попробуйте позже");
                    }

                    $amount = $args['amount'] ?? 0;
                    $currency = $args['currency'] ?? null;

                    if ($amount <= 0) {
                        Response::validation("Сумма должна быть больше нуля");
                    }

                    if (!in_array($currency, StructureType::ALLOWED_CURRENCIES)) {
                        Response::validation("Валюта не поддерживается");
                    }


                    return $findStructure->getCurrency($currency)?->getGateway($args['gateway'])?->generatePaymentLink(Auth::getAuthID(), $amount);*/
                }
            ],


            'lastBalanceOperations' => [
                'type' =>  Shm::arrayOf(
                    BalanceUtils::balancePaymentsStructure()
                ),
                'resolve' => function ($root, $args) {
                    Auth::authenticateOrThrow(...self::$authStructures);

                    $findStructure = self::findCurrentAuthStructure();

                    if (!$findStructure) {
                        return null;
                    }

                    $collection = $findStructure->collection;
                    $key = Inflect::singularize($collection);

                    $filter = [
                        '$or' => [
                            [$key  => Auth::getAuthID()],
                            ['manager' => Auth::getAuthID()],
                        ],   // в платежи ты писал $user->_id

                        'deleted_at' => ['$exists' => false],
                    ];

                    // сортировка и лимит (последние по времени)
                    $options = [
                        'sort'   => ['created_at' => -1],
                        'limit'  => 100,
                    ];

                    $cursor = mDB::collection($collection . '_payments')->find($filter, $options);


                    return iterator_to_array($cursor, false);
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
                    Auth::authenticateOrThrow(...self::$authStructures);



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


        ]);
    }
}
