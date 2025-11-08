<?php

namespace Shm\ShmAuth;


use Shm\ShmDB\mDB;
use Shm\ShmTypes\StructureType;

class AuthToken
{


    public StructureType $structure;
    public $userId;
    public $cancelKey;
    public array $payload = [];

    public function structure(StructureType $structure): self
    {
        $this->structure = $structure;
        return $this;
    }

    public function userId($userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function cancelKey($cancelKey): self
    {
        $this->cancelKey = $cancelKey;
        return $this;
    }

    public function payload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }


    public function generate(): string
    {
        $token =  hash("sha512", $this->userId . time() . bin2hex(openssl_random_pseudo_bytes(64)));




        mDB::collection(Auth::$token_collection)->insertOne([
            "token" => $token,
            'cancelKey' => $this->cancelKey,
            'collection' => $this->structure->collection,
            "agent" => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'last_used' => time(),
            "ip" => $_SERVER['REMOTE_ADDR'] ?? null,
            "owner" => mDB::id($this->userId),
            "payload" => $this->payload,
        ]);

        return $token;
    }
}
