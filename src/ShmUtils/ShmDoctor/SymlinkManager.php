<?php

namespace Shm\ShmUtils\ShmDoctor;

use Shm\ShmCmd\Cmd;
use Shm\ShmUtils\ShmInit;

class SymlinkManager
{
    /**
     * Create symlinks for admin static files
     */
    public static function createSymlinks(): void
    {
        $links = [
            ShmInit::$rootDir . '/public/static/main.js' => ShmInit::$shmDir . '/../assets/admin/static/main.js',
            ShmInit::$rootDir . '/public/static/main.css' => ShmInit::$shmDir . '/../assets/admin/static/main.css',
        ];

        $isCli = Cmd::cli();

        foreach ($links as $target => $source) {
            $targetDir = dirname($target);

            // Проверяем, существует ли исходный файл
            if (!file_exists($source)) {
                if (is_link($target) || file_exists($target)) {
                    unlink($target);
                    if ($isCli) {
                        echo "Удалён старый файл/ссылка, исходный файл отсутствует: $target\n";
                    }
                }
                if ($isCli) {
                    echo "❌ Исходный файл не найден: $source\n";
                }
                continue;
            }

            // Создаём директорию, если её нет
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0777, true);
                if ($isCli) {
                    echo "Создана директория: $targetDir\n";
                }
            }

            // Удаляем старый файл/ссылку, если есть
            if (is_link($target) || file_exists($target)) {
                unlink($target);
                if ($isCli) {
                    echo "Удалён старый файл/ссылка: $target\n";
                }
            }

            // Создаём новую символическую ссылку
            if (!symlink($source, $target)) {
                if ($isCli) {
                    echo "❌ Не удалось создать ссылку: $target -> $source\n";
                }
            } else {
                if ($isCli) {
                    echo "✅ Создана ссылка: $target -> $source\n";
                }
            }
        }
    }
}
