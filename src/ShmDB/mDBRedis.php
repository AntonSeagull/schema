<?php

namespace Shm\ShmDB;

use Shm\Shm;
use Shm\ShmUtils\RedisStorage;
use Shm\ShmUtils\ShmInit;

/**
 * Класс для кеширования данных коллекций в Redis.
 *
 * Используется для хранения документов после изменения в БД,
 * а также для быстрой выборки по названию коллекции и _id.
 */
class mDBRedis
{






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
        return 'mdb:' . $collection . ':' . $id . ShmInit::$rootDir;
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
}