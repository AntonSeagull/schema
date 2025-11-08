<?php

namespace Shm\ShmChat;

use Shm\Shm;
use Shm\ShmChat\SchemaChat\ShmChannelMembers;
use Shm\ShmChat\SchemaChat\ShmChannels;
use Shm\ShmChat\ShmChatBlueprints\ShmChatBlueprintsClientRPC;
use Shm\ShmDB\mDB;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;

class ShmChat
{


    public static ?BaseType $channelPayload = null;
    public static ?BaseType $memberPayload = null;
    public static ?BaseType $messagePayload = null;


    public static function setChannelPayload(BaseType $payload)
    {
        self::$channelPayload = $payload;
    }

    public static function setMemberPayload(BaseType $payload)
    {
        self::$memberPayload = $payload;
    }

    public static function setMessagePayload(BaseType $payload)
    {
        self::$messagePayload = $payload;
    }


    public static function newDefaultChannel(string $name, array $payload = [])
    {

        $channel = ShmChannels::structure()->insertOne([
            'name' => $name,
            'payload' => $payload
        ]);

        return $channel->getInsertedId();
    }

    public static function addMemberToChannel($channelId, $memberId, string $memberCollection, string $name, $payload = [])
    {
        $channelId = mDB::id($channelId);
        $memberId = mDB::id($memberId);

        $member = ShmChannelMembers::structure()->updateOne([
            'channel' => $channelId,
            'member' => $memberId,
            'collection' => $memberCollection,
        ], [
            '$set' => [
                'name' => $name,
                'channel' => $channelId,
                'member' => $memberId,
                'role' => 'member',
                'collection' => $memberCollection,
                'payload' => $payload
            ]
        ], [
            'upsert' => true
        ]);

        return $member->getUpsertedId();
    }

    public static function clientRPC(): array
    {
        return ShmChatBlueprintsClientRPC::init();
    }
}