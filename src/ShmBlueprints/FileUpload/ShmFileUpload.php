<?php

namespace Shm\ShmBlueprints\FileUpload;


use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmTypes\StructureType;

class ShmFileUpload
{




    public function audio(): ShmAudioUpload
    {
        return new ShmAudioUpload();
    }

    public function video(): ShmVideoUpload
    {
        return new ShmVideoUpload();
    }
    public function image(): ShmImageUpload
    {
        return new ShmImageUpload();
    }
    public function document(): ShmDocumentUpload
    {
        return new ShmDocumentUpload();
    }
}
