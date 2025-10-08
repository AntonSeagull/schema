<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes;

use Shm\ShmTypes\StringType;


class FileImageLinkType extends StringType
{



    public string $type = 'fileImageLink';

    public $width = 300;
    public $height = 300;


    public function setResize(int $w = 300, int $h = 300): static
    {

        return $this->resize($w, $h);
    }


    public function resize(int $w = 300, int $h = 300): static
    {
        $this->width = $w;
        $this->height = $h;
        return $this;
    }

    public function getSearchPaths(): array
    {
        return [];
    }


    public function exportRow(mixed $value): string | array | null
    {
        if ($value) {
            return (string)$value;
        } else {
            return null;
        }
    }
}
