<?php

namespace Shm\ShmBlueprints\Auth;

use Illuminate\Support\Arr;
use Sentry\Util\Str;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmDB\mDB;
use Shm\ShmTypes\BaseType;
use Shm\ShmUtils\Inflect;
use Shm\ShmUtils\Response;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\Config;

/**
 * Base authentication class
 * 
 * This abstract class provides common functionality for all authentication
 * handlers including structure management, validation, and token handling.
 */
abstract class ShmAuthBase
{
    public ?string $title = null;
    public ?string $key = null;

    /**
     * Authentication structures
     * @var StructureType[]
     */
    public array $_authStructures = [];

    /**
     * Registration structures
     * @var StructureType[]
     */
    public array $_regStructures = [];

    public ?string $description = null;
    public mixed $pipeline = null;


    public function currentStructure(): ?StructureType
    {
        $currentAuthStructure = null;
        foreach ($this->_authStructures as $authStructure) {
            if ($authStructure->collection == Auth::getAuthCollection()) {
                $currentAuthStructure = $authStructure;
                break;
            }
        }


        if (!$currentAuthStructure) {
            Response::unauthorized();
        }

        return $currentAuthStructure;
    }



    public $errorAccountNotFound = "Ваша учетная запись не найдена.";


    /**
     * Validate that required keys have values
     * 
     * @param array $keys Required keys to validate
     * @param array $params Parameters to validate
     * @throws \Exception If validation fails
     */
    public function hasValueValidator(array $keys, array $params): void
    {
        foreach ($keys as $key) {
            if (!isset($params[$key]) || empty($params[$key])) {
                Response::validation("Заполните все необходимые поля");
            }
        }
    }


    /**
     * Check if email is valid
     * 
     * @param string $email Email to validate
     * @return bool True if email is valid
     */
    public function isEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function __construct()
    {
        // Constructor implementation
    }


    /**
     * Set authentication structures
     * 
     * @param array $authStructures Authentication structures
     * @return static
     */
    public function auth(array $authStructures): static
    {
        $this->_authStructures = $authStructures;
        return $this;
    }

    /**
     * Set registration structures
     * 
     * @param array $regStructures Registration structures
     * @return static
     */
    public function reg(array $regStructures): static
    {
        $this->_regStructures = $regStructures;

        return $this;
    }

    public function deviceInfoStructure()
    {
        return Shm::structure([
            'name' => Shm::string(),
            'model' => Shm::string(),
            'platform' => Shm::string(),
            'uuid' => Shm::string(),
        ]);
    }

    public function  authToken(StructureType $structure,  $_id, $args): string
    {




        $deviceInfo = $args['deviceInfo'] ?? null;
        if ($deviceInfo) {

            try {

                mDB::_collection("devices")->updateOne(
                    [
                        ...$deviceInfo,
                        'user' => mDB::id($_id),
                    ],
                    [
                        '$set' => [
                            ...$deviceInfo,
                            'auth_collection' => $structure->collection,
                            'user' => mDB::id($_id)

                        ],
                    ],
                    [
                        'upsert' => true,
                    ]
                );
            } catch (\Exception $e) {
                \Sentry\captureException($e);
                $deviceInfo = null;
            }
        }


        //Проверка есть ли поле email в структуре
        $emailField = $structure->findItemByType(Shm::email())?->key ?? null;

        $cancelKey = null;
        if ($emailField) {
            $email = $structure->findOne(['_id' => $_id])[$emailField] ?? null;
            if ($email && $this->isEmail($email)) {

                $cancelKey = hash("sha512", time() . bin2hex(openssl_random_pseudo_bytes(32) . $_id));


                [
                    $body,
                    $subject
                ] = ShmMailTpl::successLogin($cancelKey);

                $this->sendEmail(
                    $email,
                    $subject,
                    $body
                );
            }
        }

        $structure->callEvent(StructureType::EVENT_AFTER_LOGIN, $_id);


        return Auth::genToken($structure, $_id, $cancelKey);
    }




    public function make(): ?array
    {
        return [];
    }


    public function sendEmail($mailTo, $subject, $body)
    {

        try {

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
                encryption: $encryption,
                timeout: 5
            );
            $mailer->send($mail);
        } catch (\Exception $e) {
            \Sentry\captureException($e);
            return;
        }
    }



    public function forceProtect($startDataString)
    {

        if (!$startDataString) return;
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


    public function findAuthUserAndStructure(BaseType | null $field, $value, array $match, $password = null): ?array
    {



        foreach ($this->_authStructures as $authStructure) {


            if ($field) {

                $findField = $authStructure->findItemByType($field)?->key;


                if (!$findField) {
                    continue;
                }

                $match  = [

                    $findField => $value,
                    ...$match
                ];
            } else {
                $match  = [

                    ...$match
                ];
            }



            if ($password) {
                $passwordField = $authStructure->findItemByType(Shm::password())?->key;

                if (!$passwordField) {
                    continue;
                }


                if ($passwordField) {

                    $match  = [

                        ...$match,
                        $passwordField => Auth::getPassword($password)
                    ];
                }
            }



            $user = $authStructure->findOne(
                $match
            );

            if ($user) {

                return [$user, $authStructure];
            }
        }
        return null;
    }

    public function regNewUser(BaseType | null $field, $value, array $set): ?array
    {


        $regStructure = $this->_regStructures[0] ?? null;

        if (!$regStructure) {
            Shm::error($this->errorAccountNotFound);
        }


        if ($field) {

            $findField = $regStructure->findItemByType($field)?->key;
            if (!$findField) {
                Shm::error($this->errorAccountNotFound);
            }

            $set  = [

                $findField => $value,
                ...$set
            ];
        } else {
            $set  = [

                ...$set
            ];
        }


        $insert = $regStructure->insertOne($set);



        $user = $regStructure->findOne(
            [
                "_id" => $insert->getInsertedId()
            ]
        );

        if ($user) {

            $regStructure->callEvent(StructureType::EVENT_AFTER_REGISTER, $user->_id);


            return [$user, $regStructure];
        }

        return null;
    }
}
