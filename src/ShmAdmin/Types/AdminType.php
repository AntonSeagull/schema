<?php


namespace Shm\ShmAdmin\Types;

use Shm\ShmTypes\StructureType;

class AdminType extends StructureType
{

    public string $type = 'admin';




    public function terms(string $terms): static
    {
        $this->assets([
            'terms' => $terms,
        ]);

        return $this;
    }
    public function privacy(string $privacy): static
    {
        $this->assets([
            'privacy' => $privacy,
        ]);

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->assets([
            'icon' => $icon,
        ]);

        return $this;
    }

    public function cover(string $cover): static
    {
        $this->assets([
            'cover' => $cover,
        ]);

        return $this;
    }

    public function color(string $color): static
    {
        $this->assets([
            'color' => $color,
        ]);

        return $this;
    }

    public function subtitle(string $subtitle): static
    {
        $this->assets([
            'subtitle' => $subtitle,
        ]);

        return $this;
    }
}
