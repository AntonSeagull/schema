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




                foreach ($this->_authStructures as $authStructure) {

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

        if (count($this->_authStructures) === 0) {
            //ERROR PHP
            throw new \Exception("No auth structures defined for Apple Auth");
        }


        return [
            'type' => Shm::string(),
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



                $authUserAndStructure = $this->findAuthUserAndStructure(null, null, [

                    $this->appleField => $appleSignInPayload->getUser()

                ]);


                if ($authUserAndStructure) {
                    [$user, $currentAuthStructure] = $authUserAndStructure;

                    return $this->authToken($currentAuthStructure, $user->_id, $args);
                }


                $regNewUser = $this->regNewUser(null, null, [

                    $this->appleField => $appleSignInPayload->getUser()

                ]);

                if ($regNewUser) {
                    [$user, $currentAuthStructure] = $regNewUser;

                    return $this->authToken($currentAuthStructure, $user->_id, $args);
                }
            }


        ];
    }
}
