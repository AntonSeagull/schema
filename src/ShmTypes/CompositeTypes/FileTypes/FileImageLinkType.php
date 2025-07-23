<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes;

use Shm\ShmTypes\StringType;


class FileImageLinkType extends StringType
{
    public string $type = 'imagelink';

    public $width = 300;
    public $height = 300;


    public function setResize(int $w = 300, int $h = 300): self
    {

        return $this->resize($w, $h);
    }

    public function resize(int $w = 300, int $h = 300): self
    {
        $this->width = $w;
        $this->height = $h;
        return $this;
    }

    public function getSearchPaths(): array
    {
        return [];
    }
}
