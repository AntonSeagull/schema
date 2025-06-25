<?php

namespace Shm\ShmRPC\ShmRPCCodeGen;


class TSType
{


    public static $tsTypes = [];
    private static $tsTypesHashData = [];

    private bool $isEnum = false;

    private string $tsTypeName;
    private string $tsTypeValue;

    public function __construct(string $tsTypeName = '', string $tsTypeValue = '', $isEnum = false)
    {





        $tsTypeHash = md5($tsTypeValue . ($isEnum ? 'enum' : 'type'));



        if ($tsTypeValue && isset(self::$tsTypesHashData[$tsTypeHash]) && self::$tsTypesHashData[$tsTypeHash]['tsTypeName'] != $tsTypeName) {


            $this->tsTypeName = $tsTypeName;

            $cachedData = self::$tsTypesHashData[$tsTypeHash];
            //   $this->tsTypeName = $cachedData['tsTypeName'];

            $this->tsTypeValue = $cachedData['tsTypeName'];
        } else {

            $this->tsTypeName = $tsTypeName;


            self::$tsTypesHashData[$tsTypeHash] = [
                'tsTypeName' => $tsTypeName,
                'tsTypeValue' => $tsTypeValue,
            ];


            $this->tsTypeValue = $tsTypeValue;
        }

        $this->isEnum = $isEnum;

        if ($tsTypeValue)
            self::$tsTypes[$this->tsTypeName] = $this->getType();
    }

    public function getTsTypeName(): string
    {
        return $this->tsTypeName;
    }


    public function getType(): string
    {


        if (!isset($this->tsTypeValue) || empty($this->tsTypeValue)) {
            return '';
            //   throw new \InvalidArgumentException("Type name is not set or empty.");
        }


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
