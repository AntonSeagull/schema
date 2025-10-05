<?php

namespace Shm\ShmUtils;

use Predis\Client;
use Predis\Connection\ConnectionException;
use Predis\Response\ServerException;

/**
 * Класс для работы с Redis-хранилищем через Predis.
 * Используется для сохранения, получения и удаления данных с TTL (временем жизни).
 */
class RedisStorage
{
    /**
     * Экземпляр клиента Redis
     * @var Client|null
     */
    protected static ?Client $client = null;

    /**
     * Префикс для ключей, чтобы избежать коллизий
     */
    protected const PREFIX = '_STORAGE_';

    /**
     * Флаг, показывающий, что соединение с Redis не удалось
     * @var bool
     */
    protected static bool $errorConnection = false;

    /**
     * Возвращает префикс для ключей Redis
     *
     * @return string Префикс для ключей
     */
    private static function getPrefix(): string
    {
        return self::PREFIX;
    }

    /**
     * Инициализирует соединение с Redis через Predis.
     * Если соединение уже установлено — повторная инициализация не выполняется.
     * В случае ошибки соединения устанавливает флаг $errorConnection = true.
     *
     * @return void
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
                'scheme'  => 'tcp',
                'host'    => Config::get('redis.host', '127.0.0.1'),
                'port'    => Config::get('redis.port', 6379),
                'timeout' => Config::get('redis.timeout', 0.5)
            ]);

            // Проверка соединения
            self::$client->ping();
        } catch (ConnectionException | ServerException $e) {
            self::$errorConnection = true;
            error_log('[RedisCache] Redis connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Сохраняет значение в Redis с заданным временем жизни.
     *
     * @param string $key Ключ (логический идентификатор данных)
     * @param string $value Значение, которое нужно сохранить
     * @param int $ttl Время жизни ключа (в секундах), по умолчанию 300 секунд
     *
     * @return bool Возвращает true, если запись выполнена успешно, иначе false
     */
    public static function set(string $key, string $value, int $ttl = 300): bool
    {
        self::init();

        if (self::$errorConnection) {
            return false;
        }

        try {
            $prefixedKey = self::getPrefix() . md5($key);
            self::$client->setex($prefixedKey, $ttl, $value);
            return true;
        } catch (\Exception $e) {
            error_log('[RedisCache] SET error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получает значение из Redis по ключу.
     *
     * @param string $key Ключ, по которому нужно получить значение
     *
     * @return string|null Возвращает значение, если найдено, или null в случае отсутствия или ошибки
     */
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

    /**
     * Удаляет ключ из Redis.
     *
     * @param string $key Ключ, который нужно удалить
     *
     * @return bool Возвращает true, если ключ успешно удалён, иначе false
     */
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

    /**
     * Очищает все данные с префиксом данного хранилища.
     * Удаляет все ключи, начинающиеся с PREFIX.
     *
     * @return void
     */
    public static function clearAll(): void
    {
        self::init();

        if (self::$errorConnection) {
            return;
        }

        try {
            $keys = self::$client->keys(self::getPrefix() . '*');
            if (!empty($keys)) {
                self::$client->del($keys);
            }
        } catch (\Exception $e) {
            error_log('[RedisCache] CLEAR error: ' . $e->getMessage());
        }
    }
}
