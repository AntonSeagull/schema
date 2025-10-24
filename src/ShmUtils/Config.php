<?php

namespace Shm\ShmUtils;

/**
 * Configuration management class
 * 
 * This class provides methods for reading configuration values
 * with automatic caching. Configuration files should only be modified
 * by developers, not by the application during runtime.
 */
class Config
{
    /**
     * Cached configuration data
     * @var array|null
     */
    private static ?array $config = null;

    /**
     * Configuration file path
     * @var string|null
     */
    private static ?string $configFile = null;

    /**
     * Whether the configuration has been loaded
     * @var bool
     */
    private static bool $loaded = false;

    /**
     * Get configuration value by key
     * 
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value or default
     */
    public static function get(string $key, $default = null)
    {
        // Load configuration if not already loaded
        if (!self::$loaded) {
            self::loadConfig();
        }

        // Return null if configuration failed to load
        if (self::$config === null) {
            return $default;
        }

        // Return entire config if key is null
        if ($key === null) {
            return self::$config;
        }

        // Parse dot notation key
        $parts = explode('.', $key);
        $value = self::$config;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }

    /**
     * Set configuration value by key (in memory only)
     * 
     * Note: This method only modifies the configuration in memory.
     * Changes are not persisted to the configuration file.
     * 
     * @param string $key Configuration key (supports dot notation)
     * @param mixed $value Value to set
     * @return bool True if successful, false otherwise
     */
    public static function set(string $key, $value): bool
    {
        // Load configuration if not already loaded
        if (!self::$loaded) {
            self::loadConfig();
        }

        // Initialize config if it doesn't exist
        if (self::$config === null) {
            self::$config = [];
        }

        // Parse dot notation key and set value
        $parts = explode('.', $key);
        $config = &self::$config;

        foreach ($parts as $part) {
            if (!is_array($config)) {
                $config = [];
            }
            if (!array_key_exists($part, $config)) {
                $config[$part] = [];
            }
            $config = &$config[$part];
        }

        $config = $value;
        return true;
    }

    /**
     * Check if configuration key exists
     * 
     * @param string $key Configuration key (supports dot notation)
     * @return bool True if key exists, false otherwise
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Remove configuration key
     * 
     * @param string $key Configuration key (supports dot notation)
     * @return bool True if key was removed, false otherwise
     */
    public static function remove(string $key): bool
    {
        // Load configuration if not already loaded
        if (!self::$loaded) {
            self::loadConfig();
        }

        if (self::$config === null) {
            return false;
        }

        // Parse dot notation key
        $parts = explode('.', $key);
        $config = &self::$config;

        foreach ($parts as $part) {
            if (!is_array($config) || !array_key_exists($part, $config)) {
                return false;
            }
            $config = &$config[$part];
        }

        unset($config);
        return true;
    }

    /**
     * Get all configuration data
     * 
     * @return array|null All configuration data or null if not loaded
     */
    public static function all(): ?array
    {
        if (!self::$loaded) {
            self::loadConfig();
        }
        return self::$config;
    }

    /**
     * Clear cached configuration data
     * 
     * @return void
     */
    public static function clear(): void
    {
        self::$config = null;
        self::$loaded = false;
        self::$configFile = null;
    }

    /**
     * Reload configuration from file
     * 
     * @return bool True if successful, false otherwise
     */
    public static function reload(): bool
    {
        self::clear();
        return self::loadConfig();
    }


    /**
     * Load configuration from file
     * 
     * @return bool True if successful, false otherwise
     */
    private static function loadConfig(): bool
    {
        self::$configFile = realpath(ShmInit::$rootDir . '/config/config.php');

        if (!file_exists(self::$configFile)) {
            self::$config = null;
            self::$loaded = true;
            return false;
        }

        try {
            self::$config = require self::$configFile;
            self::$loaded = true;
            return true;
        } catch (\Throwable $e) {
            self::$config = null;
            self::$loaded = true;
            return false;
        }
    }
}
