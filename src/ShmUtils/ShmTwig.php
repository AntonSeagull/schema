<?php

namespace Shm\ShmUtils;

use RuntimeException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class ShmTwig
{
    private static bool $init = false;
    private static string $viewPath = 'resources/views';
    private static string $cachePath = 'storage/cache/twig';

    private static array $namespaces = [];

    private static ?Environment $twig = null;

    public static function addNamespace(string $namespace, string $path): void
    {
        self::$namespaces[$namespace] = $path;

        if (self::$twig !== null) {
            self::registerNamespace($namespace, $path);
        }
    }

    private static function init(): void
    {
        if (self::$init) {
            return;
        }

        if (ShmInit::$rootDir === null) {
            throw new RuntimeException('ShmTwig requires ShmInit::$rootDir to be defined.');
        }

        $fullViewPath = self::resolvePath(self::$viewPath);
        if (!is_dir($fullViewPath)) {
            mkdir($fullViewPath, 0777, true);
        }

        $fullCachePath = self::resolvePath(self::$cachePath);
        if (!is_dir($fullCachePath)) {
            mkdir($fullCachePath, 0777, true);
        }

        $loader = new FilesystemLoader($fullViewPath);
        self::$twig = new Environment($loader, [
            'cache' => $fullCachePath,
            'debug' => false,
            'auto_reload' => true,
        ]);

        // Register library namespace 'shm' automatically
        if (ShmInit::$shmDir !== null && !isset(self::$namespaces['shm'])) {
            $shmViewsPath = rtrim(ShmInit::$shmDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'ShmUtils' . DIRECTORY_SEPARATOR . 'views';
            self::registerNamespace('shm', $shmViewsPath, true);
        }

        // Register user-defined namespaces
        foreach (self::$namespaces as $namespace => $path) {
            if ($namespace !== 'shm') {
                self::registerNamespace($namespace, $path);
            }
        }

        self::$init = true;
    }

    private static function getTwig(): Environment
    {
        self::init();

        if (self::$twig === null) {
            throw new RuntimeException('Failed to initialize the Twig environment.');
        }

        return self::$twig;
    }

    private static function registerNamespace(string $namespace, string $path, bool $absolutePath = false): void
    {
        $resolvedPath = $absolutePath ? $path : self::resolvePath($path);

        if (!is_dir($resolvedPath)) {
            mkdir($resolvedPath, 0777, true);
        }

        $loader = self::$twig?->getLoader();
        if ($loader instanceof FilesystemLoader) {
            $loader->addPath($resolvedPath, $namespace);
        }
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

    private static function normalizeTemplate(string $tpl): string
    {
        // Convert Plates syntax (namespace::template) to Twig syntax (@namespace/template.twig)
        if (strpos($tpl, '::') !== false) {
            [$namespace, $template] = explode('::', $tpl, 2);
            $template = str_replace('.php', '', $template); // Remove .php extension if present
            if (!str_ends_with($template, '.twig')) {
                $template .= '.twig';
            }
            return '@' . $namespace . '/' . $template;
        }

        // If no namespace, ensure .twig extension
        if (!str_ends_with($tpl, '.twig')) {
            $tpl .= '.twig';
        }

        return $tpl;
    }

    public static function render(string $tpl, array $data = [], array $mergeData = []): string
    {
        $twig = self::getTwig();

        $context = array_merge($mergeData, $data);
        $normalizedTpl = self::normalizeTemplate($tpl);

        return $twig->render($normalizedTpl, $context);
    }

    public static function html(string $view, array $data = [], array $mergeData = []): void
    {
        $html = self::render($view, $data, $mergeData);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}
