<?php

namespace Shm\ShmAuth;


use Shm\ShmDB\mDB;
use Shm\ShmTypes\StructureType;

class AuthApiKey
{

    public StructureType $structure;
    public $userId;

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

    public function payload(array $payload): self
    {
        $this->payload = $payload;
        return $this;
    }



    public function generate(): string
    {
        $apikey =  hash("sha512", $this->userId . time() . bin2hex(openssl_random_pseudo_bytes(64)));

        mDB::collection(Auth::$apikey_collection)->insertOne([
            "apikey" => $apikey,
            'title' => $this->structure->title,
            'collection' => $this->structure->collection,
            "agent" => $_SERVER['HTTP_USER_AGENT'] ?? null,
            "ip" => $_SERVER['REMOTE_ADDR'] ?? null,
            "owner" => mDB::id($this->userId),
            "payload" => $this->payload,
        ]);

        return $apikey;
    }
}