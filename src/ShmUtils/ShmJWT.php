<?php

namespace Lumus;

use DateTime;
use DateTimeZone;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use OpenSSLAsymmetricKey;
use Shm\ShmDB\mDB;
use Shm\ShmUtils\ShmInit;

class ShmJWT
{

    private static $publicKey;
    private static $privateKey;

    private static $algo = 'RS256';

    public static function encode(array $customClaims = [], int $ttl): string
    {

        $payload = array_merge($customClaims, self::getDefaultClaims($ttl));

        return JWT::encode($payload, self::getPrivateKey(), self::$algo);
    }

    /**
     * @throws InvalidArgumentException                 Provided key/key-array was empty or malformed
     * @throws DomainException                          Provided JWT is malformed
     * @throws UnexpectedValueException                 Provided JWT was invalid
     * @throws Firebase\JWT\SignatureInvalidException   Provided JWT was invalid because the signature verification failed
     * @throws Firebase\JWT\BeforeValidException        Provided JWT is trying to be used before it's eligible as defined by 'nbf'
     * @throws Firebase\JWT\BeforeValidException        Provided JWT is trying to be used before it's been created as defined by 'iat'
     * @throws Firebase\JWT\ExpiredException            Provided JWT has since expired, as defined by the 'exp' claim
     */
    public static function decode(string $jwt, int $leeway = 0): array
    {

        JWT::$leeway = $leeway;
        return (array) JWT::decode($jwt, new Key(self::getPublicKey(), self::$algo));
    }






    private static function getRandomString($length = 16)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';

        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[array_rand(str_split($characters))];
        }

        return $string;
    }

    private static function getDefaultClaims(int $ttl): array
    {
        $claims = [];
        $now = new DateTime('now', new DateTimeZone('UTC'));

        $claims['iat'] = $now->getTimestamp(); //Время создания токена
        $claims['nbf'] = $now->getTimestamp(); //Время, после которого токен станет доступен

        $expTime = clone $now;
        $expTime->modify('+' . $ttl . ' minutes');
        $claims['exp'] = $expTime->getTimestamp(); //Время, после которого токен считается невалидным

        $claims['jti'] = self::getRandomString(); //Уникальный идентификатор токена

        return $claims;
    }

    private static function getPrivateKey(): OpenSSLAsymmetricKey
    {
        if (self::$privateKey == null) {
            $configDir = ShmInit::$rootDir . '/config';
            $privateKeyPath = $configDir . '/jwt_private.pem';

            // Если ключ не существует, создаем его
            if (!file_exists($privateKeyPath)) {
                self::createDefaultKeyPair($configDir);
            }

            return self::$privateKey = openssl_pkey_get_private(
                file_get_contents($privateKeyPath)
            );
        } else {
            return self::$privateKey;
        }
    }

    private static function getPublicKey(): OpenSSLAsymmetricKey
    {
        if (self::$publicKey == null) {
            $configDir = ShmInit::$rootDir . '/config';
            $publicKeyPath = $configDir . '/jwt_public.pem';

            // Если ключ не существует, создаем его
            if (!file_exists($publicKeyPath)) {
                self::createDefaultKeyPair($configDir);
            }

            return self::$publicKey = openssl_pkey_get_public(
                file_get_contents($publicKeyPath)
            );
        } else {
            return self::$publicKey;
        }
    }



    /**
     * Создает новую пару RSA ключей и сохраняет их в указанную директорию
     * 
     * @param string $directory Директория для сохранения ключей
     * @param string $privateKeyName Имя файла приватного ключа (по умолчанию: private.pem)
     * @param string $publicKeyName Имя файла публичного ключа (по умолчанию: public.pem)
     * @param int $keySize Размер ключа в битах (по умолчанию: 2048)
     * @param string $passphrase Пароль для приватного ключа (опционально)
     * @return array Массив с путями к созданным файлам
     * @throws \Exception Если не удалось создать ключи или сохранить файлы
     */
    public static function generateKeyPair(
        string $directory,
        string $privateKeyName = 'private.pem',
        string $publicKeyName = 'public.pem',
        int $keySize = 2048,
        ?string $passphrase = null
    ): array {
        // Создаем директорию если она не существует
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new \Exception("Не удалось создать директорию: {$directory}");
            }
        }

        // Генерируем новую пару ключей
        $config = [
            "digest_alg" => "sha256",
            "private_key_bits" => $keySize,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        $resource = openssl_pkey_new($config);
        if (!$resource) {
            throw new \Exception("Не удалось сгенерировать ключи: " . openssl_error_string());
        }

        // Экспортируем приватный ключ
        $privateKeyPem = '';
        if (!openssl_pkey_export($resource, $privateKeyPem, $passphrase)) {
            throw new \Exception("Не удалось экспортировать приватный ключ: " . openssl_error_string());
        }

        // Получаем публичный ключ
        $keyDetails = openssl_pkey_get_details($resource);
        if (!$keyDetails) {
            throw new \Exception("Не удалось получить детали ключа: " . openssl_error_string());
        }

        $publicKeyPem = $keyDetails['key'];

        // Пути к файлам
        $privateKeyPath = rtrim($directory, '/') . '/' . $privateKeyName;
        $publicKeyPath = rtrim($directory, '/') . '/' . $publicKeyName;

        // Сохраняем приватный ключ
        if (file_put_contents($privateKeyPath, $privateKeyPem) === false) {
            throw new \Exception("Не удалось сохранить приватный ключ в файл: {$privateKeyPath}");
        }

        // Устанавливаем права доступа для приватного ключа (только владелец может читать)
        chmod($privateKeyPath, 0600);

        // Сохраняем публичный ключ
        if (file_put_contents($publicKeyPath, $publicKeyPem) === false) {
            throw new \Exception("Не удалось сохранить публичный ключ в файл: {$publicKeyPath}");
        }

        // Устанавливаем права доступа для публичного ключа
        chmod($publicKeyPath, 0644);

        return [
            'private_key_path' => $privateKeyPath,
            'public_key_path' => $publicKeyPath,
            'private_key' => $privateKeyPem,
            'public_key' => $publicKeyPem
        ];
    }

    /**
     * Создает ключи с настройками по умолчанию и сохраняет их в директорию
     * 
     * @param string $directory Директория для сохранения ключей
     * @return array Массив с путями к созданным файлам
     */
    public static function createDefaultKeyPair(string $directory): array
    {
        return self::generateKeyPair($directory, 'jwt_private.pem', 'jwt_public.pem', 2048);
    }

    /**
     * Проверяет существование и валидность ключей в указанной директории
     * 
     * @param string $directory Директория с ключами
     * @param string $privateKeyName Имя файла приватного ключа
     * @param string $publicKeyName Имя файла публичного ключа
     * @return bool True если ключи существуют и валидны
     */
    public static function validateKeyPair(
        string $directory,
        string $privateKeyName = 'private.pem',
        string $publicKeyName = 'public.pem'
    ): bool {
        $privateKeyPath = rtrim($directory, '/') . '/' . $privateKeyName;
        $publicKeyPath = rtrim($directory, '/') . '/' . $publicKeyName;

        if (!file_exists($privateKeyPath) || !file_exists($publicKeyPath)) {
            return false;
        }

        // Проверяем валидность приватного ключа
        $privateKey = openssl_pkey_get_private(file_get_contents($privateKeyPath));
        if (!$privateKey) {
            return false;
        }

        // Проверяем валидность публичного ключа
        $publicKey = openssl_pkey_get_public(file_get_contents($publicKeyPath));
        if (!$publicKey) {
            return false;
        }

        return true;
    }
}