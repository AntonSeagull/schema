<?php

namespace Shm\ShmTypes\SupportTypes;

use Shm\ShmDB\mDB;
use Shm\ShmTypes\BaseType;

class StageType extends BaseType
{
    public string $type = 'stage';


    private $pipeline = [];


    public function pipeline(array $pipeline): self
    {

        if (count($pipeline) > 0) {
            mDB::validatePipeline($pipeline);
        }


        $this->pipeline = $pipeline;
        return $this;
    }

    public function getPipeline(): array
    {
        return $this->pipeline;
    }
}
