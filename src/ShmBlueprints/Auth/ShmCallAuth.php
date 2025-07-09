<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;

use Shm\ShmUtils\Response;

class ShmCallAuth extends ShmAuthBase
{



    private $testPhones = [
        79201111111,
        79202222222,
    ];
    private $getCodeFunctionHandler = null;


    public function setGetCodeFunctionHandler($handler): self
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



        return [
            'type' => Shm::string(),
            'description' => 'Авторизация через входящий звонок',
            'args' => Shm::structure([
                "phone" => Shm::nonNull(Shm::string()),
                'code' => Shm::string(),
            ]),
            'resolve' => function ($root, $args) {

                //Ставим все поля для коллекции, чтобы не было проблем с доступом



                $phone = (int) preg_replace("/[^,.0-9]/", '', $args['phone']);



                if (isset($args['code'])) {


                    $findAuthUser = mDB::collection("_call_auth")->findOne([
                        "phone" => (int) $phone,
                        "code" => (int) $args['code'],
                        "created_at" => ['$gt' => time() - 60 * 5],
                    ]);



                    if (!$findAuthUser) {
                        Response::validation('Неверный код');
                    }






                    $user = null;
                    $userStructure = null;

                    foreach ($this->authStructures as $authStructure) {

                        if ($user) break;



                        $phoneField = $authStructure->findItemByType(Shm::phone())?->key;

                        $match = [
                            $phoneField => (int) $phone,
                        ];




                        $user = $authStructure->findOne($match);
                        if ($user) {
                            $userStructure = $authStructure;
                        }
                    }


                    if (!$user) {

                        $authStructure = $this->authStructures[0];

                        if ($authStructure->onlyAuth) {
                            Response::validation($this->errorAccountNotFound);
                        }




                        $phoneField = $authStructure->findItemByType(Shm::phone())?->key;
                        if (!$phoneField) {
                            Response::validation("Авторизация по телефону не поддерживается");
                        }

                        $user = $authStructure->insertOne([
                            $phoneField => (int) $phone,
                        ]);

                        return  Auth::genToken($authStructure, $user->getInsertedId());
                    } else {


                        return Auth::genToken($userStructure, $user->_id);
                    }
                } else {

                    if (in_array(+$phone, $this->testPhones)) {
                        $code = 9876;
                    } else {
                        $code =  $this->getCode((int) $phone);
                    }

                    mDB::collection("_call_auth")->insertOne([
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
