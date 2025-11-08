<?php

namespace Shm\ShmAuth;


class AuthPassword
{


    public static function passwordHash($password)
    {
        $hash = hash("sha512", $password);

        return $hash;
    }

    public static function isPasswordHash($password): bool
    {

        return strlen($password) === 128 && ctype_xdigit($password);
    }
}