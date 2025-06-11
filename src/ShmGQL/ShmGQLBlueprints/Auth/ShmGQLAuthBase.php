<?php

namespace Shm\ShmGQL\ShmGQLBlueprints\Auth;

use Sentry\Util\Str;
use Shm\ShmAuth\Auth;
use Shm\ShmGQL\ShmGQLBlueprints\ShmGQLUtils;
use Shm\ShmUtils\Inflect;
use Shm\Types\StructureType;

class ShmGQLAuthBase
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

    public $onlyAuth = false;

    public function onlyAuth(bool $onlyAuth = true): self
    {
        $this->onlyAuth = $onlyAuth;
        return $this;
    }

    public  function hasValueValidator($keys, $params)
    {

        foreach ($keys as $key) {
            if (!isset($params[$key])) {


                ShmGQLUtils::validError("Заполните все необходимые поля");
            }
        }
    }

    public $errorAccountNotFound = "Ваша учетная запись не найдена.";
    public $initialValues = [];

    public function __construct(StructureType ...$authStructures)
    {


        $this->authStructures = $authStructures;

        if (Auth::getApiKey()) {

            $key = Inflect::singularize(Auth::getApiKeyStructure()->collection);
            $this->initialValues = [
                $key => Auth::getApiKey()->_id
            ];
        }

        return $this;
    }



    public function make(): ?array
    {
        return [];
    }
}