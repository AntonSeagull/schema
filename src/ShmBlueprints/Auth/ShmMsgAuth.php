<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;

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
                    ])->staticBaseTypeName('ShmMsgAuthLink')
                ),
                'otherLinks' =>  Shm::arrayOf(
                    Shm::structure([
                        'title' => Shm::string(),
                        "action" => Shm::enum(['sendMessage', 'makeCall']),
                        "contact" => Shm::string(),
                        'link' => Shm::string(),
                        'image' => Shm::string(),
                        'color' => Shm::string(),
                    ])->staticBaseTypeName('ShmMsgAuthLink')
                ),
            ])->staticBaseTypeName('ShmMsgAuth'),
            'description' => 'Авторизация через Месенджеры',
            'args' => Shm::structure([

                'set' => Shm::boolean(),
                'phone' =>  Shm::nonNull(Shm::string()),
                'code_id' => Shm::string(),

            ]),
            'resolve' => function ($root, $args) {

                //Ставим все поля для коллекции, чтобы не было проблем с доступом



                if (isset($args['code_id']) && $args['code_id']) {


                    $context = stream_context_create([
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                        ]
                    ]);


                    $result = json_decode(file_get_contents(
                        "http://api.auth4app.com/code/result?code_id=" . $args['code_id'] . "&api_key=a4d5b3fa5dc37d3590deda42cd17513ede4ee3dec9fe2ee71bac94f58d817f7c",
                        false,
                        $context
                    ), true);

                    if ($result['auth'] == true) {

                        $phone = (int) preg_replace("/[^,.0-9]/", '', $args['phone']);

                        if (isset($args['set']) && $args['set'] == true) {


                            Auth::authenticateOrThrow();

                            $currentAuthStructure = $this->currentStructure();






                            $phoneField =  $currentAuthStructure->findItemByType(Shm::phone())?->key;

                            if (!$phoneField) {
                                Response::validation("Авторизация по телефону не поддерживается");
                            }

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


                            $user = $currentAuthStructure->updateOne([
                                "_id" => Auth::getAuthOwner(),
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

                                return [
                                    "token" => Auth::genToken($authStructure, $user->getInsertedId()),
                                    "auth" => true,
                                ];
                            } else {


                                return [
                                    "token" => Auth::genToken($userStructure, $user->_id),
                                    "auth" => true,
                                ];
                            }
                        }
                    }
                } else {

                    $phone = (int) preg_replace("/[^,.0-9]/", '', $args['phone']);


                    $context = stream_context_create([
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                        ]
                    ]);



                    $result = json_decode(file_get_contents("http://api.auth4app.com/code/get?phone=$phone&api_key=a4d5b3fa5dc37d3590deda42cd17513ede4ee3dec9fe2ee71bac94f58d817f7c", false, $context), true);

                    return $result;
                }
            }
        ];
    }
}
