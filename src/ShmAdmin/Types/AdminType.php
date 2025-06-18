<?php


namespace Shm\ShmAdmin\Types;

use Shm\ShmTypes\StructureType;

class AdminType extends StructureType
{

    public string $type = 'admin';


    public function icon(string $icon): self
    {
        $this->assets([
            'icon' => $icon,
        ]);

        return $this;
    }

    public function cover(string $cover): self
    {
        $this->assets([
            'cover' => $cover,
        ]);

        return $this;
    }

    public function color(string $color): self
    {
        $this->assets([
            'color' => $color,
        ]);

        return $this;
    }

    public function subtitle(string $subtitle): self
    {
        $this->assets([
            'subtitle' => $subtitle,
        ]);

        return $this;
    }
}
