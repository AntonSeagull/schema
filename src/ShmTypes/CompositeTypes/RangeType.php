<?php

namespace Shm\ShmTypes\CompositeTypes;

use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;

class RangeType extends StructureType
{
    public string $type = 'range';

    public bool $compositeType = true;



    public function __construct(BaseType $type)
    {
        parent::__construct(
            [
                'from' => (clone $type)->setCol(12)->title("От"),
                'to' => (clone $type)->setCol(12)->title("До"),
            ]
        );
    }





    public function required(bool $isRequired = true): static
    {
        return $this->fullRequired($isRequired);
    }


    public function getSearchPaths(): array
    {
        return [];
    }
}
