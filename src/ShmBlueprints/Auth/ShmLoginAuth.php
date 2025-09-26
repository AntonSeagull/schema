<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;

use Shm\ShmUtils\Response;

class ShmLoginAuth extends ShmAuthBase
{

    private function forceProtect($startDataString = "")
    {
        // Определяем, какие ключи из $_SERVER будем использовать для формирования уникального идентификатора
        $keysToInclude = [
            'REMOTE_ADDR',         // IP-адрес клиента
            'HTTP_USER_AGENT',     // User-Agent клиента
            'HTTP_ACCEPT_LANGUAGE', // Языковые настройки
            'HTTP_ACCEPT',         // Заголовок Accept
            'HTTP_CONNECTION',     // Тип соединения
            'HTTP_HOST',           // Хост
        ];

        // Инициализируем пустую строку для хранения данных
        $dataString = $startDataString;

        // Проходим по каждому ключу и добавляем его значение к строке, если оно существует
        foreach ($keysToInclude as $key) {
            if (isset($_SERVER[$key])) {
                $dataString .= $_SERVER[$key];
            }
        }

        // Генерируем SHA-512 хэш-сумму из строки
        $hash = hash('sha512', $dataString);

        // Получаем коллекцию из базы данных
        $collection = mDB::collection("_auth_locks");

        $attempt = $collection->findOne(['hash' => $hash]);

        $currentTime = time();

        if ($attempt) {

            $lastAttemptTime = $attempt->last_attempt;
            $attempts = $attempt->attempts;

            // Сообщение о блокировке
            if ($attempts >= 10 && $currentTime - 60 < $lastAttemptTime) {
                Response::validation("Доступ заблокирован из-за большого количества неудачных попыток. Попробуйте еще раз через одну минуту.");
            }

            // Проверка, прошло ли больше одной минуты
            if ($currentTime - 60 > $lastAttemptTime) {
                // Сбрасываем счетчик, если прошло больше одной минуты
                $collection->updateOne(
                    ['hash' => $hash],
                    ['$set' => ['attempts' => 1, 'last_attempt' => $currentTime]]
                );
            } else {
                // Увеличиваем счетчик
                $collection->updateOne(
                    ['hash' => $hash],
                    ['$inc' => ['attempts' => 1], '$set' => ['last_attempt' => $currentTime]]
                );
            }
        } else {
            // Создаем новый документ, если его нет
            $collection->insertOne([
                'hash' => $hash,
                'attempts' => 1,
                'last_attempt' => $currentTime
            ]);
        }
    }

    public function make(): array
    {



        return [
            'type' => Shm::string(),
            'args' => Shm::structure([
                'login' => Shm::nonNull(Shm::string()),
                "password" => Shm::string(),
                "deviceInfo" => $this->deviceInfoStructure()
            ]),
            'resolve' => function ($root, $args) {


                foreach ($args as &$val) {

                    if (is_string($val)) {
                        $val = trim($val);
                    }
                }


                $this->hasValueValidator(['login', "password"], $args);

                if (($_SERVER['SERVER_NAME'] ?? null) !== "localhost") {

                    self::forceProtect();
                }


                $user = null;
                $userStructure = null;
                foreach ($this->authStructures as $authStructure) {

                    $loginField = $authStructure->findItemByType(Shm::login())?->key;
                    $passwordField = $authStructure->findItemByType(Shm::password())?->key;

                    $match  = [

                        $loginField => $args['login'],
                        $passwordField => Auth::getPassword($args['password']),

                    ];


                    $user = $authStructure->findOne(
                        $match
                    );


                    if ($user) {

                        $userStructure = $authStructure;
                        break;
                    }
                }



                if ($user && $userStructure) {




                    return $this->authToken($userStructure, $user['_id'], $args);
                } else {
                    Response::validation('Неверная пара логин и пароль');
                }
            }
        ];
    }
}
