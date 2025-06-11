<?php

namespace Shm\ShmGQL\ShmGQLBlueprints\Auth;

use Shm\Shm;
use Shm\Types\StructureType;

class ShmGQLAuth
{


    public $authCollections = null;

    public function __construct(StructureType ...$authCollections)
    {



        $this->authCollections = $authCollections;


        return $this;
    }




    public function sms(): ShmGQLSmsAuth
    {
        return new ShmGQLSmsAuth(...$this->authCollections);
    }
}