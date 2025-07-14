<?php

namespace Shm\ShmTypes;


class StaticType extends BaseType
{
    public string $type = 'static';


    private $staticValue;

    public function __construct(mixed $staticValue)
    {

        if (!$staticValue) {
            throw new \InvalidArgumentException('Static value cannot be null or empty');
        }

        $this->staticValue = $staticValue;
    }

    public function getStaticValueTS(): mixed
    {

        if (is_string($this->staticValue)) {

            return json_encode($this->staticValue);
        }

        return $this->staticValue;
    }

    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        return $this->staticValue;
    }
}
