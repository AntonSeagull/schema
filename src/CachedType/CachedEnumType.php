<?php

namespace Shm\CachedType;

use GraphQL\Type\Definition\EnumType;
use Shm\GQLUtils\AutoPostfix;

class CachedEnumType extends EnumType
{
    private static $cachedTypes = [];

    /**
     *
     * @param array $config Конфигурация для типа.
     * @return self
     */
    public static function create(array $config)
    {





        if (!isset(self::$cachedTypes[$config['name']])) {
            self::$cachedTypes[$config['name']] = new static($config);
        }

        return self::$cachedTypes[$config['name']];
    }

    protected function __construct(array $config)
    {
        parent::__construct($config);
    }
}