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


    private static $authOwnerData = null;


    private static $authOwnerDataLoad = false;

    private static $authOwner = null;
    private static string | null $authCollection = null;


    private static $apikeyOwnerData = null;

    private static $apikeyOwnerDataLoad = false;

    private static $apikeyOwner = null;
    private static string | null $apikeyCollection = null;

    public static $token_collection = "_tokens";
    public static $apikey_collection = "_apikeys";




    private static $initialized = false;


    private static function init()
    {

        $token = self::getRequestKey(['token', 'authorization', 'x-auth-token']);


        $apikey = self::getRequestKey(['apikey', 'x-api-key', 'api-key']);

        if ($token) {




            $findToken = mDB::_collection(self::$token_collection)->findOneAndUpdate(
                [
                    "token" => $token,
                ],
                [
                    '$set' => [
                        'last_used' => time(),
                    ],
                ]
            );

            if ($findToken && isset($findToken->collection) && isset($findToken->owner)) {
                self::$authCollection = $findToken->collection;
                self::$authOwner = $findToken->owner;
            }
        }

        if ($apikey) {

            $findApiKey = mDB::_collection(self::$apikey_collection)->findOneAndUpdate(
                [
                    "apikey" => $apikey,
                ],
                [
                    '$set' => [
                        'last_used' => time(),
                    ],
                ]
            );

            if ($findApiKey && isset($findApiKey->collection) && isset($findApiKey->owner)) {
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


    public static function getAuthOwnerField(string $key, $default = null): mixed
    {
        if (!self::$initialized) {
            self::init();
        }

        if (self::$authOwner && self::$authCollection && !self::$authOwnerDataLoad) {


            self::$authOwnerDataLoad = true;

            self::$authOwnerData = mDB::collection(self::$authCollection)->findOne([
                "_id" => self::$authOwner
            ]);
        }

        if (!self::$authOwnerData) {
            return $default;
        }


        return self::$authOwnerData && isset(self::$authOwnerData->{$key}) ? self::$authOwnerData->{$key} : $default;
    }


    public static function getApiKeyOwnerField(string $key, $default = null): mixed
    {
        if (!self::$initialized) {
            self::init();
        }

        if (self::$apikeyOwner && self::$apikeyCollection && !self::$apikeyOwnerDataLoad) {

            self::$apikeyOwnerDataLoad = true;

            self::$apikeyOwnerData = mDB::collection(self::$apikeyCollection)->findOne([
                "_id" => self::$apikeyOwner
            ]);
        }

        if (!self::$apikeyOwnerData) {
            return $default;
        }
        return self::$apikeyOwnerData && isset(self::$apikeyOwnerData->{$key}) ? self::$apikeyOwnerData->{$key} : $default;
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





    public static function genApiKey(string $title, string $collection,  $_id): string
    {

        $apikey =  hash("sha512", $_id . time() . bin2hex(openssl_random_pseudo_bytes(64)));

        mDB::collection(self::$apikey_collection)->insertOne([
            "apikey" => $apikey,
            'title' => $title,
            'collection' => $collection,
            "agent" => $_SERVER['HTTP_USER_AGENT'] ?? null,
            "ip" => $_SERVER['REMOTE_ADDR'] ?? null,
            "owner" => mDB::id($_id),
        ]);

        return $apikey;
    }


    public static function genToken(StructureType $structure,  $_id): string
    {

        $token =  hash("sha512", $_id . time() . bin2hex(openssl_random_pseudo_bytes(64)));

        mDB::collection(self::$token_collection)->insertOne([
            "token" => $token,
            'collection' => $structure->collection,
            "agent" => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'last_used' => time(),
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

    public static function isPasswordHash($password): bool
    {
        // Проверяем, является ли строка хешем SHA-512
        return strlen($password) === 128;
    }
}
