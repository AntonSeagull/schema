<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes;

use Shm\ShmTypes\StringType;


class FileDocumentLinkType extends StringType
{
    public string $type = 'fileDocumentLink';

    public $accept = '*';

    public function setAccept(string $accept): self
    {
        $this->accept = $accept;
        return $this;
    }


    public function getSearchPaths(): array
    {
        return [];
    }
}
