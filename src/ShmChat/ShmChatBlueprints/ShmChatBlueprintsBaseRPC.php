<?php

namespace Shm\ShmChat\ShmChatBlueprints;

use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmChat\SchemaChat\ShmChannelMembers;
use Shm\ShmChat\SchemaChat\ShmChannels;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;

class ShmChatBlueprintsBaseRPC
{



    public static function accessValidator($channelId)
    {

        Auth::authenticateOrThrow();

        $member =  ShmChannelMembers::find([
            'member' => Auth::getAuthID(),
            'memberCollection' => Auth::getAuthCollection(),
            'channel' => mDB::id($channelId),
        ]);

        if (!$member) {
            Shm::error('Вы не являетесь участником канала');
        }
    }
}
