<?php

namespace Shm\ShmUtils;

use Predis\Client;
use Predis\Connection\ConnectionException;
use Predis\Response\ServerException;

class RedisCache
{
    protected static ?Client $client = null;

    protected const PREFIX = '_cache_';


    private static function getPrefix()
    {

        $hash = ShmInit::$shmVersionHash;

        return self::PREFIX . $hash . '_';
    }

    protected static bool $errorConnection = false;

    /**
     * Инициализирует соединение с Redis через Predis
     */
    protected static function init(): void
    {


        if (self::$errorConnection) {
            return;
        }

        if (self::$client !== null) {
            return;
        }




        try {
            self::$client = new Client([
                'scheme' => 'tcp',
                'host'   => Config::get('redis.host', '127.0.0.1'),
                'port'   => Config::get('redis.port', 6379),
                'timeout' => Config::get('redis.timeout', 0.5)
            ]);


            // Проверка соединения
            self::$client->ping();
        } catch (ConnectionException | ServerException $e) {
            self::$errorConnection = true;
            error_log('[RedisCache] Redis connection failed: ' . $e->getMessage());
        }
    }

    public static function set(string $key, string $value, int $ttl = 300): bool
    {


        self::init();


        if (self::$errorConnection) {
            return false;
        }


        try {
            $prefixedKey =  self::getPrefix() . md5($key);
            self::$client->setex($prefixedKey, $ttl, $value);
            return true;
        } catch (\Exception $e) {

            return false;
        }
    }

    public static function get(string $key): ?string
    {
        self::init();


        if (self::$errorConnection) {
            return null;
        }


        try {
            $prefixedKey = self::getPrefix() . md5($key);
            $value = self::$client->get($prefixedKey);
            return $value !== null ? $value : null;
        } catch (\Exception $e) {
            error_log('[RedisCache] GET error: ' . $e->getMessage());
            return null;
        }
    }

    public static function delete(string $key): bool
    {
        self::init();


        if (self::$errorConnection) {
            return false;
        }
        try {
            $prefixedKey = self::getPrefix() . md5($key);
            self::$client->del([$prefixedKey]);
            return true;
        } catch (\Exception $e) {
            error_log('[RedisCache] DELETE error: ' . $e->getMessage());
            return false;
        }
    }

    public static function clearAll(): void
    {
        self::init();

        if (self::$errorConnection) {
            return;
        }

        try {
            $keys = self::$client->keys(self::PREFIX . '*');
            if (!empty($keys)) {
                self::$client->del($keys);
            }
        } catch (\Exception $e) {
            error_log('[RedisCache] CLEAR error: ' . $e->getMessage());
        }
    }
}
