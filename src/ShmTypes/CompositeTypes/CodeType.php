<?php

namespace Shm\ShmTypes\CompositeTypes;

use Shm\ShmTypes\StringType;

class CodeType extends StringType
{
    public string $type = 'code';



    public string $codeLang = 'js';

    public function codeLanguage($language = 'js'): static
    {
        $this->codeLang = $language;
        return $this;
    }


    public function getSearchPaths(): array
    {
        return [];
    }
}
