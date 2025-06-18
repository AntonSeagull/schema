<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmBlueprints\ShmGQLUtils;
use Shm\ShmUtils\Response;

class ShmMsgAuth extends ShmAuthBase
{





    public function make(): array
    {


        return [
            'type' => Shm::structure([
                "auth" =>  Shm::boolean(),
                "token" => Shm::string(),
                'code' => Shm::int(),
                "qrLink" => Shm::string(),
                'code_id' => Shm::string(),
                'links' =>  Shm::arrayOf(
                    Shm::structure([
                        'title' => Shm::string(),
                        "action" => Shm::enum(['sendMessage', 'makeCall']),
                        "contact" => Shm::string(),
                        'link' => Shm::string(),
                        'image' => Shm::string(),
                        'color' => Shm::string(),
                    ])
                ),
                'otherLinks' =>  Shm::arrayOf(
                    Shm::structure([
                        'title' => Shm::string(),
                        "action" => Shm::enum(['sendMessage', 'makeCall']),
                        "contact" => Shm::string(),
                        'link' => Shm::string(),
                        'image' => Shm::string(),
                        'color' => Shm::string(),
                    ])
                ),
            ]),
            'description' => 'Авторизация через Месенджеры',
            'args' => Shm::structure([

                'set' => Shm::boolean(),
                'phone' =>  Shm::nonNull(Shm::string()),
                'code_id' => Shm::string(),

            ]),
            'resolve' => function ($root, $args) {

                //Ставим все поля для коллекции, чтобы не было проблем с доступом



                if (isset($args['code_id']) && $args['code_id']) {
                    $result = json_decode(file_get_contents("http://api.auth4app.com/code/result?code_id=" . $args['code_id'] . "&api_key=a4d5b3fa5dc37d3590deda42cd17513ede4ee3dec9fe2ee71bac94f58d817f7c"), true);

                    if ($result['auth'] == true) {

                        $phone = (int) preg_replace("/[^,.0-9]/", '', $args['phone']);

                        if (isset($args['set']) && $args['set'] == true) {


                            Auth::authenticateOrThrow();

                            $authUser = Auth::getAuth();
                            $authStructure = Auth::getAuthStructure();




                            $phoneField =  $authStructure->findItemByType(Shm::phone())?->key;


                            foreach ($this->authStructures as $authStructure) {


                                $phoneFieldLocal = $authStructure->findItemByType(Shm::phone())?->key;
                                if (!$phoneFieldLocal) continue;

                                $authStructure->updateOne(
                                    [
                                        $phoneFieldLocal => (int) $phone,
                                    ],
                                    [

                                        '$unset' => [
                                            $phoneFieldLocal => 1
                                        ]

                                    ]
                                );
                            }


                            $user = $authStructure->updateOne([
                                "_id" => Auth::getAuthId(),
                            ], [
                                '$set' => [
                                    $phoneField => (int) $phone,
                                ]
                            ]);

                            return [
                                "auth" => true,

                            ];
                        } else {


                            $user = null;
                            $userStructure = null;

                            foreach ($this->authStructures as $authStructure) {

                                if ($user) break;



                                $phoneField = $authStructure->findItemByType(Shm::phone())?->key;

                                if (!$phoneField) continue;

                                $match = [
                                    ...$this->initialValues,
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
                                    ...$this->initialValues,
                                    $phoneField => (int) $phone,
                                ]);

                                return [
                                    "token" => Auth::getToken($user->getInsertedId()),
                                    "auth" => true,
                                ];
                            } else {


                                return [
                                    "token" => Auth::getToken($user->_id),
                                    "auth" => true,
                                ];
                            }
                        }
                    }
                } else {

                    $phone = (int) preg_replace("/[^,.0-9]/", '', $args['phone']);


                    $result = json_decode(file_get_contents("http://api.auth4app.com/code/get?phone=$phone&api_key=a4d5b3fa5dc37d3590deda42cd17513ede4ee3dec9fe2ee71bac94f58d817f7c"), true);

                    return $result;
                }
            }
        ];
    }
}
