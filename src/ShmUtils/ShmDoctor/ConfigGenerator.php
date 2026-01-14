<?php

namespace Shm\ShmUtils\ShmDoctor;

use Shm\ShmUtils\ShmInit;
use Shm\ShmUtils\ShmTwig;

class ConfigGenerator
{
    /**
     * Generate config.php file from Twig template
     */
    public static function generate(): void
    {
        $dir = ShmInit::$rootDir . '/config';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file = $dir . '/config.php';
        if (file_exists($file)) {
            return;
        }

        $content = ShmTwig::render('@shm/config.php');
        file_put_contents($file, $content);
        echo "Config file created: $file\n";
        echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
    }
}
