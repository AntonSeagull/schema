<?php

namespace Shm\ShmAuth;

use Shm\ShmAdmin\SchemaCollections\SubAccountsSchema;
use Shm\ShmCmd\Cmd;
use Shm\ShmDB\mDB;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\Response;

class Auth
{


    public static function accountSessionRevoke()
    {

        //Если запрос пришел из CLI, то не меняем таймзону
        if (Cmd::cli()) {
            return;
        }



        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';


        $requestUri = is_string($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
        $requestUri = rtrim($requestUri, '/');


        if ($requestMethod === 'GET' && preg_match('#^/account/session/revoke/([a-f0-9]{128})$#', $requestUri, $matches)) {

            $token = $matches[1];

            mDB::_collection(self::$token_collection)->deleteOne([
                'cancelKey' => $token
            ]);

            Response::html('<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Session Revoked</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background: #f5f5f7;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, sans-serif;
      color: #111111;
      margin: 0;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }
    .card {
      background: #ffffff;
      border-radius: 16px;
      padding: 40px 32px;
      max-width: 420px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.08);
      text-align: center;
    }
    h1 {
      font-size: 22px;
      font-weight: 700;
      margin: 0 0 12px;
    }
    p {
      font-size: 16px;
      line-height: 1.5;
      margin: 0 0 20px;
    }
    .button {
      display: inline-block;
      padding: 12px 20px;
      font-size: 15px;
      font-weight: 600;
      color: #fff;
      background: #0071e3;
      border-radius: 8px;
      text-decoration: none;
      margin-top: 12px;
    }
    .ru {
      display: block;
      font-size: 14px;
      color: #6e6e73;
      margin-top: 4px;
    }
    @media (prefers-color-scheme: dark) {
      body { background: #000; color: #fff; }
      .card { background: #1c1c1e; box-shadow: none; }
      .ru { color: #a1a1a6; }
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>Session revoked</h1>
    <span class="ru">Сессия сброшена</span>
    <p>
      The suspicious login session has been successfully revoked.  
      <span class="ru">Подозрительная сессия входа была успешно завершена.</span>
    </p>
    <p>
      For your security, please reset your password.  
      <span class="ru">Для вашей безопасности, пожалуйста, смените пароль.</span>
    </p>
  
  </div>
</body>
</html>');
            exit;
        }
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


    public static $subAccount = null;


    private static $initialized = false;


    public static $currentRequestToken = null;


    public static function setManualToken($token)
    {
        self::init($token);
    }

    private static function init($manualToken = null)
    {

        $token = $manualToken ?: self::getRequestKey(['token', 'authorization', 'x-auth-token']);



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

                self::$currentRequestToken = $token;

                if ($findToken->collection === SubAccountsSchema::$collection) {

                    self::$subAccount = mDB::collection(SubAccountsSchema::$collection)->findOne([
                        "_id" => $findToken->owner
                    ]);

                    self::$authCollection = self::$subAccount->collection;
                    self::$authOwner = self::$subAccount->owner;
                } else {
                    self::$authCollection = $findToken->collection;
                    self::$authOwner = $findToken->owner;

                    mDB::_collection(self::$authCollection)->updateOne([
                        '_id' => $findToken->owner
                    ], [
                        '$set' => [
                            'last_active_at' => time(),
                        ]
                    ]);
                }
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

    public static function isAuthenticated(): bool
    {
        if (!self::$initialized) {
            self::init();
        }

        return self::$authOwner !== null;
    }


    public static function apiKeyAuthenticatedOrThrow(StructureType ...$authStructures)
    {


        if ($authStructures) {

            foreach ($authStructures as $structure) {

                if ($structure->collection &&  $structure->collection == self::$apikeyCollection) {
                    return false;
                }
            }

            Response::unauthorized();
        }

        if (!self::getApiKeyOwner()) {
            Response::unauthorized();
        }
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


    public static function getAuthOwnerAllField($default = null): mixed
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



        return self::$authOwnerData ? self::$authOwnerData : $default;
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

    public static function subAccountAuth(): bool
    {
        if (!self::$initialized) {
            self::init();
        }

        return self::$subAccount !== null;
    }


    public static function getSubAccountID()
    {
        if (!self::$initialized) {
            self::init();
        }

        return self::$subAccount ? self::$subAccount->_id : null;
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


    public static function genToken(StructureType $structure,  $_id, $cancelKey = null): string
    {

        $token =  hash("sha512", $_id . time() . bin2hex(openssl_random_pseudo_bytes(64)));

        mDB::collection(self::$token_collection)->insertOne([
            "token" => $token,
            'cancelKey' => $cancelKey,
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
        return strlen($password) === 128 && ctype_xdigit($password);
    }
}
