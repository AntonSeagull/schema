<?php

namespace Shm\ShmUtils;

use Shm\ShmCmd\Cmd;

class RedirectRoute
{
    public static function init(): void
    {

        if (Cmd::cli()) {
            return;
        }

        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $requestUri = parse_url($requestUri, PHP_URL_PATH);


        if ($requestMethod === 'GET' && $requestUri === '/redirect') {
            $url = $_GET['to'] ?? '';
            if ($url) {
                header("Location: $url");
                exit;
            }
        }
    }
}