<?php

namespace Shm\ShmRPC\ShmRPCCodeGen;


class TSType
{


    public static $tsTypes = [];

    private bool $isEnum = false;

    private string $tsTypeName;
    private string $tsTypeValue;

    public function __construct(string $tsTypeName = '', string $tsTypeValue = '', $isEnum = false)
    {


        $this->tsTypeName = $tsTypeName;
        $this->tsTypeValue = $tsTypeValue;

        $this->isEnum = $isEnum;

        self::$tsTypes[$this->tsTypeName] = $this->getType();
    }

    public function getTsTypeName(): string
    {
        return $this->tsTypeName;
    }


    public function getType(): string
    {

        if (!isset($this->tsTypeName) || empty($this->tsTypeName)) {
            return '';
            //   throw new \InvalidArgumentException("Type name is not set or empty.");
        }

        $result = "";
        if ($this->isEnum) {
            $result = 'export enum ' . $this->tsTypeName . ' ' . $this->tsTypeValue . ';';
        } else {
            $result  = 'export type ' . $this->tsTypeName . ' = ' . $this->tsTypeValue . ';';
        }



        return $result;
    }
}
