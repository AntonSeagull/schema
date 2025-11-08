<?php

namespace Shm\ShmUtils;

use League\Plates\Engine;
use RuntimeException;

class ShmTpl
{
    private static bool $init = false;
    private static string $viewPath = 'resources/tpl';

    private static array $namespaces = [];

    private static ?Engine $engine = null;

    public static function addNamespace(string $namespace, string $path): void
    {
        self::$namespaces[$namespace] = $path;

        if (self::$engine !== null) {
            self::registerNamespace($namespace, $path);
        }
    }

    private static function init(): void
    {
        if (self::$init) {
            return;
        }

        if (ShmInit::$rootDir === null) {
            throw new RuntimeException('ShmTpl requires ShmInit::$rootDir to be defined.');
        }

        $fullViewPath = self::resolvePath(self::$viewPath);
        if (!is_dir($fullViewPath)) {
            mkdir($fullViewPath, 0777, true);
        }

        self::$engine = new Engine($fullViewPath);

        foreach (self::$namespaces as $namespace => $path) {
            self::registerNamespace($namespace, $path);
        }

        self::$init = true;
    }

    private static function getEngine(): Engine
    {
        self::init();

        if (self::$engine === null) {
            throw new RuntimeException('Failed to initialize the Plates engine.');
        }

        return self::$engine;
    }

    private static function registerNamespace(string $namespace, string $path): void
    {
        $resolvedPath = self::resolvePath($path);

        if (!is_dir($resolvedPath)) {
            mkdir($resolvedPath, 0777, true);
        }

        self::$engine?->addFolder($namespace, $resolvedPath);
    }

    private static function resolvePath(string $path): string
    {
        $trimmedPath = trim($path);

        if ($trimmedPath === '') {
            throw new RuntimeException('View path cannot be empty.');
        }

        $normalizedPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $trimmedPath);



        return rtrim(ShmInit::$rootDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($normalizedPath, DIRECTORY_SEPARATOR);
    }



    public static function render(string $tpl, array $data = [], array $mergeData = []): string
    {
        $engine = self::getEngine();

        $context = array_merge($mergeData, $data);

        return $engine->render($tpl, $context);
    }

    public static function html(string $view, array $data = [], array $mergeData = []): void
    {
        $html = self::render($view, $data, $mergeData);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}
