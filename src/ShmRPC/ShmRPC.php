<?php

namespace Shm\ShmRPC;



use Shm\Shm;
use Shm\ShmBlueprints\Auth\ShmAuth;
use Shm\ShmBlueprints\ShmBlueprintMutation;
use Shm\ShmBlueprints\ShmBlueprintQuery;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPCCodeGen\ShmRPCCodeGen;
use Shm\ShmUtils\DeepAccess;
use Shm\ShmUtils\ProcessLogs;
use Shm\ShmUtils\Response;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmUtils;

class ShmRPC
{

    public static $init = false;


    private static function executeMethod(&$schemaMethod, $params)
    {

        return $schemaMethod['resolve']($schemaMethod, $params, null, null);
    }


    private static function transformSchemaParams(array $field, string $key): array
    {

        $field['type']->updateKeys($key);

        if (isset($field['args'])) {

            if (is_array($field['args']) && !($field['args'] instanceof StructureType)) {
                $field['args'] = new StructureType($field['args']);
            }

            $field['args']->safeFullEditable()->staticBaseTypeName('Args' . ShmUtils::onlyLetters($key));
        }

        return $field;
    }



    private  static function validateSchemaParams(array $schemaParams): void
    {


        if (!is_array($schemaParams)) {
            throw new \InvalidArgumentException("Schema must be an array.");
        }

        //Проверка что type это BaseType и args это StructureType
        foreach ($schemaParams as $key => $field) {
            if (!isset($field['type']) || !($field['type'] instanceof \Shm\ShmTypes\BaseType)) {
                throw new \InvalidArgumentException("Schema field '{$key}' must have a 'type' of BaseType.");
            }
            if (isset($field['args']) && !($field['args'] instanceof StructureType)) {
                throw new \InvalidArgumentException("Schema field '{$key}' must have 'args' of StructureType.");
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


        Response::startTime();

        self::validateSchemaParams($schemaParams);


        foreach ($schemaParams as $key => $field) {
            $schemaParams[$key] = self::transformSchemaParams($field, $key);
        }



        //If GET request, we can return the schema
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            ShmRPCCodeGen::html($schemaParams);
        };


        self::$init = true;

        $start = microtime(true);

        $body = file_get_contents('php://input');
        $request = \json_decode($body, true);

        $method = $request['method'] ?? null;
        $params = $request['params'] ?? null;


        if ($method === null) {
            throw new \InvalidArgumentException("Method is required.");
        }

        $schemaMethod = $schemaParams[$method] ?? null;
        if ($schemaMethod === null) {
            Response::notFound("Method '{$method}' not found.");
        }

        $result = self::executeMethod($schemaMethod, $params);




        $result = $schemaMethod['type']->normalize($result, false);

        $result = $schemaMethod['type']->removeOtherItems($result);



        if ($result) {


            if ($schemaMethod['type'] instanceof StructureType || $schemaMethod['type'] instanceof \Shm\ShmTypes\ArrayOfType) {

                $result = $schemaMethod['type']->externalData($result);
            }
        }

        if ($result)
            $result = mDB::replaceObjectIdsToString($result);

        $end = microtime(true);
        $duration = round(($end - $start) * 1000);


        Response::success($result);
    }

    public static function auth(StructureType ...$authStructures): ShmAuth
    {
        return (new ShmAuth(...$authStructures));
    }
}
