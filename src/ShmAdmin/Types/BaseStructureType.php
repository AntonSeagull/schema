<?php


namespace Shm\ShmAdmin\Types;

use Shm\Shm;
use Shm\ShmTypes\StructureType;

class BaseStructureType extends StructureType
{

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
        'balance',
        'action'
    ];



    public static function get(): StructureType
    {
        $type = Shm::structure([
            "collection" => Shm::string(),
            "key" => Shm::string(),
            'itemType' => Shm::selfRef(function () use (&$type) {
                return $type;
            }),



            'codeLang' => Shm::string(), //for CodeType
            'manualSort' => Shm::bool(),

            'filterPresets' => Shm::arrayOf(Shm::structure([
                "key" => Shm::string(),
                "title" => Shm::string(),
                "filter" => Shm::mixed(),
            ])),



            'args' => Shm::selfRef(function () use (&$type) {
                return $type;
            }),




            'actionPosition' => Shm::enum(['sidebar', 'inline', 'table']),

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
}
