<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes;

use Shm\Shm;
use Shm\ShmDB\mDB;
use Shm\ShmTypes\IDType;


class FileIDType extends IDType
{


    public function __construct(string $type)
    {

        $structure = null;


        if ($type == 'image') {

            $this->type = 'fileImageID';

            $structure = Shm::structure(FileImageType::items())->collection('_files');
        }

        if ($type == 'video') {

            $this->type = 'fileVideoID';

            $structure = Shm::structure(FileVideoType::items())->collection('_files');
        }
        if ($type == 'audio') {

            $this->type = 'fileAudioID';

            $structure = Shm::structure(FileAudioType::items())->collection('_files');
        }
        if ($type == 'document') {

            $this->type = 'fileDocumentID';

            $structure = Shm::structure(FileDocumentType::items())->collection('_files');
        }

        if (!$structure) {
            throw new \Exception("Unknown FileIDType file type: $type");
        }



        parent::__construct($structure);
    }


    public function exportRow(mixed $value): string | array | null
    {

        if ($value) {

            $val = mDB::collection('_files')->findOne(['_id' => mDB::id($value)]);
            if ($val && isset($val['url'])) {
                return (string)$val['url'];
            } else {
                return '';
            }
        } else {
            return '';
        }
    }





    public function getSearchPaths(): array
    {
        return [];
    }
}
