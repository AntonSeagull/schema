<?php

namespace Lumus;


use Lumus\Engine\Core;
use Lumus\Engine\Response;
use GuzzleHttp\Client;
use Aws\S3\S3Client;

class Utils
{

    public static function getIpPosition(): array | bool
    {

        return false;
    }

    public static   function num2str($n, $textForms)
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return $textForms[2];
        }
        if ($n1 > 1 && $n1 < 5) {
            return $textForms[1];
        }
        if ($n1 === 1) {
            return $textForms[0];
        }
        return $textForms[2];
    }
}
