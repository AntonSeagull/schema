<?php

namespace Shm\ShmCmd;


use Error;
use Exception;
use InvalidArgumentException;
use Poliander\Cron\CronExpression;


class CmdSchedule
{
    private static array $scheduleTasks = [];


    /**
     * Добавляет задачу с произвольным cron-выражением.
     *
     * @param string $cronExpression
     * @param string|callable $command
     */
    public static function on(string $cronExpression, string|callable $command): void
    {
        self::addTask($cronExpression, $command);
    }



    public static function scheduleTask(string $cronExpression, string|callable $command): void
    {
        self::addTask($cronExpression, $command);
    }

    private static function addTask(string $cron, string|callable $command): void
    {
        $expression = new CronExpression($cron);
        if (!$expression->isValid()) {
            throw new Error("Invalid cron expression: $cron");
        }

        $task = ['cronExpression' => $cron];

        if (is_string($command)) {
            $task['command'] = $command;
        } elseif (is_callable($command)) {
            $task['function'] = $command;
        } else {
            throw new InvalidArgumentException("Argument must be a string or callable.");
        }

        self::$scheduleTasks[] = $task;
    }



    public static function everyMinute($command): void
    {
        self::addTask("* * * * *", $command);
    }

    public static function everyFiveMinute($command): void
    {
        self::addTask("*/5 * * * *", $command);
    }

    public static function everyTenMinutes($command): void
    {
        self::addTask("*/10 * * * *", $command);
    }

    public static function everyFifteenMinutes($command): void
    {
        self::addTask("*/15 * * * *", $command);
    }

    public static function everyThirtyMinutes($command): void
    {
        self::addTask("*/30 * * * *", $command);
    }

    public static function hourly($command): void
    {
        self::addTask("0 * * * *", $command);
    }

    public static function daily($command): void
    {
        self::addTask("0 0 * * *", $command);
    }

    public static function dailyNoon($command): void
    {
        self::addTask("0 12 * * *", $command);
    }

    public static function weekly($command): void
    {
        self::addTask("0 0 * * 0", $command);
    }

    public static function monthly($command): void
    {
        self::addTask("0 0 1 * *", $command);
    }



    public static function getTasksForNow(): array
    {
        $now = new \DateTime();
        $tasksForNow = [];

        foreach (self::$scheduleTasks as $val) {
            $expression = new CronExpression($val['cronExpression']);
            if ($expression->isMatching($now)) {
                $tasksForNow[] = $val;
            }
        }

        return $tasksForNow;
    }

    /**
     * Выполняет задачи, подходящие под текущее время.
     */
    public static function run(): void
    {


        //If is cli request do return
        if (php_sapi_name() === 'cli') {
            return;
        }



        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
        $requestUri = is_string($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';

        if ($requestMethod === 'GET' && $requestUri === '/cron/schedule/run') {


            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if (!preg_match('/bot|crawl|slurp|spider|curl|wget|python|java|fetch/i', $userAgent)) {
                http_response_code(403);
                exit("Forbidden");
            }

            $tasksForNow = self::getTasksForNow();

            foreach ($tasksForNow as $task) {
                if (isset($task['command'])) {

                    $command = "php ./index.php " . escapeshellarg($task['command']);

                    if (!function_exists('exec')) {
                        $error = new \RuntimeException("Function exec() is disabled");
                        \Sentry\captureException($error);
                        return;
                    }
                    exec($command, $output, $return_var);

                    if ($return_var !== 0) {
                        $message = "Command '{$task['command']}' failed with exit code {$return_var}";
                        $error = new \RuntimeException($message);
                        \Sentry\captureException($error);
                    }
                }

                if (isset($task['function'])) {
                    try {
                        $task['function']();
                    } catch (Exception $exception) {
                        \Sentry\captureException($exception);
                    }
                }
            }

            exit;
        }
    }
}
