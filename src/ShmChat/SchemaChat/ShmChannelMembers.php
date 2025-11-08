<?php

namespace Shm\ShmChat\SchemaChat;

use Shm\Collection\Collection;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmChat\ShmChat;
use Shm\ShmTypes\StructureType;

class ShmChannelMembers extends Collection
{



    public function schema(): ?StructureType
    {
        $schema = Shm::structure([

            "member" => Shm::ID(),
            "memberCollection" => Shm::string(),
            "name" => Shm::string(),
            'channel' => ShmChannels::ID(),
            "role" => Shm::enum([
                "member" => "Участник",
                "owner" => "Владелец",
                "admin" => "Администратор",
                "support" => "Поддержка",
            ])->default("member"),

            "payload" => ShmChat::$memberPayload ?? Shm::structure([
                "*" => Shm::mixed()
            ]),

            "last_read_at" => Shm::timestamp()->title("Когда последний раз читал"),
            "unread_count" => Shm::int()->title("Непрочитанных")->default(0),


        ]);


        return $schema;
    }

    public static function updateUnreadCount($memberId)
    {

        $member = ShmChannelMembers::structure()->findOne(['_id' => $memberId]);

        $last_readAt = $member->last_read_at ?? 0;
        $unreadCount = ShmChannelMessages::structure()->count([
            'channel' => $member->channel,
            'created_at' => ['$gt' => $last_readAt]
        ]);

        ShmChannelMembers::structure()->update(
            ['_id' => $memberId],
            ['$set' => ['unread_count' => $unreadCount]]
        );
    }

    public static function updateLastRead($channelId, $userId)
    {
        $collection = ShmChannelMembers::structure();
        $collection->update(
            [
                'channel' => $channelId,
                'user' => $userId
            ],
            [
                '$set' => [
                    'last_read_at' => time(),
                    'unread_count' => 0
                ]
            ]
        );
    }
}