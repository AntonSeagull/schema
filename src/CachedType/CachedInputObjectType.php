<?php

namespace Shm\CachedType;

use GraphQL\Type\Definition\InputObjectType;
use Lumus\GraphQL\GQL;
use Lumus\GraphQL\GQLUtils\ExpectToGraphQLInputType;
use Lumus\Schema\Elements\Structure;

class CachedInputObjectType extends InputObjectType
{
    private static $cachedTypes = [];



    public static function has(string $name)
    {

        return isset(self::$cachedTypes[$name]);
    }


    public static function get(string $name)
    {

        return  self::$cachedTypes[$name] ?? null;
    }


    /**
     * Создает или возвращает существующий объект типа.
     *
     * @param array $config Конфигурация для типа.
     * @return self
     */
    public static function create(array $config)
    {
        $typeName = $config['name'];


        // Проверяем, существует ли уже тип с таким именем
        if (!isset(self::$cachedTypes[$typeName])) {
            // Создаем новый тип и сохраняем его в кэше



            self::$cachedTypes[$typeName] = new static($config);
        }


        return self::$cachedTypes[$typeName];
    }

    protected function __construct(array $config)
    {
        parent::__construct($config);
    }
}
