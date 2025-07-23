<?php

namespace Lumus\GraphQL;

use Shm\Collection\Collection;
use Shm\Shm;
use Shm\ShmBlueprints\ShmBlueprintMutation;
use Shm\ShmBlueprints\ShmBlueprintQuery;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\EnumType;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\Response;
use Shm\ShmUtils\ShmUtils;

class GQL extends ShmRPC
{


    public static $isGraphQL = true;

    public static function fieldObjectType(Collection | array $data, $fieldName)
    {


        return $data::structure()->findItemByKey($fieldName);
    }


    public static function cachedEnumType(array $value): EnumType
    {
        return Shm::enum($value['values']);
    }

    public static function objectType(Collection | array $data)
    {

        if (is_array($data)) {


            return self::cachedObjectType($data);
        }



        return $data::structure();
    }

    public static function objectInputType($data)
    {

        return self::objectType($data);
    }

    public static function cachedObjectType($params)
    {


        $name = $params['name'] ?? null;
        //Remove Type from end if exists
        if (str_ends_with($name, 'Type')) {
            $name = substr($name, 0, -4);
        }

        //Remore Input from end if exists
        if (str_ends_with($name, 'Input')) {
            $name = substr($name, 0, -5);
        }

        $items = [];

        $fields = $params['fields'] ?? [];

        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                $items[$key] = $value['type'] ?? null;
            } else {
                $items[$key] = $value;
            }
        }

        return Shm::structure($items)->staticBaseTypeName($name);
    }


    public static function init($data, ...$args)
    {

        $query = $data['query'] ?? null;
        $mutation = $data['mutation'] ?? null;


        self::$isGraphQL = true;

        $schema = [];

        if ($query) {

            unset($data['query']);

            foreach ($query as $key => $value) {
                $schema['q' . ShmUtils::onlyLetters($key)] = $value;
            }
        }

        if ($mutation) {

            unset($data['mutation']);

            foreach ($mutation as $key => $value) {
                $schema['m' . ShmUtils::onlyLetters($key)] = $value;
            }
        }

        foreach ($schema as &$item) {


            if (isset($item['args'])) {

                foreach ($item['args'] as &$arg) {

                    if (is_array($arg) && isset($arg['type'])) {
                        $arg = $arg['type'];
                    }
                }
            }
        }

        ShmRPC::init([...$schema, ...$data]);
    }


    public static function _makeMutation(Collection $collection): ShmBlueprintMutation
    {
        return new ShmBlueprintMutation($collection::structure());
    }


    public static function _makeQuery(Collection $collection): ShmBlueprintQuery
    {
        return new ShmBlueprintQuery($collection::structure());
    }


    public static function error($message)
    {

        Response::validation($message);
    }
}
