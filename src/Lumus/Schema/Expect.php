<?php


namespace Lumus\Schema;

use Lumus\Engine\Collection\Collection;
use Lumus\Schema\Elements\Structure;
use Shm\Shm;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\IDsType;
use Shm\ShmTypes\IDType;
use Shm\ShmTypes\StructureType;

enum FileType
{
    case Image;
    case Video;
    case Audio;
    case ImageLink;
    case File;

    case FileLink;
}


class Expect extends Shm
{

    public static function anyFile()
    {

        return Shm::fileIDDocument();
    }


    public static function imageFile()
    {

        return Shm::fileIDImage();
    }

    public static function rangeunixdate()
    {

        return Shm::range(Shm::unixdate());
    }

    public static function rangetime()
    {

        return Shm::range(Shm::time());
    }

    public static function geoJSON()
    {
        return Shm::mixed();
    }

    public static function ID(callable  | StructureType | Collection $documentResolver = null): IDType
    {

        if ($documentResolver instanceof Collection) {
            $documentResolver = $documentResolver::structure();
        }


        return (new IDType($documentResolver));
    }

    public static function IDs(callable | StructureType | Collection $documentResolver = null): IDsType
    {

        if ($documentResolver instanceof Collection) {
            $documentResolver = $documentResolver::structure();
        }

        return (new IDsType($documentResolver));
    }

    public static function file(FileType $fileType): BaseType
    {

        switch ($fileType) {

            case FileType::Image:

                return Shm::fileImage();

            case FileType::ImageLink:

                return Shm::fileImageLink();

            case FileType::Video:

                return Shm::fileVideo();

            case FileType::Audio:

                return Shm::fileAudio();

            case FileType::File:

                return Shm::fileDocument();

            default:

                return Shm::fileDocument();
        }
    }

    public static function structure(array $fields): StructureType
    {
        return new StructureType($fields);
    }
}
