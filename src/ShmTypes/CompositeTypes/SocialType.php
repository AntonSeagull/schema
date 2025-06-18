<?php

namespace Shm\ShmTypes\CompositeTypes;

use Shm\Shm;
use Shm\ShmTypes\ArrayOfType;

class SocialType extends ArrayOfType
{
    public string $type = 'social';


    public function __construct()
    {
        parent::__construct(
            Shm::structure(
                [
                    "id" => Shm::string(),
                    "type" => Shm::string(),
                    "soc" => Shm::string(),
                    "mail" => Shm::string(),
                    "name" => Shm::string(),
                    "surname" => Shm::string(),
                    "photo" => Shm::string(),
                ]
            )
        );
    }

    public function getSearchPaths(): array
    {
        return [];
    }
}
