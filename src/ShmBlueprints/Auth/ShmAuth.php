<?php

namespace Shm\ShmBlueprints\Auth;

use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmTypes\StructureType;

class ShmAuth
{



    public function __construct()
    {




        return $this;
    }



    public function sms(): ShmSmsAuth
    {




        return (new ShmSmsAuth());
    }


    public function msg(): ShmMsgAuth
    {



        return new ShmMsgAuth();
    }

    public function email(): ShmEmailAuth
    {




        return new ShmEmailAuth();
    }




    public function login(): ShmLoginAuth
    {




        return new ShmLoginAuth();
    }

    public function soc(): ShmSocAuth
    {




        return new ShmSocAuth();
    }


    public function apple(): ShmAppleAuth
    {



        return new ShmAppleAuth();
    }



    public function passport(): ShmPassportAuth
    {


        return new ShmPassportAuth();
    }

    public function call(): ShmCallAuth
    {



        return new ShmCallAuth();
    }
}
