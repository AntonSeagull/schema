<?php

namespace Lumus;

use Exception;

use Lumus\Engine\Core;
use Lumus\Engine\Response;
use Shm\ShmAuth\Auth as ShmAuthAuth;

class Auth
{


    public static function init() {}

    public static function getAuthenticatedUser()
    {

        return ShmAuthAuth::getAuthOwnerAllField();
    }
}
