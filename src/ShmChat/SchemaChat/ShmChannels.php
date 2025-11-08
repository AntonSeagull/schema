<?php

namespace Shm\ShmChat\SchemaChat;

use Shm\Collection\Collection;
use Shm\Shm;
use Shm\ShmChat\ShmChat;
use Shm\ShmTypes\StructureType;

class ShmChannels extends Collection
{

    public function schema(): ?StructureType
    {
        $schema = Shm::structure([
            "name" => Shm::string(),
            'last_message' => ShmChannelMessages::ID(),
            "payload" => ShmChat::$channelPayload ?? Shm::structure([
                "*" => Shm::mixed()
            ]),
            'type' => Shm::enum([
                'default' => 'Обычный чат',
                'support' => 'Поддержка',
                'supportSystem' => 'Поддержка системы',
            ])->default('default'),
        ]);



        return $schema;
    }

    public static function updateUnreadCount($channel)
    {


        $members = ShmChannelMembers::structure()->find(['channel' => $channel->_id, 'removed' => false, 'banned' => false, 'muted' => false]);




        foreach ($members as $member) {
            ShmChannelMembers::updateUnreadCount($member);
        }
    }
}