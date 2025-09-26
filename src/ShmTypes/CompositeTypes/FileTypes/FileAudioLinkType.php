<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes;

use Shm\ShmTypes\StringType;


class FileAudioLinkType extends StringType
{



    public string $type = 'fileAudioLink';


    public function getSearchPaths(): array
    {
        return [];
    }
}
