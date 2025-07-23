<?php

namespace Shm\SocketClient;

use Shm\ShmDB\mDB;
use Shm\ShmUtils\Config;

class SocketClient
{

    /**
     * Отправка сообщения в Nchan канал
     *
     * @param string $channel Название канала
     * @param array|object $data Данные для отправки (массив или объект)
     * @return bool Возвращает true при успешной отправке, иначе false
     */
    public static function send(string $channel, array $data): bool
    {

        //Убираем у $channel первый символ, если он "/" и последний символ, если он "/"
        $channel = trim($channel);
        $channel = ltrim($channel, '/');
        $channel = rtrim($channel, '/');


        $url = 'https://' . Config::get("socket.domain") . "/pub/" . Config::get("socket.prefix") . '/'  . $channel;



        $data = mDB::replaceObjectIdsToString($data);

        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);



        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);


        if ($httpCode !== 200) {
            \Sentry\captureMessage("SocketClient publish failed", \Sentry\Severity::error());

            \Sentry\captureException(new \Exception(
                "Failed to publish to channel '{$channel}'. HTTP code: {$httpCode}. Curl error: {$curlError}. Response: {$response}"
            ));
        }


        return $httpCode === 200;
    }
}
