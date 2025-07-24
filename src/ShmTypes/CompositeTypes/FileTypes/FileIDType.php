<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes;

use Shm\Shm;
use Shm\ShmTypes\IDType;


class FileIDType extends IDType
{


    public function __construct(string $type)
    {

        $structure = null;


        if ($type == 'image') {

            $structure = Shm::structure(FileImageType::items())->collection('_files');
        }

        if ($type == 'video') {

            $structure = Shm::structure(FileVideoType::items())->collection('_files');
        }
        if ($type == 'audio') {

            $structure = Shm::structure(FileAudioType::items())->collection('_files');
        }
        if ($type == 'document') {

            $structure = Shm::structure(FileDocumentType::items())->collection('_files');
        }

        if (!$structure) {
            throw new \Exception("Unknown FileIDType file type: $type");
        }



        parent::__construct($structure);
    }




    public function getSearchPaths(): array
    {
        return [];
    }
}
