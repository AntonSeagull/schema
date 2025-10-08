<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;

use Shm\ShmUtils\Response;

class ShmCallAuth extends ShmAuthBase
{

    private $callAuthCollection = "_call_auth";

    private $testPhones = [
        79201111111,
        79202222222,
    ];
    private $getCodeFunctionHandler = null;


    /**
     * Время жизни CALL кода в секундах
     * @var int
     */
    private $timeLiveCallCode = 60 * 5;

    public function setGetCodeFunctionHandler($handler): static
    {
        $this->getCodeFunctionHandler = $handler;
        return $this;
    }


    private function getCode($phone)
    {
        if ($this->getCodeFunctionHandler) {
            return call_user_func($this->getCodeFunctionHandler, $phone);
        }

        return false;
    }


    public function make(): array
    {


        if (count($this->_authStructures) === 0) {
            //ERROR PHP
            throw new \Exception("No auth structures defined for Call Auth");
        }


        return [
            'type' => Shm::string(),
            'description' => 'Авторизация через входящий звонок',
            'args' => Shm::structure([
                "phone" => Shm::nonNull(Shm::string()),
                'code' => Shm::string(),
                "deviceInfo" => $this->deviceInfoStructure()
            ]),
            'resolve' => function ($root, $args) {

                //Ставим все поля для коллекции, чтобы не было проблем с доступом



                $phone = (int) preg_replace("/[^,.0-9]/", '', $args['phone']);



                if (isset($args['code'])) {



                    $findAuthUser = mDB::collection($this->callAuthCollection)->findOne(
                        [
                            "phone" => (int) $phone,
                            "code" => (int) $args['code'],
                            "created_at" => ['$gt' => $this->timeLiveCallCode],

                        ],
                        [
                            "sort" => ["_id" => -1]
                        ]
                    );



                    if (!$findAuthUser) {

                        Response::validation("Неверный код");
                    }


                    $findAuthUserAndStructure =  $this->findAuthUserAndStructure(Shm::phone(), (int) $phone, []);


                    if (!$findAuthUserAndStructure) {


                        $regNewUser = $this->regNewUser(Shm::phone(), (int) $phone, []);

                        if ($regNewUser) {
                            [$user, $authStructure] = $regNewUser;
                            return $this->authToken($authStructure, $user->_id, $args, true);
                        }


                        Response::validation("Не найден аккаунт с таким номером телефона.");
                    }

                    [$user, $userStructure] = $findAuthUserAndStructure;


                    return $this->authToken($userStructure, $user->_id, $args, false);
                } else {

                    if (in_array(+$phone, $this->testPhones)) {
                        $code = 9876;
                    } else {
                        $code =  $this->getCode((int) $phone);
                    }

                    mDB::collection($this->callAuthCollection)->insertOne([
                        "phone" => (int) $phone,
                        "code" => (int)$code,
                        "created_at" => time(),
                    ]);


                    if (in_array(+$phone, $this->testPhones)) {
                        return null;
                    }


                    return null;
                }
            }
        ];
    }
}
