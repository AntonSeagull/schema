<?php

namespace Shm\ShmBlueprints\Auth;

use Sentry\Util\Str;
use Shm\ShmAuth\Auth;

use Shm\ShmUtils\Inflect;
use Shm\ShmUtils\Response;
use Shm\ShmTypes\StructureType;

class ShmAuthBase
{

    public $title;
    public $key;

    /**
     * @var StructureType[]
     * Список структур, которые поддерживают авторизацию
     */
    public $authStructures = null;
    public $description;
    public $pipeline;




    public  function hasValueValidator($keys, $params)
    {

        foreach ($keys as $key) {
            if (!isset($params[$key])) {


                Response::validation("Заполните все необходимые поля");
            }
        }
    }

    public $errorAccountNotFound = "Ваша учетная запись не найдена.";


    public function __construct(StructureType ...$authStructures)
    {


        $this->authStructures = $authStructures;



        return $this;
    }



    public function make(): ?array
    {
        return [];
    }
}
