<?php

namespace Shm\ShmTypes\SupportTypes;

use Shm\ShmTypes\BaseType;

class StageType extends BaseType
{
    public string $type = 'stage';


    private $pipeline = [];


    public function pipeline(array $pipeline): self
    {
        $this->pipeline = $pipeline;
        return $this;
    }

    public function getPipeline(): array
    {
        return $this->pipeline;
    }
}
