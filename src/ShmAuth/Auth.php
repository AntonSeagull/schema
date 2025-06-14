<?php

namespace Shm\ShmAuth;

use F3Mongo\mDB;
use Shm\Types\StructureType;

class Auth
{


    /**
     * @var StructureType[]
     * Список коллекций, которые поддерживают авторизацию
     */
    private static $authStructures = [];

    /**
     * @var StructureType[]
     * Структура для API ключа
     */
    public static $apiStructure  = [];


    public static function addApiKeyStructure(StructureType $structure): void
    {
        if (!$structure instanceof StructureType) {
            throw new \InvalidArgumentException("Structure must be an instance of StructureType.");
        }

        if (!isset($structure->document)) {
            throw new \InvalidArgumentException("Structure must have a document defined.");
        }

        self::$apiStructure = $structure;
    }

    public  static function addStructure(StructureType $structure): void
    {

        if (!$structure instanceof StructureType) {
            throw new \InvalidArgumentException("Structure must be an instance of StructureType.");
        }

        if (!isset($structure->document)) {
            throw new \InvalidArgumentException("Structure must have a document defined.");
        }

        self::$authStructures[] = $structure;
    }

    private static function  getRequestKey(array $keys): ?string
    {
        foreach ($keys as $key) {

            if (isset($_REQUEST[$key])) {
                return  $_REQUEST[$key];
            }


            $data = json_decode(file_get_contents('php://input'), true);
            if ($data && isset($data[$key])) {
                return $data[$key];
            }

            $headers = getallheaders();
            if (isset($headers[$key])) {
                return $headers[$key];
            } else if (isset($headers[strtolower($key)])) {
                return $headers[strtolower($key)];
            } else if (isset($headers[strtoupper($key)])) {
                return $headers[strtoupper($key)];
            }
        }
        return null;
    }



    private static $auth = null;
    private static StructureType $authStructure;

    private static $apikey;
    private static StructureType $apikeyStructure;

    private static $token_collection = "_tokens";


    private static $initialized = false;

    private static function init()
    {

        $token = self::getRequestKey(['token', 'authorization', 'x-auth-token']);
        $apikey = self::getRequestKey(['apikey', 'x-api-key', 'api-key']);

        if ($token) {
            foreach (self::$authStructures as $structure) {

                $findToken = mDB::collection(self::$token_collection)->findOne([
                    "token" => $token,
                ]);

                if ($findToken) {

                    self::$authStructure = $structure;
                    self::$auth = mDB::collection(self::$authStructure->collection)->findOne([
                        "_id" => $findToken['user_id'],
                    ]);

                    break;
                }
            }
        }

        if ($apikey) {

            foreach (self::$apikeyStructure as $structure) {


                $findApiKey = mDB::collection($structure->collection)->findOne([
                    "apikey" => $apikey,
                ]);

                if ($findApiKey) {

                    self::$apikeyStructure = $structure;
                    self::$apikey = $findApiKey;

                    break;
                }
            }
        }
        self::$initialized = true;
    }


    public static function getAuth(): ?array
    {

        if (!self::$initialized) {
            self::init();
        }

        if (self::$auth !== null) {
            return self::$auth;
        }

        return null;
    }

    public static function getApiKey(): ?object
    {

        if (!self::$initialized) {
            self::init();
        }

        if (self::$apikey !== null) {
            return self::$apikey;
        }

        return null;
    }

    public static function getAuthStructure(): ?StructureType
    {

        if (!self::$initialized) {
            self::init();
        }

        if (self::$authStructure !== null) {
            return self::$authStructure;
        }

        return null;
    }

    public static function getApiKeyStructure(): ?StructureType
    {

        if (!self::$initialized) {
            self::init();
        }

        if (self::$apikeyStructure !== null) {
            return self::$apikeyStructure;
        }

        return null;
    }


    public static function getToken($_id): string
    {

        $token =  hash("sha512", $_id . time() . bin2hex(openssl_random_pseudo_bytes(64)));

        mDB::collection(self::$token_collection)->insertOne([
            "token" => $token,
            "agent" => $_SERVER['HTTP_USER_AGENT'] ?? null,
            "ip" => $_SERVER['REMOTE_ADDR'] ?? null,
            "user_id" => mDB::id($_id),
        ]);


        return $token;
    }
}
