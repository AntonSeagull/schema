<?php

namespace Lumus;

use Exception;

use Lumus\Engine\Core;
use Lumus\Engine\Response;
use Shm\ShmAuth\Auth as ShmAuthAuth;

class Auth
{


    public static function init() {}


    public static function isAuthenticated($key)
    {
        return self::isUserModel($key);
    }




    public static function noAuth()
    {

        return !ShmAuthAuth::isAuthenticated();
    }

    public static function isUserModel(string $model): bool
    {

        return ShmAuthAuth::getAuthCollection() === $model;
    }

    public static function getAuthenticatedUser()
    {

        return ShmAuthAuth::getAuthIDAllField();
    }
}
