<?php

namespace Shm\ShmTypes\CompositeTypes;

use Shm\Shm;
use Shm\ShmTypes\StructureType;

class RangeUnixDateType extends StructureType
{
    public string $type = 'rangeunixdate';


    public function __construct()
    {
        parent::__construct(
            [
                'from' => Shm::unixdate(),
                'to' => Shm::unixdate(),
            ]
        );
    }

    public function getSearchPaths(): array
    {
        return [];
    }
}
