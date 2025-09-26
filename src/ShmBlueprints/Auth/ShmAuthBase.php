<?php

namespace Shm\ShmBlueprints\Auth;

use Sentry\Util\Str;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmUtils\Inflect;
use Shm\ShmUtils\Response;
use Shm\ShmTypes\StructureType;

class ShmAuthBase
{

    public $title;
    public $key;

    /**
     * @var StructureType[]
     * Список структур, которые поддерживают авторизацию
     */
    public $authStructures = null;
    public $description;
    public $pipeline;


    public function currentStructure(): ?StructureType
    {
        $currentAuthStructure = null;
        foreach ($this->authStructures as $authStructure) {
            if ($authStructure->collection == Auth::getAuthCollection()) {
                $currentAuthStructure = $authStructure;
                break;
            }
        }


        if (!$currentAuthStructure) {
            Response::unauthorized();
        }

        return $currentAuthStructure;
    }


    public  function hasValueValidator($keys, $params)
    {

        foreach ($keys as $key) {
            if (!isset($params[$key])) {


                Response::validation("Заполните все необходимые поля");
            }
        }
    }

    public $errorAccountNotFound = "Ваша учетная запись не найдена.";


    public function __construct(StructureType ...$authStructures)
    {


        $this->authStructures = $authStructures;



        return $this;
    }

    public function deviceInfoStructure()
    {
        return Shm::structure([
            'name' => Shm::string(),
            'model' => Shm::string(),
            'platform' => Shm::string(),
            'uuid' => Shm::string(),
        ]);
    }

    public function  authToken(StructureType $structure,  $_id, $args): string
    {

        $deviceInfo = $args['deviceInfo'] ?? null;
        if ($deviceInfo) {

            try {

                mDB::_collection("devices")->updateOne(
                    [
                        ...$deviceInfo,
                        'user' => mDB::id($_id),
                    ],
                    [
                        '$set' => [
                            ...$deviceInfo,
                            'auth_collection' => $structure->collection,
                            'user' => mDB::id($_id)

                        ],
                    ],
                    [
                        'upsert' => true,
                    ]
                );
            } catch (\Exception $e) {
                \Sentry\captureException($e);
                $deviceInfo = null;
            }
        }

        return Auth::genToken($structure, $_id);
    }




    public function make(): ?array
    {
        return [];
    }
}
