<?php

namespace Shm\ShmRPC;


use Sentry\Util\JSON;
use Shm\Shm;
use Shm\ShmBlueprints\Auth\ShmAuth;
use Shm\ShmBlueprints\Auth\ShmPassportAuth;
use Shm\ShmBlueprints\ShmBlueprintMutation;
use Shm\ShmBlueprints\ShmBlueprintQuery;

use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPCCodeGen\ShmRPCCodeGen;
use Shm\ShmUtils\DeepAccess;
use Shm\ShmUtils\ProcessLogs;
use Shm\ShmUtils\Response;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmUtils;
use Shm\ShmBlueprints\FileUpload\ShmFileUpload;
use Shm\ShmBlueprints\Geo\ShmIPGeolocation;
use Shm\ShmTypes\BaseType;
use Shm\ShmUtils\RedisCache;

class ShmRPC
{

    public static $init = false;


    public static $key = null;


    private static function executeMethod(&$schemaMethod, $params)
    {

        return $schemaMethod['resolve']($schemaMethod, $params, null, null);
    }

    private static function executeMakeBlueprint($schemaParams)
    {


        foreach ($schemaParams as $key => $field) {



            if (is_object($field) && method_exists($field, 'make')) {
                $schemaParams[$key] = $field->make();
            }
        }


        return $schemaParams;
    }

    public static function transformSchemaParams(array $field, string $key): array
    {

        $field['type']->updateKeys($key);


        if (isset($field['args'])) {


            if (is_array($field['args']) && !($field['args'] instanceof StructureType)) {


                $field['args'] = Shm::structure($field['args']);
            }


            $field['args']->editable()->staticBaseTypeName('Args' . ShmUtils::onlyLetters($key));
        }

        return $field;
    }



    private  static function validateSchemaParams(array $schemaParams): void
    {


        if (!is_array($schemaParams)) {
            throw new \Exception("Schema must be an array.");
        }

        //Проверка что type это BaseType и args это StructureType
        foreach ($schemaParams as $key => &$field) {



            if (!isset($field['type']) || !($field['type'] instanceof \Shm\ShmTypes\BaseType)) {
                throw new \Exception("Schema field '{$key}' must have a 'type' of BaseType.");
            }
        }
    }


    public static function makeMutation(StructureType $strucutre): ShmBlueprintMutation
    {
        return new ShmBlueprintMutation($strucutre);
    }


    public static function makeQuery(StructureType $strucutre): ShmBlueprintQuery
    {
        return new ShmBlueprintQuery($strucutre);
    }


    private  static  function shift_encrypt(string $text, string $key): string
    {
        $min = 32;
        $max = 126;
        $range = $max - $min + 1;
        $keyLen = strlen($key);

        $result = '';
        for ($i = 0; $i < strlen($text); $i++) {
            $code = ord($text[$i]);
            $keyShift = ord($key[$i % $keyLen]);
            $shifted = ($code - $min + $i + $keyShift) % $range + $min;
            $result .= chr($shifted);
        }
        return $result;
    }

    private  static  function shift_decrypt(string $text, string $key): string
    {
        $min = 32;
        $max = 126;
        $range = $max - $min + 1;
        $keyLen = strlen($key);

        $result = '';
        for ($i = 0; $i < strlen($text); $i++) {
            $code = ord($text[$i]);
            $keyShift = ord($key[$i % $keyLen]);
            $shifted = ($code - $min - $i - $keyShift + $range * 2) % $range + $min;
            $result .= chr($shifted);
        }
        return $result;
    }


    private  static function xor_encrypt(string $text, string $key): string
    {
        $keyLen = strlen($key);
        $output = '';

        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $output .= $text[$i] ^ $key[$i % $keyLen];
        }

