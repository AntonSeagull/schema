<?php

namespace Shm\ShmRPC\ShmRPCCodeGen;

use Shm\ShmUtils\ShmUtils;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;

class ShmRPCRequestCode
{



    private BaseType $type;
    private BaseType | null $args;
    private string $key;

    private $formData = false;

    public function __construct(
        BaseType $type,
        BaseType | null $args,
        string $key,
        $formData = false
    ) {
        $this->type = $type;
        $this->args = $args;
        $this->key = $key;
        $this->formData = $formData;
    }


    private function  functionName(): string
    {
        return $this->key; // ShmUtils::onlyLetters($this->key);
    }

    private function paramsForFunction(): string
    {
        if (isset($this->args)) {

            return 'params: ' . $this->args->keyIfNot($this->key)->tsInputType()->getTsTypeName();
        }
        return "";
    }


    public function paramsType(): string
    {
        if (isset($this->args)) {

            return $this->args->keyIfNot($this->key)->tsInputType()->getTsTypeName();
        }
        return "{}";
    }


    private function paramsForRequest(): string
    {
        if (isset($this->args)) {

            return ', params';
        }
        return "";
    }




    public function initialize(): string
    {


        if ($this->formData) {

            return "{$this->functionName()}: (formData:FormData) => {
            return rpcClient.callFormData<{$this->type->tsType()->getTsTypeName()} | null>(
               '{$this->key}', formData
            );
             },";
        } else {

            return "{$this->functionName()}: ({$this->paramsForFunction()}) => {
            return rpcClient.call<{$this->paramsType()}, {$this->type->tsType()->getTsTypeName()} | null>(
               '{$this->key}'{$this->paramsForRequest()}
            );
             },";
        }
    }
}
