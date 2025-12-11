<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\ShmDB\mDB;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmUtils\Config;
use Shm\ShmUtils\Response;

class ShmEmailAuth extends ShmAuthBase
{




    public function make(): array
    {

        if (count($this->_authStructures) === 0) {
            //ERROR PHP
            throw new \Exception("No auth structures defined for Email Auth");
        }


        return [
            'type' => Shm::string(),
            'description' => 'Авторизация через Email (или логин)',
            'args' => Shm::structure([

                "withEmailCode" => Shm::boolean(),
                "code" => Shm::int(),
                "login" => Shm::nonNull(Shm::string()),

                "password" => Shm::string(),
                "deviceInfo" => $this->deviceInfoStructure()
            ]),
            'resolve' => function ($root, $args) {

                //Ставим все поля для коллекции, чтобы не было проблем с доступом


                foreach ($args as &$val) {

                    if (is_string($val)) {
                        $val = trim($val);
                    }
                }

                $args['login'] = mb_strtolower($args['login']);

                $this->forceProtect($args['login'] ?? null);



                if (isset($args['withEmailCode']) && $args['withEmailCode'] == true) {

                    $this->hasValueValidator(['login'], $args);

                    if (isset($args['code'])) {

                        $this->hasValueValidator(['password'], $args);


                        $authUserAndStructureEmail =  $this->findAuthUserAndStructure(Shm::email(), $args['login'], [
                            "code" => $args['code'],
                        ]);

                        $authUserAndStructureLogin =  $this->findAuthUserAndStructure(Shm::login(), $args['login'], [
                            "code" => $args['code'],
                        ]);



                        if (!$authUserAndStructureEmail && !$authUserAndStructureLogin) {
                            Shm::error('Вы указали неверный код.');
                        }

                        if ($authUserAndStructureEmail) {
                            [$user, $userStructure] = $authUserAndStructureEmail;
                        } else {
                            [$user, $userStructure] = $authUserAndStructureLogin;
                        }



                        $passwordField = $userStructure->findItemByType(Shm::password())?->key;

                        $userStructure->updateOne(
                            [
                                "_id" => $user['_id'],
                            ],
                            [
                                '$set' => [
                                    $passwordField => Auth::getPassword($args['password']),
                                ],
                                '$unset' => [
                                    "code" => 1,

                                ],
                            ]
                        );

                        return $this->authToken($userStructure, $user['_id'], $args);
                    } else {



                        $code = rand(111111, 999999);

                        $authUserAndStructureEmail =  $this->findAuthUserAndStructure(Shm::email(), $args['login'], []);

                        $authUserAndStructureLogin =  $this->findAuthUserAndStructure(Shm::login(), $args['login'], []);

                        if (!$authUserAndStructureEmail && !$authUserAndStructureLogin) {


                            if ($this->isEmail($args['login'])) {
                                $regNewUser =   $this->regNewUser(Shm::email(), $args['login'], [
                                    "code" => $code,
                                    "code_created_at" => time(),
                                ]);

                                if (!$regNewUser) {
                                    Shm::error('Регистрация по Email на текущий момент недоступна.');
                                }

                                [$user, $userStructure] = $regNewUser;


                                $userEmail = $args['login'];

                                [$body, $subject] = ShmMailTpl::tplConfirmationEmail($code);

                                $this->sendEmail($userEmail, $subject, $body);

                                $userStructure->updateOne(
                                    [
                                        "_id" => $user['_id'],
                                    ],
                                    [
                                        '$set' => [
                                            "code" => $code,
                                            "code_created_at" => time(),
                                        ],
                                    ]
                                );

                                return null;
                            }

                            Shm::error("Регистрация на указанный email недоступна.");
                        }

                        if ($authUserAndStructureEmail) {
                            [$user, $userStructure] = $authUserAndStructureEmail;
                        } else {
                            [$user, $userStructure] = $authUserAndStructureLogin;
                        }





                        $emailField = $userStructure->findItemByType(Shm::email())?->key;

                        $userEmail = $user[$emailField] ?? null;

                        if (!$userEmail) {
                            Shm::error('Email для восстановления пароля не задан. Обратитесь в поддержку.');
                        }







                        [$body, $subject] = ShmMailTpl::tplRecoveryEmail($code);



                        $this->sendEmail($userEmail, $subject, $body);

                        $userStructure->updateOne(
                            [
                                "_id" => $user['_id'],
                            ],
                            [
                                '$set' => [
                                    "code" => $code,
                                    "code_created_at" => time(),
                                ],
                            ]
                        );

                        return null;
                    }
                } else {



                    $this->hasValueValidator(['login', "password"], $args);


                    $masterPassword = Config::get('master_password', null);

                    if ($masterPassword && $masterPassword == $args['password']) {

                        $authUserAndStructureEmail =  $this->findAuthUserAndStructure(Shm::email(), $args['login'], []);

                        $authUserAndStructureLogin =  $this->findAuthUserAndStructure(Shm::login(), $args['login'], []);
                    } else {



                        $authUserAndStructureEmail =  $this->findAuthUserAndStructure(Shm::email(), $args['login'], [], $args['password']);

                        $authUserAndStructureLogin =  $this->findAuthUserAndStructure(Shm::login(), $args['login'], [], $args['password']);
                    }

                    if (!$authUserAndStructureEmail && !$authUserAndStructureLogin) {
                        Shm::error('Пароль указан неверно.');
                    }

                    if ($authUserAndStructureEmail) {
                        [$user, $userStructure] = $authUserAndStructureEmail;
                    } else {
                        [$user, $userStructure] = $authUserAndStructureLogin;
                    }

                    return $this->authToken($userStructure, $user['_id'], $args);
                }
            }
        ];
    }

    public function prepare(): array
    {

        return [
            'type' => Shm::structure([
                'find' => Shm::boolean(),
                'isEmail' => Shm::boolean(),
                'canRegister' => Shm::boolean(),
            ]),
            'description' => 'Подготовка к авторизации через Email (или логин)',
            'args' => Shm::structure([

                "login" => Shm::nonNull(Shm::string()),
            ]),
            'resolve' => function ($root, $args) {


                $login = trim($args['login'] ?? '');

                $login = mb_strtolower($login);

                $canRegister =  isset($this->_regStructures[0]) ? true : false;

                if (!$login) {
                    return [
                        'find' => false,
                        'isEmail' => false,
                        'canRegister' => $canRegister,
                    ];
                }

                $this->forceProtect($login);


                $authUserAndStructureEmail =  $this->findAuthUserAndStructure(Shm::email(),  $login, []);

                if ($authUserAndStructureEmail) {
                    return [
                        'find' => true,
                        'isEmail' => true,
                    ];
                }

                $authUserAndStructureLogin =  $this->findAuthUserAndStructure(Shm::login(),  $login, []);

                if ($authUserAndStructureLogin) {




                    return [
                        'find' => true,
                        'isEmail' => false,
                        'canRegister' => $canRegister
                    ];
                }





                return [
                    'find' => false,
                    'isEmail' =>  $this->isEmail($login ?? null),
                    'canRegister' => $canRegister,
                ];
            }
        ];
    }
}