        return base64_encode($output);
    }

    private  static function xor_decrypt(string $encodedText, string $key): string
    {
        $text = base64_decode($encodedText);
        $keyLen = strlen($key);
        $output = '';

        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $output .= $text[$i] ^ $key[$i % $keyLen];
        }

        return $output;
    }



    private static function getRequestData(): array
    {
        $body = file_get_contents('php://input');
        $request = [];

        if ($body) {
            $decoded = \json_decode($body, true);
            if (is_array($decoded)) {
                $request = $decoded;
            }
        }

        return [...$request, ...$_GET, ...$_POST];
    }

    /**
     * Инициализация
     *
     * @param  array<string, array{
     *         type: mixed,
     *         args?: array<string, mixed>,
     *         resolve?: callable
     *     }>
     * } $schemaParams Описание схемы. 
     **/
    public static function init(array $schemaParams)
    {



        $_schemaParams = [];

        foreach ($schemaParams as $key => $field) {
            $_schemaParams[ShmUtils::translitIfCyrillic($key)] = $field;
        }
        $schemaParams = $_schemaParams;



        Response::startTime();

        $schemaParams =  self::executeMakeBlueprint($schemaParams);

        self::validateSchemaParams($schemaParams);






        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['schema'])) {


            foreach ($schemaParams as $key => $field) {


                $schemaParams[$key] = self::transformSchemaParams($field, $key);
            }




            ShmRPCCodeGen::html($schemaParams, isset($_GET['json']));
        };




        self::$init = true;

        $start = microtime(true);

        $request = self::getRequestData();

        $method = $request['method'] ?? null;

        $params = $request['params'] ?? [];

        $context = $request['context'] ?? null;

        if ($context && is_string($params)) {

            $params = self::xor_decrypt($params, $context);

            try {
                $params = \json_decode($params, true);
            } catch (\Exception $e) {
                Response::validation("Ошибка выполнения запроса");
            }
        }


        if ($method === null) {
            throw new \Exception("Method is required.");
        }


        if ($method === null) {
            throw new \Exception("Method is required.");
        }

        $schemaMethod = $schemaParams[$method] ?? null;

        if ($schemaMethod === null) {
            Response::notFound("Method '{$method}' not found.");
        }


        $cache = isset($request['cache']) ? $request['cache'] : true;



        if ($cache && ($schemaMethod['cache'] ?? 0) > 0) {
            $cache = RedisCache::get($method . json_encode($params));
            if ($cache) {
                if ($cache !== null) {

                    $result = json_decode($cache, true);



                    if ($context) {
                        $result = json_encode($result);
                        $result = self::xor_encrypt($result, $context);
                    }

                    Response::cache();
                    $result = mDB::replaceObjectIdsToString($result);
                    Response::success($result);
                }
            }
        }

        self::callRpcMethod($schemaMethod, $method);
    }

    public static function callRpcMethod($schemaMethod, $method)
    {

        $request = self::getRequestData();

        $params = $request['params'] ?? [];

        $context = $request['context'] ?? null;

        if ($context && is_string($params)) {

            $params = self::xor_decrypt($params, $context);

            try {
                $params = \json_decode($params, true);
            } catch (\Exception $e) {
                Response::validation("Ошибка выполнения запроса");
            }
        }




        Response::startTraceTiming('transformSchemaParams');
        $schemaMethod = self::transformSchemaParams($schemaMethod, $method);
        Response::endTraceTiming('transformSchemaParams');
        if ($schemaMethod === null) {
            Response::notFound("Method '{$method}' not found.");
        }

        Response::startTraceTiming("executeMethod");
        $result = self::executeMethod($schemaMethod, $params);
        Response::endTraceTiming("executeMethod");


        Response::startTraceTiming("normalize");





        $result = $schemaMethod['type']->normalize($result, false);



        $result = $schemaMethod['type']->toOutput($result);




        $result = $schemaMethod['type']->removeOtherItems($result);


        $result = $schemaMethod['type']->normalizePrivate($result);

        Response::endTraceTiming("normalize");




        if ($result) {





            if ($schemaMethod['type'] instanceof StructureType || $schemaMethod['type'] instanceof \Shm\ShmTypes\ArrayOfType) {
                $schemaMethod['type']->expand();


                Response::startTraceTiming("externalData");
                $result = $schemaMethod['type']->externalData($result);
                Response::endTraceTiming("externalData");
            }
        }

        if ($result)
            $result = mDB::replaceObjectIdsToString($result);

        $end = microtime(true);





        if ($result && ($schemaMethod['cache'] ?? 0) > 0) {
            RedisCache::set($method . json_encode($params), json_encode($result), $schemaMethod['cache']);
        }



        if ($context) {
            $result = json_encode($result);
            $result = self::xor_encrypt($result, $context);
        }




        Response::success($result);
    }


    public static function auth(StructureType ...$authStructures): ShmAuth
    {
        return (new ShmAuth(...$authStructures));
    }

    public static function fileUpload(): ShmFileUpload
    {
        return new ShmFileUpload();
    }

    public static function IPGeolocation()
    {
        return ShmIPGeolocation::rpc();
    }
}
