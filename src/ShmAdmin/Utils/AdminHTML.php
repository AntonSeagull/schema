<?php

namespace Shm\ShmAdmin\Utils;

use Shm\ShmAdmin\AdminPanel;
use Shm\ShmUtils\ShmInit;
use Shm\ShmUtils\ShmTwig;

class AdminHTML
{


    private static function url($path = "")
    {


        $scheme =  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 'https' : 'http';

        $host = $_SERVER['HTTP_HOST'] ?? "";

        $currentURL = $scheme . '://' . $host;

        return $currentURL . $path;
    }

    public static function html()
    {


        $title = AdminPanel::$schema->title ?? "Admin";

        $icon = AdminPanel::$schema->assets['icon'] ?? null;
        $color = AdminPanel::$schema->assets['color'] ?? "#000000";
        $apiUrl =  self::url() . $_SERVER['REQUEST_URI'];


        $url = self::url();



        $js =  $url . "/static/main.js?shm=" . ShmInit::$shmVersionHash;
        $css = $url . "/static/main.css?shm=" . ShmInit::$shmVersionHash;

        return ShmTwig::render('@shm/admin', [
            'title' => $title,
            'icon' => $icon,
            'color' => $color,
            'apiUrl' => $apiUrl,
            'js' => $js,
            'css' => $css,
        ]);
    }
}
