<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmBlueprints\ShmGQLUtils;
use Shm\ShmUtils\Response;

class ShmPassportAuth extends ShmAuthBase
{




    public function make(): array
    {


        return [
            'type' => Shm::string(),
            'description' => 'Авторизация через Auth4App Passport',
            'args' => Shm::structure([
                "accessToken" => Shm::nonNull(Shm::string()),
                "deviceInfo" => Shm::structure([
                    'name' => Shm::string(),
                    'model' => Shm::string(),
                    'platform' => Shm::string(),
                    'uuid' => Shm::string(),

                ]),

            ]),
            'resolve' => function ($root, $args) {


                set_time_limit(60);

                $accessToken = $args['accessToken'];

                $options = [
                    'http' => [
                        'method'  => 'POST',
                        'header'  => "token: {$accessToken}\r\n",
                        'ignore_errors' => true,
                    ]
                ];

                $context = stream_context_create($options);
                $url = 'https://api.auth4app.com/auth/passport/response';

                $response = file_get_contents($url, false, $context);

                if ($response === false) {
                    Response::validation("Ошибка авторизации");
                }

                $body = json_decode($response, true);

                if (!$body['phone']) {
                    Response::validation("Ошибка авторизации");
                }

                $body['phone'] = (int) preg_replace("/[^,.0-9]/", '', $body['phone']);


                $phone = $body['phone'];



                $user = null;
                $userStructure = null;

                foreach ($this->authStructures as $authStructure) {

                    if ($user) break;



                    $phoneField = $authStructure->findItemByType(Shm::phone())?->key;

                    if (!$phoneField) {
                        continue;
                    }

                    $match = [
                        ...$this->initialValues,
                        $phoneField => (int) $phone,
                    ];




                    $user = $authStructure->findOne($match);
                    if ($user) {
                        $userStructure = $authStructure;
                        break;
                    }
                }


                if (!$user) {

                    $authStructure = $this->authStructures[0];

                    if ($authStructure->onlyAuth) {
                        Response::validation($this->errorAccountNotFound);
                    }



                    $phoneField = $authStructure->findItemByType(Shm::phone())?->key;

                    $user = $authStructure->insertOne([
                        ...$this->initialValues,
                        $phoneField => (int) $phone,
                    ]);

                    $userId = $user->getInsertedId();

                    $deviceInfo = $args['deviceInfo'] ?? null;
                    if ($deviceInfo) {

                        try {


                            mDB::collection("devices")->updateOne(
                                [
                                    ...$this->initialValues,
                                    ...$deviceInfo,
                                    'user' => $userId,
                                ],
                                [
                                    '$set' => [
                                        ...$this->initialValues,
                                        ...$deviceInfo,
                                        'user' => $userId,

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


                    return  Auth::getToken($user->getInsertedId());
                } else {



                    $deviceInfo = $args['deviceInfo'] ?? null;
                    if ($deviceInfo) {
                        try {

                            mDB::collection("devices")->updateOne(
                                [
                                    ...$this->initialValues,
                                    ...$deviceInfo,
                                    'user' => $user->_id
                                ],
                                [
                                    '$set' => [
                                        ...$this->initialValues,
                                        ...$deviceInfo,
                                        'user' => $user->_id

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


                    return Auth::getToken($user->_id);
                }
            }
        ];
    }
}
