<?php

namespace Shm\ShmUtils\ShmDoctor\Utils;

use Shm\ShmUtils\ShmInit;

class StructureHelper
{
    /**
     * Get all structure classes from app/Collections directory
     * 
     * @return array Array of structure class instances
     */
    public static function getStructures(): array
    {
        $classes = [];

        if (is_dir(ShmInit::$rootDir . '/app/Collections')) {
            $files = scandir(ShmInit::$rootDir . '/app/Collections');

            foreach ($files as $file) {
                if (!in_array($file, ['.', '..']) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $className = str_replace('.php', '', $file);
                    $fullClassName = 'App\\Collections\\' . $className;

                    $class = new $fullClassName();
                    $classes[] = $class;
                }
            }

            return $classes;
        }

        return [];
    }
}
