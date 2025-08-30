<?php

namespace Shm\ShmTypes\CompositeTypes;

use Shm\Shm;
use Shm\ShmTypes\StructureType;

class GradientType extends StructureType
{
    public string $type = 'gradient';




    public function __construct()
    {



        parent::__construct([

            "palette" => Shm::arrayOf(Shm::structure([
                "color" => Shm::string(),
                "opacity" => Shm::float(),
                "offset" => Shm::string()
            ])),
            "angle" => Shm::float()

        ]);
    }

    public function getSearchPaths(): array
    {
        return [];
    }
}
