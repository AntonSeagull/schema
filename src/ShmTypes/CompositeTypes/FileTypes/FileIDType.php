<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes;

use Shm\Shm;
use Shm\ShmDB\mDB;
use Shm\ShmDB\mDBRedis;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmTypes\IDType;
use Shm\ShmUtils\ShmUtils;

class FileIDType extends IDType
{

    public bool $compositeType = true;

    public function __construct(string $type)
    {

        $structure = null;


        if ($type == 'image') {

            $this->type = 'fileImageID';

            $structure = Shm::structure(FileImageType::items())->collection('_files');

            $structure->staticBaseTypeName('fileImageFile');
        }

        if ($type == 'video') {

            $this->type = 'fileVideoID';

            $structure = Shm::structure(FileVideoType::items())->collection('_files');
            $structure->staticBaseTypeName('fileVideoFile');
        }
        if ($type == 'audio') {

            $this->type = 'fileAudioID';

            $structure = Shm::structure(FileAudioType::items())->collection('_files');
            $structure->staticBaseTypeName('fileAudioFile');
        }
        if ($type == 'document') {

            $this->type = 'fileDocumentID';

            $structure = Shm::structure(FileDocumentType::items())->collection('_files');
            $structure->staticBaseTypeName('fileDocumentFile');
        }

        if (!$structure) {
            throw new \Exception("Unknown FileIDType file type: $type");
        }





        parent::__construct($structure);
    }





    public function exportRow(mixed $value): string | array | null
    {

        if ($value) {

            $val =  mDBRedis::get("_files", $value);

            if ($val) {
                if ($val && isset($val['url'])) {
                    return (string)$val['url'];
                } else {
                    return '';
                }
            }

            $val = mDB::collection('_files')->findOne(['_id' => mDB::id($value)]);
            if ($val && isset($val['url'])) {

                mDBRedis::save("_files", $value, $val);

                return (string)$val['url'];
            } else {
                return '';
            }
        } else {
            return '';
        }
    }


    public function tsType(): TSType
    {
        return $this->getDocument()->tsType();
    }
}
