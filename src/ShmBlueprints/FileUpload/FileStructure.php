<?php

namespace Shm\ShmBlueprints\FileUpload;

use Shm\Shm;
use Shm\ShmTypes\StructureType;

class FileStructure
{


    public static function document(): StructureType
    {
        return Shm::structure([
            "fileType" =>  Shm::string(),
            'name' =>  Shm::string(),
            'url' => Shm::string(),
            "type" => Shm::string(),
            'created_at' => Shm::string(),
            "_id" => Shm::ID()
        ])->staticBaseTypeName("DocumentFile");
    }


    public static function audio(): StructureType
    {
        return Shm::structure([
            "fileType" =>  Shm::string(),
            'name' =>  Shm::string(),
            'url' => Shm::string(),
            'duration' => Shm::float(),
            "type" => Shm::string(),
            'created_at' => Shm::string(),
            "_id" => Shm::ID()
        ])->staticBaseTypeName("AudioFile");
    }

    public static function video(): StructureType
    {
        return Shm::structure([
            "fileType" => Shm::string(),
            'name' => Shm::string(),
            'url' => Shm::string(),
            'cover' => Shm::string(),
            'duration' => Shm::number(),
            'width' => Shm::number(),
            'height' =>  Shm::number(),
            "type" => Shm::string(),
            'created_at' => Shm::number(),
            "_id" => Shm::ID(),
        ])->staticBaseTypeName("VideoFile");
    }

    public static function image(): StructureType
    {
        return Shm::structure([
            "fileType" => Shm::string(),
            "name" => Shm::string(),
            "url" => Shm::string(),
            "url_medium" => Shm::string(),
            "url_small" => Shm::string(),

            "blurhash" => Shm::string(),
            "width" => Shm::float(),
            "height" => Shm::float(),
            "type" => Shm::string(),
            "created_at" => Shm::number(),
            "_id" => Shm::ID()
        ])->staticBaseTypeName("ImageFile");
    }
}
