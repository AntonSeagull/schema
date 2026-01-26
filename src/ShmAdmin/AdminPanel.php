<?php

namespace Shm\ShmAdmin;

use GraphQL\Type\Schema;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAdmin\AdminRPC\AdminRPCAnalytics;
use Shm\ShmAdmin\AdminRPC\AdminRPCProfile;
use Shm\ShmAdmin\AdminRPC\AdminRPCUpdateProfile;
use Shm\ShmAdmin\AdminRPC\AdminRPCInit;
use Shm\ShmAdmin\AdminRPC\AdminRPCMenu;
use Shm\ShmAdmin\AdminRPC\AdminRPCDisplayValues;
use Shm\ShmAdmin\AdminRPC\AdminRPCData;


use Shm\ShmAdmin\AdminRPC\AdminRPCCollection;
use Shm\ShmAdmin\AdminRPC\AdminRPCEmptyData;
use Shm\ShmAdmin\AdminRPC\AdminRPCDashboard;
use Shm\ShmAdmin\AdminRPC\AdminRPCGeocode;
use Shm\ShmAdmin\AdminRPC\AdminRPCDeleteData;
use Shm\ShmAdmin\AdminRPC\AdminRPCHash;
use Shm\ShmAdmin\AdminRPC\AdminRPCDeleteExport;
use Shm\ShmAdmin\AdminRPC\AdminRPCMakeExport;
use Shm\ShmAdmin\AdminRPC\AdminRPCMakeStatementExport;
use Shm\ShmAdmin\AdminRPC\AdminRPCListExport;
use Shm\ShmAdmin\AdminRPC\AdminRPCFilter;
use Shm\ShmAdmin\AdminRPC\AdminRPCApiKeys;
use Shm\ShmAdmin\AdminRPC\AdminRPCRemoveApiKey;
use Shm\ShmAdmin\AdminRPC\AdminRPCNewApiKey;
use Shm\ShmAdmin\AdminRPC\AdminRPCMoveUpdate;

use Shm\ShmAdmin\AdminRPC\AdminRPCUpdate;
use Shm\ShmAdmin\AdminRPC\AdminRPCRunAction;
use Shm\ShmAdmin\AdminRPC\AdminRPCFilterPresetTotal;

use Shm\ShmAdmin\AdminRPC\AdminRPCGeneratePaymentLink;
use Shm\ShmAdmin\AdminRPC\AdminRPCLastBalanceOperations;

use Shm\ShmAdmin\SchemaCollections\ShmExportCollection;
use Shm\ShmAdmin\SchemaCollections\SubAccountsSchema;
use Shm\ShmAdmin\ShmAdminRPC\RPCCompositeTypes;
use Shm\ShmAdmin\ShmAdminRPC\ShmAdminRPCCompositeTypes;
use Shm\ShmAdmin\Types\AdminType;
use Shm\ShmAdmin\Types\GroupType;
use Shm\ShmAdmin\Utils\AdminHTML;
use Shm\ShmAdmin\Utils\DescriptionsUtils;
use Shm\ShmAuth\Auth;

use Shm\ShmRPC\ShmRPC;
use Shm\ShmRPC\ShmRPCClient\ShmRPCClient;
use Shm\ShmSupport\ShmSupport;
use Shm\ShmTypes\CompositeTypes\BalanceTypes\BalanceUtils;
use Shm\ShmTypes\DashboardType;
use Shm\ShmTypes\StructureType;

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







    public static function rpc()
    {

        ShmInit::$isAdmin = true;

        if (!isset($_GET['schema']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
            header_remove("X-Frame-Options");
            $html = AdminHTML::html();

            header('Content-Type: text/html; charset=utf-8');
            echo $html;
            exit;
        }


        ShmRPC::init([

            'compositeTypes' => ShmRPC::lazy(function () {
                return [
                    'type' => Shm::structure([
                        'geoPoint' => Shm::geoPoint(),
                        'geoRegion' => Shm::geoRegion(),
                        'gradient' => Shm::gradient(),


                    ]),
                ];
            }),

            'geolocation' => ShmRPC::lazy(function () {
                return ShmRPC::IPGeolocation();
            }),

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



            'allCollections' => AdminRPCAnalytics::allCollectionsRpc(),




            'menu' => AdminRPCMenu::rpc(),



            'displayValues' => AdminRPCDisplayValues::rpc(),


            'collection' => AdminRPCCollection::rpc(),
            'emptyData' => AdminRPCEmptyData::rpc(),

            'dashboard' => AdminRPCDashboard::rpc(),


            'geocode' => AdminRPCGeocode::rpc(),



            'deleteData' => AdminRPCDeleteData::rpc(),




            'hash' => AdminRPCHash::rpc(),
            'data' => AdminRPCData::rpc(),



            'deleteExport' => AdminRPCDeleteExport::rpc(),

            'makeExport' => AdminRPCMakeExport::rpc(),

            'makeStatementExport' => AdminRPCMakeStatementExport::rpc(),

            'listExport' => AdminRPCListExport::rpc(),

            'filter' => AdminRPCFilter::rpc(),

            'apikeys' => AdminRPCApiKeys::rpc(),

            'removeApiKey' => AdminRPCRemoveApiKey::rpc(),

            'newApiKey' => AdminRPCNewApiKey::rpc(),


            'moveUpdate' => AdminRPCMoveUpdate::rpc(),




            'update' => AdminRPCUpdate::rpc(),

            'runAction' => AdminRPCRunAction::rpc(),

            'stagesTotal' => AdminRPCFilterPresetTotal::rpc(),




            'generatePaymentLink' => AdminRPCGeneratePaymentLink::rpc(),


            'lastBalanceOperations' => AdminRPCLastBalanceOperations::rpc(),



        ]);
    }
}
