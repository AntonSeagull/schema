<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;

use Shm\ShmUtils\Response;

class ShmPassportAuth extends ShmAuthBase
{




    public function make(): array
    {
        if (count($this->_authStructures) === 0) {
            //ERROR PHP
            throw new \Exception("No auth structures defined for Passport Auth");
        }


        return [
            'type' => Shm::string(),
            'description' => 'Авторизация через Auth4App Passport',
            'args' => Shm::structure([
                "accessToken" => Shm::nonNull(Shm::string()),
                "deviceInfo" => $this->deviceInfoStructure()

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
                $url = 'http://api.auth4app.com/auth/passport/response';

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



                $findAuthUserAndStructure =  $this->findAuthUserAndStructure(Shm::phone(), (int) $phone, []);

                if (!$findAuthUserAndStructure) {

                    $regNewUser = $this->regNewUser(Shm::phone(), (int) $phone, []);

                    [$user, $regStructure] = $regNewUser;

                    return [
                        "token" => $this->authToken($regStructure, $user->_id, $args),
                        "auth" => true,
                    ];
                }

                [$user, $userStructure] = $findAuthUserAndStructure;


                return $this->authToken($userStructure, $user->_id, $args);
            }
        ];
    }
}
