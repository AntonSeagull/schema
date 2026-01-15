<?php

namespace Shm\ShmTypes\CompositeTypes;

use Shm\Shm;
use Shm\ShmTypes\StructureType;

class TimeType extends StructureType
{
    public string $type = 'time';


    public bool $compositeType = true;



    public function __construct()
    {

        $this->items = [
            'h' => Shm::int()->min(0)->max(23)->default(0),
            'm' => Shm::int()->min(0)->max(59)->default(0),
        ];
    }

    public function getSearchPaths(): array
    {
        return [];
    }
}
