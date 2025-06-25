<?php

namespace Shm\ShmTypes;


use Shm\ShmRPC\ShmRPCCodeGen\TSType;

class UUIDType extends BaseType

{
    public string $type = 'uuid';


    private function generateUuidV4(): string
    {
        $data = random_bytes(16);

        // Устанавливаем версию 4 и вариант
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // версия 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // вариант RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function __construct() {}

    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {



        if (!$value) {
            return  $this->generateUuidV4();
        }
        return (string) $value;
    }




    public function tsType(): TSType
    {
        $TSType = new TSType("string");


        return $TSType;
    }
}
