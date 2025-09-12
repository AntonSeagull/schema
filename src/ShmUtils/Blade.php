<?php

namespace Shm\ShmUtils;


class Blade
{
    private static ?Blade $blade = null;
    private static string $viewPath = '/resources/views';
    private static string $cachePath = '/resources/cache';
    private static array $namespaces = [];

    public static function addNamespace(string $namespace, string $path): void
    {
        self::$namespaces[$namespace] = $path;
    }

    private static function getBlade(): Blade
    {
        if (!self::$blade) {


            $fullViewPath = ShmInit::$rootDir . self::$viewPath;
            if (!is_dir($fullViewPath)) {
                mkdir($fullViewPath, 0777, true);
            }
            $fullCachePath = ShmInit::$rootDir . self::$cachePath;
            if (!is_dir($fullCachePath)) {
                mkdir($fullCachePath, 0777, true);
            }

            self::$blade = new Blade($fullViewPath, $fullCachePath);

            foreach (self::$namespaces as $ns => $path) {
                self::$blade->addNamespace($ns, $path);
            }
        }

        return self::$blade;
    }

    public static function render(string $view, array $data = [], array $mergeData = []): string
    {
        return self::getBlade()->render($view, $data, $mergeData);
    }

    public static function html(string $view, array $data = [], array $mergeData = [])
    {
        $html =  self::getBlade()->render($view, $data, $mergeData);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}
