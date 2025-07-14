<?php

namespace Shm\ShmCmd;


class Cmd
{

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

        if (php_sapi_name() === 'cli') {
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
                    fwrite(STDERR, $message . PHP_EOL);
                    // Отправляем исключение в Sentry
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
}
