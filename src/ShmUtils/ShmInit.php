<?php

namespace Shm\ShmUtils;

use Shm\ShmCmd\Cmd;
use Shm\ShmCmd\CmdSchedule;

use Shm\ShmDB\mDB;
use Shm\ShmDB\mDBLite;
use Throwable;

class ShmInit
{
    private static $inited = false;


    public static $disableUpdateEvents = false;

    public static $rootDir = null;

    public static $shmDir = null;

    public static function init(string $bootstrapAppDir): void
    {





        if (php_sapi_name() !== 'cli') {

            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Methods: *");
            header("Content-Type: application/json;charset=utf-8");
            header("Access-Control-Allow-Headers: *");
        }




        self::$rootDir = realpath($bootstrapAppDir . '/../');

        self::$shmDir = realpath(dirname(__FILE__) . '/../');

        if (self::$inited) {
            return;
        }

        if (Config::get('sentry.dsn', '')) {
            \Sentry\init([
                'dsn' => Config::get('sentry.dsn', ''),
                'traces_sample_rate' => 1.0,
                'attach_stacktrace' => true,
                'environment' => Config::get('sentry.environment', 'production'),
            ]);
        }


        MaterialIcons::init();


        self::errorHandler();
        self::updateTimezone();


        Doctor::cmdInit();
        SearchStringUpdate::cmdInit();

        CmdSchedule::run();




        self::$inited = true;
    }


    private static function updateTimezone()
    {

        $keys = ['timezone', 'Timezone', 'TIMEZONE', 'TZ', 'tz', 'timeZone'];

        // Получаем заголовки
        $headers = function_exists('getallheaders') ? getallheaders() : [];

        $sources = [
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



    private static function errorHandler()
    {


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

        $whoops->register();
    }
}
