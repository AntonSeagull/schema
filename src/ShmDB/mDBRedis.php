<?php

namespace Shm\ShmDB;

use Shm\ShmUtils\RedisStorage;

/**
 * Класс для кеширования данных коллекций в Redis.
 *
 * Используется для хранения документов после изменения в БД,
 * а также для быстрой выборки по названию коллекции и _id.
 */
class mDBRedis
{



    public static array $cachedCollections = [];

    public static function isCollectionCached(string $collection): bool
    {
        return in_array($collection, self::$cachedCollections, true);
    }

    public static function addCachedCollection(string $collection): void
    {
        if (!in_array($collection, self::$cachedCollections, true)) {
            self::$cachedCollections[] = $collection;
        }
    }

    public static function removeCachedCollection(string $collection): void
    {
        self::$cachedCollections = array_filter(
            self::$cachedCollections,
            fn($col) => $col !== $collection
        );
    }

    public static function clearCachedCollections(): void
    {
        self::$cachedCollections = [];
    }

    public static function setCachedCollections(array $collections): void
    {
        self::$cachedCollections = array_values(array_unique($collections));
    }



    /**
     * Время жизни данных в Redis (в секундах)
     */
    protected const TTL = 300;

    /**
     * Формирует ключ Redis для хранения документа.
     *
     * Формат: mdb:{collection}:{id}
     *
     * @param string $collection Название коллекции
     * @param string $id Идентификатор документа
     * @return string Ключ для Redis
     */
    protected static function makeKey(string $collection, string $id): string
    {
        return 'mdb:' . $collection . ':' . $id;
    }

    /**
     * Сохраняет документ в Redis.
     *
     * @param string $collection Название коллекции
     * @param string $id Идентификатор документа
     * @param array|object $data Данные документа
     * @return bool Успешность сохранения
     */
    public static function save(string $collection, string $id, $data): bool
    {

        if (!self::isCollectionCached($collection)) {
            return false;
        }


        try {
            $key = self::makeKey($collection, $id);

            $data = mDB::replaceObjectIdsToString($data);

            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            return RedisStorage::set($key, $json, self::TTL);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Получает документ из Redis по коллекции и _id.
     *
     * @param string $collection Название коллекции
     * @param string $id Идентификатор документа
     * @return array|null Данные документа или null, если не найден
     */
    public static function get(string $collection, string $id): ?array
    {
        $key = self::makeKey($collection, $id);
        $json = RedisStorage::get($key);
        return $json ? json_decode($json, true) : null;
    }

    /**
     * Получает несколько документов по массиву _id.
     *
     * @param string $collection Название коллекции
     * @param string[] $ids Массив идентификаторов
     * @return array Ассоциативный массив [id => данные|null]
     */
    public static function getMany(string $collection, array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = self::get((string) $collection, (string) $id);
        }
        return $result;
    }

    /**
     * Удаляет документ(ы) из кеша по коллекции и _id (или массиву _id).
     *
     * @param string $collection Название коллекции
     * @param string|string[] $ids Один или несколько идентификаторов
     * @return void
     */
    public static function delete(string $collection, $ids): void
    {
        $ids = is_array($ids) ? $ids : [$ids];
        foreach ($ids as $id) {
            $key = self::makeKey($collection, $id);
            RedisStorage::delete($key);
        }
    }

    /**
     * Делает выборку из БД после обновления данных и сохраняет их в Redis.
     *
     * Пример:
     *   mDBRedis::updateCacheAfterChange('orders', ['status' => 'paid']);
     *
     * @param string $collection Название коллекции
     * @param array $filter Фильтр find()
     * @param bool $force Принудительно обновить кеш, даже если коллекция не в списке кешируемых
     * @return int Количество документов, добавленных в кеш
     */
    public static function updateCacheAfterChange(string $collection, array $filter, bool $force = false): int
    {

        if (!$force && !self::isCollectionCached($collection)) {
            return 0;
        }


        try {



            $cursor = mDB::_collection($collection)->find($filter);

            $count = 0;
            foreach ($cursor as $doc) {
                $id = (string)($doc['_id'] ?? '');
                if (!$id) {
                    continue;
                }

                $json = mDB::replaceObjectIdsToString($doc);
                $json = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                $key = self::makeKey($collection, $id);

                if (RedisStorage::set($key, $json, self::TTL)) {
                    $count++;
                }
            }

            return $count;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
