<?php

namespace Shm\ShmTypes\CompositeTypes;

use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;

class RangeType extends StructureType
{
    public string $type = 'range';


    public function __construct(BaseType $type)
    {
        parent::__construct(
            [
                'from' => $type->setCol(12),
                'to' => $type->setCol(12),
            ]
        );
    }

    public function inAdmin(bool $isAdmin = true): static
    {
        return $this->fullInAdmin($isAdmin);
    }

    public function editable(bool $isEditable = true): static
    {
        return $this->fullEditable($isEditable);
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
