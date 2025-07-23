<?php

namespace Lumus\Engine;

use Shm\ShmCmd\Cmd as ShmCmdCmd;

class Cmd extends ShmCmdCmd
{

    public static function createConsoleCommand(string $commandName, callable $resolve): void
    {
        self::command($commandName, $resolve);
    }
}
