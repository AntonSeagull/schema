<?php

namespace Shm\ShmRPC\ShmRPCUtils;


class ShmRPCLazy
{
    protected $callback;


    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function make()
    {
        return call_user_func($this->callback);
    }
}
