<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes;

use Shm\ShmTypes\StringType;


class FileImageLinkType extends StringType
{
    public string $type = 'imagelink';

    public function getSearchPaths(): array
    {
        return [];
    }
}
