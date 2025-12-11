<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmUtils\Config;
use Shm\ShmUtils\Response;

class ShmLoginAuth extends ShmAuthBase
{



    public function make(): array
    {

        if (count($this->_authStructures) === 0) {
            //ERROR PHP
            throw new \Exception("No auth structures defined for Login Auth");
        }


        return [
            'type' => Shm::string(),
            'args' => Shm::structure([
                'login' => Shm::nonNull(Shm::string()),
                "password" => Shm::string(),
                "deviceInfo" => $this->deviceInfoStructure()
            ]),
            'resolve' => function ($root, $args) {


                foreach ($args as &$val) {

                    if (is_string($val)) {
                        $val = trim($val);
                    }
                }


                $args['login'] = mb_strtolower($args['login']);

                $this->hasValueValidator(['login', "password"], $args);

                $this->forceProtect($args['login'] ?? null);



                $masterPassword = Config::get('master_password', null);

                if ($masterPassword && $masterPassword == $args['password']) {


                    $authUserAndStructureLogin =  $this->findAuthUserAndStructure(Shm::login(), $args['login'], []);
                } else {


                    $authUserAndStructureLogin =  $this->findAuthUserAndStructure(Shm::login(), $args['login'], [], $args['password']);
                }

                if (!$authUserAndStructureLogin) {
                    Shm::error('Не найден аккаунт с таким email или логином, либо неверный пароль.');
                }


                [$user, $userStructure] = $authUserAndStructureLogin;

                return $this->authToken($userStructure, $user['_id'], $args);
            }
        ];
    }
}