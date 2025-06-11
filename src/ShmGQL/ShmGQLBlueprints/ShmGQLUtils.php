<?php

namespace Shm\ShmGQL\ShmGQLBlueprints;


class ShmGQLUtils
{


    public static function validError(string $msg)
    {
        throw new \GraphQL\Error\Error(extensions: [
            "type" => "VALID",
            "message" => $msg,
        ]);
    }
}