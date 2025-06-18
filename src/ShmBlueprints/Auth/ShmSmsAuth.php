<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmBlueprints\ShmGQLUtils;
use Shm\ShmUtils\Response;

class ShmSmsAuth extends ShmAuthBase
{

    private $smsAuthCollection = "_sms_auth";


    private $testPhones = [
        79201111111,
        79202222222,
    ];

    private $smsSendFunctionHandler = null;

    private $smsCodeFunctionHandler = null;


    public function setSmsSendFunctionHandler($handler): self
    {
        $this->smsSendFunctionHandler = $handler;
        return $this;
    }

    public function setSmsCodeFunctionHandler($handler): self
    {
        $this->smsCodeFunctionHandler = $handler;
        return $this;
    }

    private function getCode($phone)
    {
        if ($this->smsCodeFunctionHandler) {
            return call_user_func($this->smsCodeFunctionHandler, $phone);
        }

        return rand(1111, 9999);
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
    private $timeLiveSmsCode = 60;


    public function make(): array
    {


        return [
            'type' => Shm::string(),
            'args' => Shm::structure([

                "phone" => Shm::nonNull(Shm::string()),
                'code' => Shm::string(),

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



                    $user = null;

                    foreach ($this->authStructures as $authStructure) {




                        $phoneField = $authStructure->findItemByType('phone');

                        if (!$phoneField) {
                            continue;
                        }

                        $match = [
                            ...$this->initialValues,
                            $phoneField->key => (int) $phone,
                        ];

                        $user =  mDB::collection($authStructure->collection)->findOne($match, [
                            'projection' => ['_id' => 1]
                        ]);

                        if ($user) {
                            $userStructure = $authStructure;

                            break;
                        }
                    }

                    if (!$user) {



                        if ($authStructure->onlyAuth) {
                            Response::validation("Регистрация по SMS ограничена");
                        }

                        $authStructure = $this->authStructures[0] ?? null;

                        if (!$authStructure) {
                            Response::validation("Регистрация по SMS недоступна");
                        }



                        $phoneField = $authStructure->findItemByType('phone');

                        if (!$phoneField) {
                            Response::validation("Регистрация по SMS не поддерживается");
                        }



                        $insertData = [
                            ...$this->initialValues,
                            $phoneField->key => (int) $phone,
                        ];



                        $insertData = $authStructure->normalize($insertData, true);

                        $user = mDB::collection($authStructure->collection)->insertOne($insertData);

                        return  Auth::getToken($user->getInsertedId());
                    } else {

                        return Auth::getToken($user->_id);
                    }
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
                        "code" => (int) $args['code'],
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
