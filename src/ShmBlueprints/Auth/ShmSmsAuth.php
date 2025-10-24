<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmUtils\Response;

/**
 * SMS authentication handler
 * 
 * This class handles SMS-based authentication including sending codes
 * and verifying phone numbers.
 */
class ShmSmsAuth extends ShmAuthBase
{
    private string $smsAuthCollection = "_sms_auth";
    private array $testPhones = [
        79201111111,
        79202222222,
    ];
    private mixed $smsSendFunctionHandler = null;
    private mixed $smsCodeFunctionHandler = null;

    /**
     * Set SMS sending function handler
     * 
     * @param callable $handler Function that accepts phone number and code, returns true/false
     * @return static
     */
    public function setSmsSendFunctionHandler(callable $handler): static
    {
        $this->smsSendFunctionHandler = $handler;
        return $this;
    }

    /**
     * Set SMS code generation function handler
     * 
     * @param callable $handler Function that generates SMS code
     * @return static
     */
    public function setSmsCodeFunctionHandler(callable $handler): static
    {
        $this->smsCodeFunctionHandler = $handler;
        return $this;
    }

    private function getCode($phone)
    {
        if ($this->smsCodeFunctionHandler) {
            return call_user_func($this->smsCodeFunctionHandler, $phone);
        }

        return \random_int(1111, 9999);
    }

    private function sendSms($phone, $code)
    {
        if ($this->smsSendFunctionHandler) {
            return call_user_func($this->smsSendFunctionHandler, $phone, $code);
        }

        return false;
    }

    /**
     * Время жизни SMS кода в секундах
     * @var int
     */
    private $timeLiveSmsCode = 60 * 5;


    public function make(): array
    {

        if (count($this->_authStructures) === 0) {
            //ERROR PHP
            throw new \Exception("No auth structures defined for Sms Auth");
        }


        return [
            'type' => Shm::string(),
            'args' => Shm::structure([

                "phone" => Shm::nonNull(Shm::string()),
                'code' => Shm::string(),
                "deviceInfo" => $this->deviceInfoStructure()

            ]),
            'resolve' => function ($root, $args) {

                $phone = (int) preg_replace("/[^,.0-9]/", '', $args['phone']);


                if (isset($args['code'])) {


                    $findAuthUser = mDB::collection($this->smsAuthCollection)->findOne(
                        [
                            "phone" => (int) $phone,
                            "code" => (int) $args['code'],
                            "created_at" => ['$gt' => $this->timeLiveSmsCode],
                            "ip" => $_SERVER['REMOTE_ADDR'] ?? null,
                        ],
                        [
                            "sort" => ["_id" => -1]
                        ]
                    );



                    if (!$findAuthUser) {

                        Response::validation("Неверный SMS код");
                    }


                    $findAuthUserAndStructure =  $this->findAuthUserAndStructure(Shm::phone(), (int) $phone, []);


                    if (!$findAuthUserAndStructure) {


                        $regNewUser = $this->regNewUser(Shm::phone(), (int) $phone, []);

                        if ($regNewUser) {
                            [$user, $authStructure] = $regNewUser;
                            return $this->authToken($authStructure, $user->_id, $args);
                        }


                        Response::validation("Не найден аккаунт с таким номером телефона.");
                    }

                    [$user, $userStructure] = $findAuthUserAndStructure;


                    return $this->authToken($userStructure, $user->_id, $args);
                } else {




                    if (in_array(+$phone, $this->testPhones)) {
                        $code = 9876;

                        mDB::collection($this->smsAuthCollection)->insertOne([
                            "phone" => (int) $phone,
                            "code" => $code,
                            "created_at" => time(),
                            "ip" => $_SERVER['REMOTE_ADDR'] ?? null,
                        ]);
                        return null;
                    }



                    //Проверяем есть ли уже код с такого номера и IP в последние $timeLiveSmsCode секунд
                    $findAuthUser = mDB::collection($this->smsAuthCollection)->findOne([
                        "phone" => (int) $phone,
                        "created_at" => ['$gt' => time() - $this->timeLiveSmsCode],
                        "ip" => $_SERVER['REMOTE_ADDR'] ?? null,
                    ], [
                        "sort" => ["_id" => -1]
                    ]);


                    if ($findAuthUser) {


                        mDB::collection($this->smsAuthCollection)->insertOne([
                            "reSend" => true,
                            "repeat_from" => $findAuthUser->created_at,
                            "phone" => (int) $phone,
                            "code" =>  $findAuthUser->code,
                            "ip" => $_SERVER['REMOTE_ADDR'] ?? null,
                        ]);


                        return null;
                    }



                    $code = $this->getCode($phone);

                    mDB::collection($this->smsAuthCollection)->insertOne([
                        "phone" => (int) $phone,
                        "code" => (int) $code,
                        "ip" => $_SERVER['REMOTE_ADDR'] ?? null,
                    ]);


                    if (in_array(+$phone, $this->testPhones)) {
                        return null;
                    }
                    $this->sendSms($phone, $code);

                    return null;
                }
            }
        ];
    }
}
