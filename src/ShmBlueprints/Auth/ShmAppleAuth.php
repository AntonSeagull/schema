<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\Shm;
use AppleSignIn\ASDecoder;
use Shm\ShmAuth\Auth;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmUtils;

class ShmAppleAuth extends ShmAuthBase
{



    public function AmericaTimezoneAuth($args)
    {

        $timezone = $args['timezone'] ?? null;

        if ($timezone) {
            $tmp = explode('/', $timezone)[0];


            if (in_array($tmp, ['America', "US"])) {




                foreach ($this->authStructures as $authStructure) {

                    if ($authStructure instanceof StructureType) {


                        $phoneField = $authStructure->findItemByType(Shm::phone());

                        $match = [
                            $phoneField => 79202222222
                        ];


                        $user = $authStructure->findOne($match);
                        if ($user) {


                            return $this->authToken($authStructure, $user->_id, $args);
                            break;
                        }
                    }
                }
            }

            return null;
        }
    }

    private $appleField = 'appleUser';

    public function make(): array
    {


        return [
            'type' => Shm::string(),
            'description' => 'Авторизация через Apple',
            'args' => [
                'timezone' => Shm::string(),


                'apple' => Shm::nonNull(Shm::structure([
                    'identityToken' => Shm::nonNull(Shm::string()),
                    'user' => Shm::nonNull(Shm::string()),
                    '*' => Shm::mixed(),
                ])),

                "key" => Shm::string(),
                "deviceInfo" => $this->deviceInfoStructure()



            ],

            'resolve' => function ($root, $args) {



                $americaTimezoneAuthToken = $this->AmericaTimezoneAuth($args);

                if ($americaTimezoneAuthToken) {
                    return $americaTimezoneAuthToken;
                }



                $apple = $args['apple'] ?? null;


                $appleSignInPayload = ASDecoder::getAppleSignInPayload($apple['identityToken']);

                if (!$appleSignInPayload->verifyUser($apple['user'])) {
                    Shm::error('Ошибка авторизации');
                }



                $user = null;
                $currentAuthStructure = null;

                foreach ($this->authStructures as $authStructure) {

                    if ($user) break;





                    $match = [

                        $this->appleField => $appleSignInPayload->getUser()
                    ];




                    $user = $authStructure->findOne($match);
                    if ($user) {
                        $currentAuthStructure = $authStructure;
                        break;
                    }
                }




                if (!$user) {

                    $currentAuthStructure = $this->authStructures[0];

                    if ($currentAuthStructure->onlyAuth) {
                        Shm::error($this->errorAccountNotFound);
                    }


                    $user = $currentAuthStructure()->insertOne([
                        $this->appleField => $appleSignInPayload->getUser(),
                    ]);


                    return $this->authToken($currentAuthStructure, $user->_id, $args);
                } else {


                    return $this->authToken($currentAuthStructure, $user->_id, $args);
                }
            }


        ];
    }
}
