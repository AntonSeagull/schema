<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmBlueprints\ShmGQLUtils;
use Shm\ShmUtils\Response;


class ShmSocAuth extends ShmAuthBase
{


    public function make(): array
    {


        return [
            'type' => Shm::string(),
            'args' => Shm::structure([

                'unsetId' => Shm::string(),
                'set' => Shm::bool(),

                "key" => Shm::string(),
                "deviceInfo" => Shm::structure([
                    'name' => Shm::string(),
                    'model' => Shm::string(),
                    'platform' => Shm::string(),
                    'uuid' => Shm::string(),

                ]),

            ]),
            'resolve' => function ($root, $args) {



                if (isset($args['unsetId']) && $args['unsetId']) {

                    Auth::authenticateOrThrow();


                    $authModel = Auth::getAuthStructure();



                    $socialField =  $authModel->findItemByType(Shm::social())?->key;

                    if (!$socialField) {
                        Response::validation("Социальные сети не поддерживаются");
                    }


                    $user = $authModel->updateOne([
                        "_id" => Auth::getAuthId(),
                    ], [
                        '$pull' => [
                            $socialField => [
                                "id" => $args['unsetId']
                            ]
                        ],
                    ]);

                    return Auth::getToken(Auth::getAuthId());
                }



                $this->hasValueValidator(['key'], $args);

                $dataAuth = file_get_contents('https://api.auth4app.com/hash?key=' . $args['key']);

                $data = json_decode($dataAuth, true);

                if ($data['type'] == 'error') {
                    Response::validation("Ошибка авторизации");
                }

                $userSoc = $data['data'];



                if (isset($args['set']) && $args['set'] == true) {


                    Auth::authenticateOrThrow();


                    $authModel = Auth::getAuthStructure();



                    foreach ($this->authStructures as $authStructureItem) {


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
                        "_id" => Auth::getAuthId(),
                    ], [
                        '$push' => [$socialField  => $userSoc],
                    ]);

                    return  Auth::getToken(Auth::getAuthId());
                } else {

                    $user = null;
                    $userStructure = null;
                    foreach ($this->authStructures as $authStructureItem) {




                        $socialField = $authStructureItem->findItemByType(Shm::social())?->key;

                        if (!$socialField) {
                            continue;
                        }

                        $match = [
                            ...$this->initialValues,
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
                                        ...$this->initialValues,
                                        ...$deviceInfo,
                                        'user' => mDB::id($user['_id'])
                                    ],
                                    [
                                        '$set' => [
                                            ...$this->initialValues,
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



                        return Auth::getToken($user['_id']);
                    } else {

                        $authStructure = $this->authStructures[0];

                        if ($authStructure->onlyAuth) {
                            Response::validation($this->errorAccountNotFound);
                        }



                        $emailField = $authStructure->findItemByType(Shm::email())?->key;
                        $socialField = $authStructure->findItemByType(Shm::social())?->key;

                        if (!$socialField) {
                            Response::validation("Социальные сети не поддерживаются");
                        }

                        $nameField = $authStructure->findItemByKey('name');
                        $surnameField = $authStructure->findItemByKey('surname');
                        $photoField = $authStructure->findItemByType(Shm::fileImageLink());

                        $insers = [
                            ...$this->initialValues,
                            $socialField => [$userSoc],
                        ];

                        if ($emailField) {
                            $insers[$emailField] = $userSoc['mail'] ?? null;
                        }
                        if ($nameField) {
                            $insers[$nameField] = $userSoc['name'] ?? null;
                        }
                        if ($surnameField) {
                            $insers[$surnameField] = $userSoc['surname'] ?? null;
                        }
                        if ($photoField) {
                            $insers[$photoField] = $userSoc['photo'] ?? null;
                        }


                        $user = $authStructure->insertOne($insers);

                        $deviceInfo = $args['deviceInfo'] ?? null;
                        if ($deviceInfo) {

                            try {

                                mDB::collection("devices")->updateOne(
                                    [
                                        ...$this->initialValues,
                                        ...$deviceInfo,
                                        'user' => mDB::id($user['_id'])
                                    ],
                                    [
                                        '$set' => [
                                            ...$this->initialValues,
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


                        return Auth::getToken($user->getInsertedId());
                    }
                }
            }
        ];
    }
}