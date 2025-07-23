<?php

namespace Lumus\Engine;


use Shm\ShmUtils\ShmInit;

class Core extends ShmInit
{

    //Theme
    public static $visualMode = 2;
    public static $title = "Без названия";
    public static $subtitle = "";
    public static $logo = "https://random.imagecdn.app/300/300";
    public static $cover = "https://random.imagecdn.app/1024/1024";
    //PROJECT_IDENTIFIER
    public static $indentifier = null;
    public static $coverDepthMap = "";

    public static $color = "#000";
}
