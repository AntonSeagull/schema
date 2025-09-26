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


    private function sendEmail($mailTo, $subject, $body)
    {


        if (!$mailTo || !$subject || !$body) {
            return;
        }


        $host  = Config::get('smtp.host', null);
        $port  =  Config::get('smtp.port', null);
        $username  = Config::get('smtp.username', null);
        $password  = Config::get('smtp.password', null);
        $encryption  = Config::get('smtp.encryption', null);
        $from_email  = Config::get('smtp.from_email', null);
        $from_name  = Config::get('smtp.from_name', null);

        if (!$host || !$port || !$username || !$password) {

            return;
        }




        $mail = new \Nette\Mail\Message;
        $mail->setFrom($from_email, $from_name)
            ->addTo($mailTo)
            ->setSubject($subject)
            ->setHtmlBody($body);

        $mailer = new \Nette\Mail\SmtpMailer(
            host: $host,
            port: $port,
            username: $username,
            password: $password,
            encryption: $encryption
        );
        $mailer->send($mail);
    }




    public function make(): array
    {



        return [
            'type' => Shm::string(),
            'description' => 'Авторизация через Email (или логин)',
            'args' => Shm::structure([
                "withEmailCode" => Shm::boolean(),
                "code" => Shm::int(),
                "email" => Shm::nonNull(Shm::string()),

                "password" => Shm::string(),
                "deviceInfo" => $this->deviceInfoStructure()

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


                            return $this->authToken($userStructure, $user['_id'], $args);
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


                            $this->recoveryEmail($args['email'], $code);
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

                            $this->confirmationEmail($args['email'], $code);
                        }
                    }
                } else {



                    $this->hasValueValidator(['email', "password"], $args);


                    $user = null;
                    $userStructure = null;



                    foreach ($this->authStructures as $authStructure) {


                        $pipeline = [];



                        $emailField = $authStructure->findItemByType(Shm::email())?->key;

                        $loginField = $authStructure->findItemByType(Shm::login())?->key;

                        $passwordField = $authStructure->findItemByType(Shm::password())?->key;

                        if ((!$emailField && !$loginField) || !$passwordField) {
                            continue;
                        }



                        $masterPassword = Config::get('master_password', null);


                        $or = [];


                        if ($loginField) {
                            $or[] = [$loginField => $args['email']];
                        }
                        if ($emailField) {
                            $or[] = [$emailField => $args['email']];
                        }


                        $pipeline[]  = [

                            '$match' => [
                                '$or' => $or
                            ]

                        ];



                        if ($masterPassword &&  $masterPassword != $args['password']) {
                            $pipeline[]  = [


                                '$match' => [
                                    $passwordField => Auth::getPassword($args['password']),
                                ]



                            ];
                        }



                        $pipeline[] =
                            [
                                '$limit' => 1
                            ];





                        $user = $authStructure->aggregate(
                            $pipeline
                        )->toArray()[0] ?? null;



                        if ($user) {

                            $userStructure = $authStructure;
                            break;
                        }
                    }




                    if ($user && $userStructure) {



                        return $this->authToken($userStructure, $user['_id'], $args);
                    } else {
                        Response::validation('Данные для входа неверны');
                    }
                }
            }
        ];
    }


    private function recoveryEmail($email, $code)
    {

        $body = '<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password recovery</title>
    <style>
        body {
            text-align: center;
            font-family: Arial, sans-serif;
            margin: 40px;
        }

        .container {
            background-color: #f7f7f7;
            padding: 20px;
            border-radius: 5px;
        }

        .code {
            font-weight: bold;
            font-size: 24px;
            margin: 20px 0;
        }

        .notice {
            color: red;
            margin-top: 20px;
        }

        .ru {
            font-size: 12px;

        }

        .logo {
            display: block;
            margin: 0 auto 20px auto;
            border-radius: 20px;
            max-width: 100px;
        }
    </style>
</head>

<body>
    <div class="container">
       

        <h2>Password recovery<br />
            <p class="ru">Восстановление пароля</p>
        </h2>


        <p>You have requested password recovery! To complete the recovery process, please enter the following
            confirmation
            code:

            <br /><span class="ru">Вы запросили восстановление пароля! Для завершения процесса восстановления,
                пожалуйста,
                введите следующий код подтверждения:</span>
        </p>


        <div class="code">' . $code . '</div>

        <p>This code is valid for 15 minutes.<br /><span class="ru">Этот код действителен в течение 15 минут.</span>
        </p>

        <div class="notice">
            <p>If you did not request password recovery and received this email by mistake, ignore it.<br /><span
                    class="ru">Если вы не запрашивали восстановление пароля и получили это письмо по
                    ошибке, проигнорируйте его.</span></p>
        </div>
    </div>
</body>

</html>
';

        $subject = "Password recovery / Восстановление пароля";

        $this->sendEmail($email, $subject, $body);
    }

    private function confirmationEmail($email, $code)
    {

        $body = '<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation of registration</title>
    <style>
        body {
            text-align: center;
            font-family: Arial, sans-serif;
            margin: 40px;
        }

        .container {
            background-color: #f7f7f7;
            padding: 20px;
            border-radius: 5px;
        }

        .code {
            font-weight: bold;
            font-size: 24px;
            margin: 20px 0;
        }

        .notice {
            color: red;
            margin-top: 20px;
        }

        .ru {
            font-size: 12px;

        }

        .logo {
            display: block;
            margin: 0 auto 20px auto;
            border-radius: 20px;
            max-width: 100px;
            /* Вы можете изменить размеры по своему усмотрению */
        }
    </style>
</head>

<body>
    <div class="container">
       

        <h2>Confirmation of registration<br />
            <p class="ru">Подтверждение регистрации</p>
        </h2>


        <p>Thank you for registering! To complete the registration process, please enter the following confirmation
            code:

            <br /><span class="ru">Благодарим за регистрацию! Для завершения процесса регистрации, пожалуйста,
                введите следующий код подтверждения:</span>
        </p>


        <div class="code">' . $code . '</div>

        <p>This code is valid for 15 minutes.<br /><span class="ru">Этот код действителен в течение 15 минут.</span>
        </p>

        <div class="notice">
            <p>If you have not registered and received this email by mistake, please ignore
                it.<br /><span class="ru">Если вы не регистрировались и получили это письмо по
                    ошибке, проигнорируйте его.</span></p>
        </div>
    </div>
</body>

</html>
';

        $subject = "Confirmation of registration / Подтверждение регистрации";

        $this->sendEmail($email, $subject, $body);
    }
}
