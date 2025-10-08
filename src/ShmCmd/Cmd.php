<?php

namespace Shm\ShmCmd;

use Shm\Shm;
use Shm\ShmUtils\ShmInit;

class Cmd
{


    // Проверяет, запущена ли команда из командной строки
    public static function cli(): bool
    {
        return php_sapi_name() === 'cli';
    }


    public static function command(string $cmd, callable $handler): self
    {
        return new self($cmd, $handler);
    }

    private function parseNamedArgs(array $argv): array
    {
        $result = [];

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--') && str_contains($arg, '=')) {
                [$key, $value] = explode('=', substr($arg, 2), 2);
                if ($key !== '' && $value !== '') {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }


    private $cmd;

    private $handler;

    public function __construct(string $cmd, callable $handler)
    {
        $this->cmd = $cmd;
        $this->handler = $handler;

        if (Cmd::cli()) {
            $argv = $_SERVER['argv'];
            $command = $argv[1] ?? null;

            if ($command === $this->cmd) {




                ini_set('log_errors', 1);

                // Настройка глобального обработчика ошибок
                set_error_handler(function ($errno, $errstr, $errfile, $errline): void {
                    $message = "Error [$errno]: $errstr in $errfile on line $errline";

                    fwrite(STDERR, $message . PHP_EOL);
                    // Отправляем ошибку как исключение в Sentry
                    $exception = new \ErrorException($errstr, 0, $errno, $errfile, $errline);
                    \Sentry\captureException($exception);
                });

                set_exception_handler(function ($exception): void {
                    $message = "Uncaught exception: " . $exception->getMessage();
                    $location = " in " . $exception->getFile() . " on line " . $exception->getLine();

                    fwrite(STDERR, $message . $location . PHP_EOL);

                    $trace = $exception->getTrace();

                    fwrite(STDERR, "Stack trace:" . PHP_EOL);

                    foreach ($trace as $index => $frame) {
                        $file = $frame['file'] ?? '[internal function]';
                        $line = $frame['line'] ?? '';
                        $function = $frame['function'] ?? '';
                        $class = $frame['class'] ?? '';
                        $type = $frame['type'] ?? '';

                        fwrite(STDERR, sprintf(
                            "#%d %s(%s): %s%s%s()\n",
                            $index,
                            $file,
                            $line,
                            $class,
                            $type,
                            $function
                        ));
                    }

                    // Отправляем в Sentry
                    \Sentry\captureException($exception);
                });

                register_shutdown_function(function (): void {
                    $error = error_get_last();
                    if ($error !== NULL) {
                        $message = "[SHUTDOWN] file: {$error['file']} | ln: {$error['line']} | msg: {$error['message']}";
                        fwrite(STDERR, $message . PHP_EOL);
                        // Отправляем в Sentry
                        \Sentry\captureMessage($message, \Sentry\Severity::fatal());
                    }
                });

                $params = $this->parseNamedArgs($argv);


                $callback = $this->handler;

                $reflection = is_array($callback)
                    ? new \ReflectionMethod($callback[0], $callback[1])
                    : new \ReflectionFunction($callback);

                if ($reflection->getNumberOfParameters() > 0) {
                    call_user_func($callback, $params);
                } else {
                    call_user_func($callback);
                }



                exit;
            }
        }

        return $this;
    }

    public function monthly()
    {

        CmdSchedule::monthly($this->cmd);
        return $this;
    }

    public function weekly()
    {

        CmdSchedule::weekly($this->cmd);
        return $this;
    }
    public function daily()
    {

        CmdSchedule::daily($this->cmd);
        return $this;
    }
    public function hourly()
    {

        CmdSchedule::hourly($this->cmd);
        return $this;
    }
    public function everyMinute()
    {

        CmdSchedule::everyMinute($this->cmd);
        return $this;
    }
    public function everyFiveMinute()
    {

        CmdSchedule::everyFiveMinute($this->cmd);
        return $this;
    }
    public function everyTenMinutes()
    {

        CmdSchedule::everyTenMinutes($this->cmd);
        return $this;
    }
    public function everyFifteenMinutes()
    {

        CmdSchedule::everyFifteenMinutes($this->cmd);
        return $this;
    }
    public function everyThirtyMinutes()
    {

        CmdSchedule::everyThirtyMinutes($this->cmd);
        return $this;
    }

    public function dailyNoon()
    {

        CmdSchedule::dailyNoon($this->cmd);
        return $this;
    }



    public static function asyncRunBase64Data(string $commandName, array $data, int $delay = 0, int $priority = 1, string $queue = '', bool $runAsync = false)
    {


        if (count($data) == 0) {
            return;
        }


        $base64 = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));

        $payload = json_encode([
            "command" =>  ShmInit::$rootDir . '/public/index.php ' . $commandName . ' --base64=' . $base64,
            "delay" => $delay,
            "priority" => $priority,
            "queue"     =>  $queue,
            "run_async" => $runAsync,   // запускается в фоне без ожидания
        ]);



        $ch = curl_init('http://localhost:8434/enqueue');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($ch);


        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $curlError = curl_error($ch);
        curl_close($ch);
    }


    public static function asyncRun(string $commandName, int $delay = 0, int $priority = 1, string $queue = '', bool $runAsync = false)
    {

        $payload = json_encode([
            "command" => ShmInit::$rootDir . '/public/index.php ' . $commandName,
            "delay" => $delay,
            "priority" => $priority,
            "queue"     => $queue,      // передаём имя очереди (если пусто — будет default)
            "run_async" => $runAsync,   // запускается в фоне без ожидания
        ]);


        $ch = curl_init('http://localhost:8434/enqueue');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Проверка ошибки
        if ($response === false || $httpCode >= 400) {
            $message = "Failed to enqueue command '$commandName'. HTTP code: $httpCode. Curl error: $curlError";
            error_log($message);


            \Sentry\captureMessage($message, \Sentry\Severity::error());
        }
    }
}
