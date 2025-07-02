<?php

namespace Shm\ShmAuth;

use Shm\ShmDB\mDB;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\Response;

class Auth
{



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



    private static $authOwner = null;
    private static string | null $authCollection = null;

    private static $apikeyOwner = null;
    private static string | null $apikeyCollection = null;

    private static $token_collection = "_tokens";
    private static $apikey_collection = "_apikeys";


    private static $initialized = false;


    private static function init()
    {

        $token = self::getRequestKey(['token', 'authorization', 'x-auth-token']);


        $apikey = self::getRequestKey(['apikey', 'x-api-key', 'api-key']);

        if ($token) {

            $findToken = mDB::collection(self::$token_collection)->findOne([
                "token" => $token,
            ]);

            if ($findToken) {
                self::$apikeyCollection = $findToken->collection;
                self::$authOwner = $findToken->owner;
            }
        }

        if ($apikey) {

            $findApiKey = mDB::collection(self::$apikey_collection)->findOne([
                "apikey" => $apikey,
            ]);

            if ($findApiKey) {
                self::$apikeyCollection = $findApiKey->collection;
                self::$apikeyOwner = $findApiKey->owner;
            }
        }
        self::$initialized = true;
    }



    public static function authenticateOrThrow(StructureType ...$authStructures)
    {



        if ($authStructures) {

            foreach ($authStructures as $structure) {

                if ($structure->collection &&  $structure->collection == self::$authCollection) {
                    return false;
                }
            }

            Response::unauthorized();
        }

        if (!self::getAuthOwner()) {
            Response::unauthorized();
        }
    }



    public static function getAuthOwner(): mixed
    {
        if (!self::$initialized) {
            self::init();
        }


        return self::$authOwner ?? null;
    }


    public static function getApiKeyOwner(): mixed
    {
        if (!self::$initialized) {
            self::init();
        }


        return self::$apikeyOwner ?? null;
    }

    public static function getAuthCollection(): ?string
    {
        if (!self::$initialized) {
            self::init();
        }

        return self::$authCollection;
    }

    public static function getApiKeyCollection(): ?string
    {
        if (!self::$initialized) {
            self::init();
        }

        return self::$apikeyCollection;
    }





    public static function genApiKey(StructureType $structure,  $_id): string
    {

        $apikey =  hash("sha512", $_id . time() . bin2hex(openssl_random_pseudo_bytes(64)));

        mDB::collection(self::$apikey_collection)->insertOne([
            "apikey" => $apikey,
            'collection' => $structure->collection,
            "agent" => $_SERVER['HTTP_USER_AGENT'] ?? null,
            "ip" => $_SERVER['REMOTE_ADDR'] ?? null,
            "owner" => mDB::id($_id),
        ]);

        return $apikey;
    }


    public static function getToken(StructureType $structure,  $_id): string
    {

        $token =  hash("sha512", $_id . time() . bin2hex(openssl_random_pseudo_bytes(64)));

        mDB::collection(self::$token_collection)->insertOne([
            "token" => $token,
            'collection' => $structure->collection,
            "agent" => $_SERVER['HTTP_USER_AGENT'] ?? null,
            "ip" => $_SERVER['REMOTE_ADDR'] ?? null,
            "owner" => mDB::id($_id),
        ]);


        return $token;
    }

    public static function getPassword($password)
    {
        $hash = hash("sha512", $password);

        return $hash;
    }
}
