<?php

namespace Shm\ShmUtils;

use Shm\ShmCmd\Cmd;
use Shm\ShmCmd\CmdSchedule;

use Shm\ShmDB\mDB;
use Shm\ShmDB\mDBLite;
use Shm\ShmSupport\ShmSupport;
use Throwable;

class ShmInit
{
    private static $inited = false;


    public static $disableUpdateEvents = false;
    public static $disableInsertEvents = false;

    public static $rootDir = null;


    public static $isAdmin = false;

    public static $shmVersionHash = 'none';

    public static $shmDir = null;

    public static $lang = 'en';

    public static function init(string $bootstrapAppDir): void
    {



        self::$shmVersionHash =   \Composer\InstalledVersions::getReference("shm/schema") ?? "none";


        if (php_sapi_name() !== 'cli') {

            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Methods: *");
            header("Content-Type: application/json;charset=utf-8");
            header("Access-Control-Allow-Headers: *");


            // Обработка preflight (OPTIONS) запроса
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(200);
                exit;
            }
        }





        self::$rootDir = realpath($bootstrapAppDir . '/../');

        self::$shmDir = realpath(dirname(__FILE__) . '/../');

        if (self::$inited) {
            return;
        }

        if (Config::get('sentry.dsn', null)) {
            \Sentry\init([
                'dsn' => Config::get('sentry.dsn', ''),
                'traces_sample_rate' => 1.0,
                'attach_stacktrace' => true,
                'environment' => Config::get('sentry.environment', 'production'),
            ]);
        }

        self::errorHandler();


        MaterialIcons::init();



        self::updateTimezone();
        self::updateLang();


        Doctor::cmdInit();
        SearchStringUpdate::cmdInit();

        CmdSchedule::run();





        self::$inited = true;
    }


    private static function updateTimezone()
    {

        //Если запрос пришел из CLI, то не меняем таймзону
        if (php_sapi_name() === 'cli') {
            return;
        }

        $keys = ['timezone', 'Timezone', 'TIMEZONE', 'TZ', 'tz', 'timeZone'];

        // Получаем заголовки
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        $body = file_get_contents('php://input');
        $bodyRequest = [];
        if ($body) {
            $bodyData = json_decode($body, true);
            if (is_array($bodyData)) {
                $bodyRequest = $bodyData;
            }
        }


        $sources = [
            "BODY" => $bodyRequest,
            'HEADERS' => $headers,
            'GET' => $_GET,
            'POST' => $_POST,
            'COOKIE' => $_COOKIE,
            'SESSION' => $_SESSION ?? [],
        ];

        foreach ($keys as $key) {
            foreach ($sources as $source) {
                if (isset($source[$key]) && in_array($source[$key], timezone_identifiers_list(), true)) {
                    date_default_timezone_set($source[$key]);
                    return;
                }
            }
        }
    }



    private static function updateLang()
    {
        if (php_sapi_name() === 'cli') {
            self::$lang = 'en';
            return;
        }

        static $cachedBody = null;
        $body = $cachedBody ?? $cachedBody = file_get_contents('php://input');
        $bodyRequest = json_decode($body, true) ?? [];

        $headers = function_exists('getallheaders') ? getallheaders() : [];

        $sources = [
            'SESSION' => $_SESSION ?? [],
            'COOKIE' => $_COOKIE,
            'GET' => $_GET,
            'POST' => $_POST,
            'BODY' => $bodyRequest,
            'HEADERS' => $headers,
        ];

        $keys = ['lang', 'language', 'LANG', 'Language'];

        $allowedLangs = ['en', 'ru'];

        foreach ($sources as $sourceName => $source) {
            foreach ($keys as $key) {
                if (!empty($source[$key])) {
                    $lang = strtolower(substr(trim($source[$key]), 0, 2));
                    if (in_array($lang, $allowedLangs, true)) {
                        self::$lang = $lang;
                        return;
                    }
                }
            }
        }

        // Попробовать из заголовка Accept-Language
        if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($langs as $langRaw) {
                $lang = strtolower(substr($langRaw, 0, 2));
                if (in_array($lang, $allowedLangs, true)) {
                    self::$lang = $lang;
                    return;
                }
            }
        }

        // Значение по умолчанию
        self::$lang = 'en';
    }



    private static function errorHandler()
    {

        $whoops = new \Whoops\Run;

        $isDebug = isset($_GET['debug']) || ($_SERVER['SERVER_NAME'] ?? '') === 'localhost';

        if ($isDebug) {
            $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        } else {
            $whoops->pushHandler(function (Throwable $exception, $inspector, $run) {
                \Sentry\captureException($exception);
                return \Whoops\Handler\Handler::DONE;
            });
        }

        $whoops->register();


        /*

        $whoops = new \Whoops\Run;
        if (!isset($_GET['debug']) && ($_SERVER['SERVER_NAME'] ?? null) !== "localhost") {
            $whoops->pushHandler(function (Throwable $exception, $inspector, $run) {

                \Sentry\captureException($exception);


                return \Whoops\Handler\Handler::DONE;
            });
        } else {
            $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
            $whoops->register();
        }

        $whoops->register();*/
    }
}
