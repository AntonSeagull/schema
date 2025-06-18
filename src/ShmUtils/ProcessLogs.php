<?php

namespace Shm\ShmUtils;

class ProcessLogs
{

    public static $logs = [];


    public static function addLog(string $processId,  string $message): void
    {
        self::$logs[$processId][] = $message;
    }
}
