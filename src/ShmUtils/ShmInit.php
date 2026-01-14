<?php

namespace Shm\ShmUtils;

use Shm\ShmAdmin\SchemaCollections\ShmExportCollection;
use Shm\ShmAuth\AuthSessionRevoke;
use Shm\ShmCmd\Cmd;
use Shm\ShmUtils\ShmDoctor\Doctor;
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

    private static function renderDos404(): string
    {
        $messages = [
            [
                'message' => 'The requested resource could not be located.',
                'hint' => 'Have you tried turning it off and on again?'
            ],
            [
                'message' => 'This page is null. Literally.',
                'hint' => 'Stack Overflow suggests you check your spelling.'
            ],
            [
                'message' => 'The page you\'re looking for is in another castle.',
                'hint' => 'Did you try sudo?'
            ],
            [
                'message' => 'It\'s not a bug, it\'s a feature.',
                'hint' => 'The page has been moved to /dev/null'
            ],
            [
                'message' => 'This page is undefined. Just like your variable.',
                'hint' => 'rm -rf / won\'t help here.'
            ],
            [
                'message' => 'The page has left the building. Elvis has nothing to do with it.',
                'hint' => 'Have you tried Ctrl+Alt+Delete?'
            ],
            [
                'message' => 'The page you\'re looking for is in another dimension.',
                'hint' => 'This page has been deprecated. Like IE6.'
            ],
            [
                'message' => 'Resource not found. Neither does your social life.',
                'hint' => 'It\'s not a bug, it\'s a feature request.'
            ],
            [
                'message' => '404: Page not found. Stack overflow suggests you check your spelling.',
                'hint' => 'Have you tried grep -r "page" /dev/null?'
            ],
            [
                'message' => 'This endpoint returns 404. It\'s working as intended.',
                'hint' => 'Maybe try checking the network tab?'
            ],
            [
                'message' => 'The page you\'re looking for doesn\'t exist. Neither does your social life.',
                'hint' => 'Did you try npm install?'
            ],
            [
                'message' => '404: Resource not found. Have you tried sudo?',
                'hint' => 'The page has been moved to /dev/null'
            ],
            [
                'message' => '404: Resource not found. DNS is having an existential crisis.',
                'hint' => 'Try waiting 5 minutes. Or 5 hours.'
            ],
            [
                'message' => '404: Resource not found. These are not the pages you are looking for.',
                'hint' => 'Move along.'
            ],
            [
                'message' => '404: Resource not found. This is fine.',
                'hint' => 'Everything is under control.'
            ]
        ];

        $msg = $messages[array_rand($messages)];

        return ShmTwig::render('@shm/404', [
            'message' => $msg['message'],
            'hint' => $msg['hint']
        ]);
    }

    private static function fatFree404()
    {

        if (class_exists('\\Base')) {
            $f3 = \Base::instance();

            $f3->set('ONERROR', function ($f3) {
                $error = $f3->get('ERROR');
                $code  = $error['code'] ?? 500;

                if ($code === 404) {
                    header('Content-Type: text/html; charset=utf-8', true, 404);
                    echo self::renderDos404(); // функция ниже
                    return;
                }

                // Для прочих ошибок можно вывести нейтрально:
                header('Content-Type: text/plain; charset=utf-8', true, $code);
                echo "Error {$code}";
            });
        }
    }

    public static function init(string $bootstrapAppDir): void
    {
        self::$shmVersionHash =   \Composer\InstalledVersions::getReference("shm/schema") ?? "none";

        header_remove("X-Powered-By");
        ini_set('expose_php', 'off');

        self::fatFree404();

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

        Cmd::command("exportStep", function () {

            ShmExportCollection::exportStep();
        })->everyMinute();


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
            self::$lang = 'ru';
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
                    $value = strtolower(trim($source[$key]));
                    // Проверяем вхождение языка в строке (например, "RU_ru", "En_en", "russian", "english")
                    foreach ($allowedLangs as $allowedLang) {
                        if (strpos($value, $allowedLang) !== false) {
                            self::$lang = $allowedLang;
                            return;
                        }
                    }
                }
            }
        }

        // Попробовать из заголовка Accept-Language
        if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($langs as $langRaw) {
                $value = strtolower(trim($langRaw));
                // Проверяем вхождение языка в строке
                foreach ($allowedLangs as $allowedLang) {
                    if (strpos($value, $allowedLang) !== false) {
                        self::$lang = $allowedLang;
                        return;
                    }
                }
            }
        }

        // Значение по умолчанию
        self::$lang = 'ru';
    }



    public static function sendOnError(Throwable $exception): void
    {

        try {
            \Sentry\captureException($exception);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
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
