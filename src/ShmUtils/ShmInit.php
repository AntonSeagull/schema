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

        MaterialIcons::init();


        self::errorHandler();
        self::updateTimezone();
        self::makeConfigFile();

        Doctor::cmdInit();
        SearchStringUpdate::cmdInit();

        CmdSchedule::run();

        FileUploader::init();


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


    private static function makeConfigFile()
    {

        Cmd::command("make-config", function () {

            $dir =  self::$rootDir . '/config';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $file = $dir . '/config.php';
            if (file_exists($file)) {
                //   echo "Config file already exists: $file\n";
                //    return;
            }
            $content = "<?php
            return [
                'mongodb' => [
                  'host' => 'localhost',
                    'port' => 27017,
                    'username' => '',
                    'password' => '',
                    'database' => '',
                    'authSource' => 'admin',
                    'poolSize' => 1000,
                    'ssl' => false,
                    'connectTimeoutMS' => 360000,
                    'socketTimeoutMS' => 360000,
                ],
                
                'redis' => [
                    'host' => 'localhost',
                    'port' => 6379,
                    'password' => null,
                ],
                'sentry' => [
                    'dsn' => '',
                    'environment' => 'production',
                ],
                'socket' => [
                    'domain' => '',
                    'prefix' => 'test'
                ],
                's3' => [
                    'bucket' => '',
                    'version' => 'latest',
                    'region' =>  '',
                    'endpoint' => '',
                    'credentials' => [
                        'key' => '',
                        'secret' => '',
                    ],
                 ]
                
            ];
            ";
            file_put_contents($file, $content);
            echo "Config file created: $file\n";
            echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
        });
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
