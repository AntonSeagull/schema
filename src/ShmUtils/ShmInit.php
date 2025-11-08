<?php

namespace Shm\ShmUtils;

use Shm\ShmAuth\Auth;
use Shm\ShmAuth\AuthSessionRevoke;
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


    private static $_errorHandler = null;

    /**
     * Устанавливает обработчик ошибок.
     * @param callable $errorHandler Функция обработчика ошибок, которая принимает исключение.
     * @example
     * ```php
     * ShmInit::onError(function (Throwable $exception) {
     *     // Обработка исключения
     * });
     * ```
     * @return void
     */
    public static function onError(callable $errorHandler): void
    {
        self::$_errorHandler = $errorHandler;
    }


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

        AuthSessionRevoke::init();

        Doctor::cmdInit();
        SearchStringUpdate::cmdInit();


        ShmMetrics::init();





        self::$inited = true;
    }


    private static function updateTimezone()
    {

        //Если запрос пришел из CLI, то не меняем таймзону
        if (Cmd::cli()) {
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
        if (Cmd::cli()) {
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



    public static function sendOnError(Throwable $exception): void
    {

        \Sentry\captureException($exception);

        if (self::$_errorHandler) {
            call_user_func(self::$_errorHandler, $exception);
        }
    }


    private static function errorHandler()
    {

        $whoops = new \Whoops\Run;

        $isDebug = isset($_GET['debug']) || ($_SERVER['SERVER_NAME'] ?? '') === 'localhost';

        if ($isDebug) {
            $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        } else {
            $whoops->pushHandler(function (Throwable $exception, $inspector, $run) {


                self::sendOnError($exception);



                return \Whoops\Handler\Handler::DONE;
            });
        }

        $whoops->register();
    }
}