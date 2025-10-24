<?php

namespace Shm\ShmUtils;

use Shm\Shm;
use Shm\ShmDB\mDB;

/**
 * Response utility class for handling HTTP responses
 * 
 * This class provides methods for sending different types of HTTP responses
 * including HTML, JSON, and error responses.
 */
class Response
{
    /**
     * Send HTML response
     * 
     * @param string $html HTML content
     * @param int $status HTTP status code
     * @param array $headers Additional headers
     */
    public static function html(string $html, int $status = 200, array $headers = []): never
    {
        header("Content-Type: text/html;charset=utf-8");
        http_response_code($status);
        echo $html;
        exit;
    }

    /**
     * Send JSON response
     * 
     * @param mixed $data Data to encode as JSON
     * @param int $status HTTP status code
     * @param array $headers Additional headers
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): never
    {
        header("Content-Type: application/json;charset=utf-8");
        http_response_code($status);
        echo json_encode($data);
        exit;
    }




    private static float $startTime = 0.0;
    private static ?string $method = null;
    private static bool $cache = false;

    /**
     * Set cache flag
     * 
     * @param bool $cache Whether to enable caching
     */
    public static function cache(bool $cache = true): void
    {
        self::$cache = $cache;
    }

    /**
     * Start timing for performance measurement
     */
    public static function startTime(): void
    {
        self::$startTime = microtime(true);
    }


    public static function setMethod(?string $method): void
    {
        self::$method = $method;
    }

    private static $traceTimingsStart = [];
    private static $traceTimingsResult = [];


    public static function startTraceTiming(string $name): void
    {
        self::$traceTimingsStart[$name] = microtime(true);
    }

    public static function endTraceTiming(string $name): void
    {
        if (isset(self::$traceTimingsStart[$name])) {
            self::$traceTimingsResult[$name] = round((microtime(true) - self::$traceTimingsStart[$name]) * 1000);
            unset(self::$traceTimingsStart[$name]);
        } else {
            throw new \Exception("Trace timing '{$name}' was not started.");
        }
    }




    /**
     * Базовая структура ответа.
     *
     * @var array<string, mixed>
     */
    private static array $baseResponse = [
        'success' => false,
        'result' => null,
        'error' => null,
        'executionTime' => 0,
    ];

    /**
     * Возвращает успешный ответ.
     *
     * @param mixed $result Результат выполнения запроса
     * @return never
     */
    public static function success(mixed $result): never
    {

        header('Content-Type: application/json; charset=utf-8');


        $executionTime = self::$startTime ? round((microtime(true) - self::$startTime) * 1000) : null;

        $slowRequestTime = Config::get("slowRequestTime", null);


        if ($slowRequestTime) {
            if ($executionTime &&  $executionTime > $slowRequestTime) {

                mDB::collection("_rpc_slow_requests")->insertOne([
                    'url' => $_SERVER['REQUEST_URI'] ?? '',
                    'method' => self::$method,
                    'executionTime' => $executionTime,
                    'traceTimings' => self::$traceTimingsResult,
                    'memoryUsage' => [
                        'used' => memory_get_usage(),
                        'peak' => memory_get_peak_usage(),
                    ],
                ]);
            }
        }

        echo json_encode([
            ...self::$baseResponse,
            'success' => true,
            'result' => $result,
            'method' => self::$method,
            'executionTime' => $executionTime,
            'traceTimings' => self::$traceTimingsResult,
            'cache' => self::$cache,
            'memoryUsage' => [
                'used' => memory_get_usage(),
                'peak' => memory_get_peak_usage(),
            ],
        ]);
        exit(0);
    }

    /**
     * Ошибка валидации.
     *
     * @param string $message Сообщение об ошибке
     * @return never
     */
    public static function validation(string $message): never
    {


        self::error('VALIDATION_ERROR', $message, 400);
    }

    /**
     * Ошибка авторизации.
     *
     * @param string $message Сообщение об ошибке
     * @return never
     */
    public static function unauthorized(string $message = 'Unauthorized'): never
    {
        self::error('UNAUTHORIZED', $message, 401);
    }

    /**
     * Доступ запрещён.
     *
     * @param string $message Сообщение об ошибке
     * @return never
     */
    public static function forbidden(string $message = 'Forbidden'): never
    {
        self::error('FORBIDDEN', $message, 403);
    }

    /**
     * Ресурс не найден.
     *
     * @param string $message Сообщение об ошибке
     * @return never
     */
    public static function notFound(string $message = 'Not Found'): never
    {
        self::error('NOT_FOUND', $message, 404);
    }

    /**
     * Превышен лимит запросов.
     *
     * @param string $message Сообщение об ошибке
     * @return never
     */
    public static function rateLimited(string $message = 'Too Many Requests'): never
    {
        self::error('RATE_LIMITED', $message, 429);
    }

    /**
     * Внутренняя ошибка сервера.
     *
     * @param string $message Сообщение об ошибке
     * @return never
     */
    public static function internal(string $message = 'Internal Server Error'): never
    {
        self::error('INTERNAL_ERROR', $message, 500);
    }



    /**
     * Общий метод возврата ошибки.
     *
     * @param string $type Тип ошибки (например, UNAUTHORIZED, VALIDATION_ERROR)
     * @param string $message Сообщение об ошибке
     * @param int|null $code Числовой код ошибки (опционально)
     * @return never
     */
    public static function error(string $type, string $message, ?int $code = null): never
    {





        $error = [
            'type' => $type,
            'message' => $message,
        ];

        if ($code !== null) {
            $error['code'] = $code;
        }

        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            ...self::$baseResponse,
            'error' => $error,

            'executionTime' => self::$startTime ? round((microtime(true) - self::$startTime) * 1000) : null,
        ]);
        exit(0);
    }
}
