<?php

namespace Shm\ShmUtils;


class Config
{

    public static function get(string $key, $default = null)
    {
        static $config;

        if ($config === null) {
            $file = realpath(ShmInit::$rootDir . '/config/config.php');
            if (!file_exists($file)) {
                throw new \RuntimeException("Config file not found: $file");
            }
            $config = require $file;
        }

        if ($key === null) {
            return $config;
        }

        $parts = explode('.', $key);
        $value = $config;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }
}
