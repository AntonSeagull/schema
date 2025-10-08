<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\Response;


class ShmSocAuth extends ShmAuthBase
{


    public function make(): array
    {


        if (count($this->_authStructures) === 0) {
            //ERROR PHP
            throw new \Exception("No auth structures defined for Social Auth");
        }

        return [
            'type' => Shm::string(),
            'args' => Shm::structure([

                'unsetId' => Shm::string(),
                'set' => Shm::bool(),

                "key" => Shm::string(),
                "deviceInfo" => $this->deviceInfoStructure()

            ]),
            'resolve' => function ($root, $args) {



                if (isset($args['unsetId']) && $args['unsetId']) {

                    Auth::authenticateOrThrow();


                    $authModel = $this->currentStructure();



                    $socialField =  $authModel->findItemByType(Shm::social())?->key;

                    if (!$socialField) {
                        Response::validation("Социальные сети не поддерживаются");
                    }


                    $user = $authModel->updateOne([
                        "_id" => Auth::getAuthOwner(),
                    ], [
                        '$pull' => [
                            $socialField => [
                                "id" => $args['unsetId']
                            ]
                        ],
                    ]);

                    return $this->authToken($authModel, Auth::getAuthOwner(), $args);
                }



                $this->hasValueValidator(['key'], $args);

                $context = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ]
                ]);

                $dataAuth = file_get_contents('http://api.auth4app.com/hash?key=' . $args['key'], false, $context);

                $data = json_decode($dataAuth, true);

                if ($data['type'] == 'error') {
                    Response::validation("Ошибка авторизации");
                }

                $userSoc = $data['data'];



                if (isset($args['set']) && $args['set'] == true) {


                    Auth::authenticateOrThrow();


                    $authModel = $this->currentStructure();



                    foreach ($this->_authStructures as $authStructureItem) {


                        $socialFieldLocal = $authStructureItem->findItemByType(Shm::social())?->key;

                        if ($socialFieldLocal) {
                            $authStructureItem->updateOne(
                                [
                                    $socialFieldLocal . ".id" => $userSoc['id'],
                                ],
                                [

                                    '$pull' => [
                                        $socialFieldLocal => [
                                            "id" => $userSoc['id']
                                        ],
                                    ],

                                ]
                            );
                        }
                    }

                    $socialField = $authModel->findItemByType(Shm::social())?->key;


                    if (!$socialField) {
                        Response::validation("Социальные сети не поддерживаются");
                    }





                    $user = $authModel->updateOne([
                        "_id" => Auth::getAuthOwner(),
                    ], [
                        '$push' => [$socialField  => $userSoc],
                    ]);

                    return  $this->authToken($authModel, Auth::getAuthOwner(), $args);
                } else {




                    $user = null;
                    $userStructure = null;
                    foreach ($this->_authStructures as $authStructureItem) {




                        $socialField = $authStructureItem->findItemByType(Shm::social())?->key;

                        if (!$socialField) {
                            continue;
                        }

                        $match = [

                            $socialField . ".id" => $userSoc['id'],

                        ];

                        $user = $authStructureItem->findOne($match);

                        if ($user) {
                            $userStructure = $authStructureItem;
                            break;
                        }
                    }



                    if ($user && $userStructure) {


                        $deviceInfo = $args['deviceInfo'] ?? null;
                        if ($deviceInfo) {

                            try {

                                mDB::collection("devices")->updateOne(
                                    [

                                        ...$deviceInfo,
                                        'user' => mDB::id($user['_id'])
                                    ],
                                    [
                                        '$set' => [

                                            ...$deviceInfo,
                                            'user' => mDB::id($user['_id'])

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



                        return $this->authToken($userStructure, $user['_id'], $args);
                    } else {

                        $authStructure = $this->_regStructures[0] ?? null;

                        if (!$authStructure) {
                            Response::validation($this->errorAccountNotFound);
                        }



                        $emailField = $authStructure->findItemByType(Shm::email())?->key;
                        $socialField = $authStructure->findItemByType(Shm::social())?->key;

                        if (!$socialField) {
                            Response::validation("Социальные сети не поддерживаются");
                        }

                        $nameField = $authStructure->findItemByKey('name')?->key;
                        $surnameField = $authStructure->findItemByKey('surname')?->key;
                        $photoField = $authStructure->findItemByType(Shm::fileImageLink())?->key;

                        $insert = [

                            $socialField => [$userSoc],
                        ];


                        if ($emailField) {
                            $insert[$emailField] = $userSoc['mail'] ?? null;
                        }
                        if ($nameField) {
                            $insert[$nameField] = $userSoc['name'] ?? null;
                        }
                        if ($surnameField) {
                            $insert[$surnameField] = $userSoc['surname'] ?? null;
                        }
                        if ($photoField) {
                            $insert[$photoField] = $userSoc['photo'] ?? null;
                        }




                        $user = $authStructure->insertOne($insert);

                        $authStructure->callEvent(StructureType::EVENT_AFTER_REGISTER, $user->getInsertedId());

                        return $this->authToken($authStructure, $user->getInsertedId(), $args);
                    }
                }
            }
        ];
    }
}
