<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmTypes\StructureType;

class ShmAuth
{


    public $authStructures = null;

    public function __construct(StructureType ...$authStructures)
    {




        $this->authStructures = $authStructures;


        return $this;
    }



    public function sms(): ShmSmsAuth
    {
        return new ShmSmsAuth(...$this->authStructures);
    }


    public function msg(): ShmMsgAuth
    {
        return new ShmMsgAuth(...$this->authStructures);
    }

    public function email(): ShmEmailAuth
    {
        return new ShmEmailAuth(...$this->authStructures);
    }

    public function login(): ShmLoginAuth
    {
        return new ShmLoginAuth(...$this->authStructures);
    }

    public function soc(): ShmSocAuth
    {
        return new ShmSocAuth(...$this->authStructures);
    }


    public function apple(): ShmAppleAuth
    {
        return new ShmAppleAuth(...$this->authStructures);
    }



    public function passport(): ShmPassportAuth
    {
        return new ShmPassportAuth(...$this->authStructures);
    }

    public function call(): ShmCallAuth
    {
        return new ShmCallAuth(...$this->authStructures);
    }
}
