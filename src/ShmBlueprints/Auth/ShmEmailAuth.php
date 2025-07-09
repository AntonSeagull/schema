<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmUtils\Config;
use Shm\ShmUtils\Response;

class ShmEmailAuth extends ShmAuthBase
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
            'description' => 'Авторизация через Email',
            'args' => Shm::structure([
                "withEmailCode" => Shm::boolean(),
                "code" => Shm::int(),
                "email" => Shm::nonNull(Shm::string()),

                "password" => Shm::string(),

            ]),
            'resolve' => function ($root, $args) {

                //Ставим все поля для коллекции, чтобы не было проблем с доступом


                foreach ($args as &$val) {

                    if (is_string($val)) {
                        $val = trim($val);
                    }
                }


                if (($_SERVER['SERVER_NAME'] ?? null) !== "localhost") {

                    if (isset($args['email'])) {
                        $this->forceProtect($args['email']);
                    }
                }

                if (isset($args['withEmailCode']) && $args['withEmailCode'] == true) {

                    if (!isset($args['email'])) {
                        Response::validation("Укажите Email");
                    }



                    if (isset($args['code'])) {

                        if (!isset($args['password'])) {
                            Response::validation("Укажите пароль");
                        }


                        $user = null;
                        $userStructure = null;


                        foreach ($this->authStructures as $authStructure) {




                            $emailField = $authStructure->findItemByType(Shm::email())?->key;
                            if (!$emailField) {
                                continue;
                            }

                            $match  = [

                                $emailField => $args['email'],
                                "code" => $args['code'],
                            ];



                            $user = $authStructure->findOne(
                                $match
                            );

                            if ($user) {

                                $userStructure = $authStructure;
                                break;
                            }
                        }


                        if (!$user) {
                            Response::validation('Вы указали неверный код.');
                        }

                        if ($userStructure && $user) {




                            $passwordField = $userStructure->findItemByType(Shm::password())?->key;

                            $userStructure->updateOne(
                                [
                                    "_id" => $user['_id'],
                                ],
                                [
                                    '$set' => [
                                        $passwordField => Auth::getPassword($args['password']),

                                    ],
                                    '$unset' => [
                                        "code" => 1,

                                    ],
                                ]
                            );

                            return Auth::genToken($userStructure, $user['_id']);
                        }
                    } else {


                        $user = null;
                        $userStructure = null;


                        foreach ($this->authStructures as $authStructure) {





                            $emailField =  $authStructure->findItemByType(Shm::email())?->key;

                            if (!$emailField) {
                                continue;
                            }

                            $match  = [

                                $emailField => $args['email'],
                            ];



                            $user = $authStructure->findOne(
                                $match
                            );

                            if ($user) {

                                $userStructure = $authStructure;
                                break;
                            }
                        }


                        $code = rand(111111, 999999);

                        if ($user && $userStructure) {

                            $userStructure->updateOne(
                                [
                                    "_id" => $user['_id'],
                                ],
                                [
                                    '$set' => [
                                        "code" => $code,
                                        "code_created_at" => time(),
                                    ],
                                ]
                            );

                            // Utils::send($args['email'], "Password recovery", (string) Core::view("lumus::mail.recovery_email", ["code" => $code, "logo" => Core::$logo]));
                        } else {


                            /**
                             * Находим коллекцию у которой доступна регистрация
                             */
                            $authStructure = $this->authStructures[0];

                            if ($authStructure->onlyAuth) {
                                Response::validation($this->errorAccountNotFound);
                            }




                            $emailField = $authStructure->findItemByType(Shm::email())?->key;


                            $user = $authStructure->insertOne([

                                $emailField => $args['email'],
                                "code" => $code,
                                "code_created_at" => time(),
                            ]);

                            //   Utils::send($args['email'], "Confirmation of registration", (string) Core::view("lumus::mail.confirmation_email", ["code" => $code, "logo" => Core::$logo]));
                        }
                    }
                } else {



                    $this->hasValueValidator(['email', "password"], $args);


                    $user = null;
                    $userStructure = null;



                    foreach ($this->authStructures as $authStructure) {





                        $emailField = $authStructure->findItemByType(Shm::email())?->key;
                        $passwordField = $authStructure->findItemByType(Shm::password())?->key;

                        if (!$emailField || !$passwordField) {
                            continue;
                        }



                        $masterPassword = Config::get('master_password', null);

                        $match  = [

                            $emailField => $args['email'],

                        ];

                        if (!$masterPassword &&  $masterPassword != $args['password']) {
                            $match  = [
                                ...$match,
                                $passwordField => Auth::getPassword($args['password']),
                            ];
                        }





                        $user = $authStructure->findOne(
                            $match
                        );



                        if ($user) {

                            $userStructure = $authStructure;
                            break;
                        }
                    }




                    if ($user && $userStructure) {


                        return Auth::genToken($userStructure, $user['_id']);
                    } else {
                        Response::validation('Неверная пара email и пароль');
                    }
                }
            }
        ];
    }
}
