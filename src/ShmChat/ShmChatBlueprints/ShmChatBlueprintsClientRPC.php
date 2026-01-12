<?php

namespace Shm\ShmChat\ShmChatBlueprints;

use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmChat\SchemaChat\ShmChannelMembers;
use Shm\ShmChat\SchemaChat\ShmChannelMessages;
use Shm\ShmChat\SchemaChat\ShmChannels;
use Shm\ShmChat\ShmChat;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;

class ShmChatBlueprintsClientRPC extends ShmChatBlueprintsBaseRPC
{


    public static function init(): array
    {

        return [
            'channelInfo' => [

                'type' => ShmChannels::structure(),

                'args' => Shm::structure([
                    'channel' => Shm::ID(),

                ]),
                'resolve' => function ($rootValue, array $args, $context) {


                    if (!isset($args['channel'])) {
                        Shm::error('Канал не найден');
                    }


                    self::accessValidator($args['channel']);


                    return ShmChannels::findOne([
                        '_id' => mDB::id($args['channel']),
                    ]);
                }
            ],
            'channelList' => [
                'type' => Shm::structure([
                    'data' => Shm::arrayOf(ShmChannels::structure()),
                    'hash' => Shm::string()
                ]),
                'args' => [
                    "limit" => Shm::int()->default(50)->title("Количество сообщений"),
                    "offset" => Shm::int()->default(0)->title("Смещение"),
                ],
                'resolve' => function ($rootValue, array $args) {
                    Auth::authenticateOrThrow();


                    $channels = ShmChannelMembers::structure()->distinct('channel', [
                        'member' => Auth::getAuthID(),
                        'memberCollection' => Auth::getAuthCollection(),
                    ]);


                    if (count($channels) === 0) {

                        return [
                            'data' => [],
                            'hash' => md5(''),
                        ];
                    }

                    $pipeline = [
                        [
                            '$match' => [
                                '_id' => ['$in' => $channels],
                            ]
                        ]
                    ];

                    if (isset($args['offset']) && $args['offset'] > 0) {
                        $pipeline[] = [
                            '$skip' => $args['offset']
                        ];
                    }

                    $pipeline[] = [
                        '$limit' => $args['limit'] ?? 50
                    ];


                    $result = ShmChannels::structure()->aggregate($pipeline)->toArray();



                    return [
                        'data' => $result,
                        'hash' => mDB::hashDocuments($result)
                    ];
                }
            ],
            'channelSendMessage' => [

                'type' => ShmChannelMessages::structure(),
                'args' => [
                    "channel" => Shm::ID(),
                    'reply' => Shm::ID(),
                    "text" => Shm::string()->title("Текст сообщения"),
                    "attachments" => Shm::arrayOf(Shm::fileDocument())->title("Вложения"),
                    'voice' => Shm::fileAudio(),
                    "payload" => Shm::structure([
                        '*' => Shm::mixed()
                    ]),
                ],
                'resolve' => function ($rootValue, array $args, $context) {

                    self::accessValidator($args['channel']);

                    $channel = ShmChannels::structure()->findOne([
                        '_id' => mDB::id($args['channel']),
                    ]);

                    if (!$channel) {
                        Shm::error('Канал не найден');
                    }


                    $insert =  ShmChannelMessages::structure()->insertOne([
                        "channel" => $channel->_id,
                        "member" => Auth::getAuthID(),
                        "text" => $args['text'] ?? "",
                        'reply' => $args['reply'] ?? null,
                        "attachments" => $args['attachments'] ?? [],
                        "voice" => $args['voice'] ?? null,
                        "type" => 'regular',
                        "custom" => $args['custom'] ?? null,
                    ]);

                    return ShmChannelMessages::structure()->findOne([
                        "_id" => $insert->getInsertedId(),

                    ]);
                },

            ],

            'channelMessages' => [
                'type' => Shm::structure([
                    'data' => Shm::arrayOf(ShmChannelMessages::structure()),
                    'hash' => Shm::string()
                ]),
                'args' => [
                    "channel" => Shm::string(),
                    "limit" => Shm::int()->default(50)->title("Количество сообщений"),
                    "offset" => Shm::int()->default(0)->title("Смещение"),
                ],
                'resolve' => function ($rootValue, array $args) {


                    if (!isset($args['channel'])) {
                        Shm::error('Канал не найден');
                    }






                    self::accessValidator($args['channel']);


                    ShmChannelMembers::updateLastRead(mDB::id($args['channel']), Auth::getAuthID());


                    $channel = ShmChannels::structure()->findOne([
                        '_id' => mDB::id($args['channel']),
                    ]);

                    if (!$channel) {
                        Shm::error('Канал не найден');
                    }

                    $member = ShmChannelMembers::structure()->findOne([
                        "user" => Auth::getAuthID(),
                        "channel" => $channel->_id,
                    ]);
                    if (!$member) {
                        Shm::error('Вы не являетесь участником канала');
                    }


                    $pipeline = [
                        [
                            '$match' => [
                                'channel' => $channel->_id,

                            ]
                        ],
                        [
                            '$sort' => ['_id' => -1]
                        ]
                    ];

                    if (isset($args['offset']) && $args['offset'] > 0) {
                        $pipeline[] = [
                            '$skip' => $args['offset']
                        ];
                    }

                    $pipeline[] = [
                        '$limit' => $args['limit'] ?? 50
                    ];


                    $result = ShmChannelMessages::structure()->aggregate($pipeline)->toArray();


                    return [
                        'data' => array_reverse($result),
                        'hash' => mDB::hashDocuments($result)
                    ];
                }
            ],

            'channelReaction' => [
                'type' => Shm::bool(),
                'args' => [
                    "message" => Shm::ID(),
                    "channel" => Shm::ID(),
                    "reaction" => Shm::enum([
                        "heart" => "Сердце",
                        "like" => "Нравится",
                        "laugh" => "Смех",
                        "wow" => "Удивление",
                        "sad" => "Грусть",
                        "angry" => "Злость",
                        "clap" => "Аплодисменты",
                        "party" => "Праздник",
                        "poop" => "Какахa",
                        "fire" => "Огонь"
                    ])
                ],
                'resolve' => function ($rootValue, array $args) {

                    if (!isset($args['channel']) || !isset($args['message']) || !isset($args['reaction'])) {
                        Shm::error('Канал, сообщение или реакция не найдены');
                    }

                    self::accessValidator($args['channel']);

                    $message = ShmChannelMessages::structure()->findOne([
                        "_id" => mDB::id($args['message']),
                        "channel" => mDB::id($args['channel']),

                    ]);

                    if (!$message) {
                        Shm::error('Сообщение не найдено');
                    }

                    $reactionType = $args['reaction'];
                    $userIdObj = mDB::id(Auth::getAuthID());
                    $messageIdObj = mDB::id($args['message']);

                    $collection = ShmChannelMessages::structure();

                    // Проверяем, есть ли уже такая реакция от пользователя
                    $existing = $collection->findOne([
                        '_id' => $messageIdObj,
                        'reactions' => [
                            '$elemMatch' => [
                                'user' => $userIdObj,
                                'type' => $reactionType,
                            ]
                        ]
                    ]);

                    if ($existing) {
                        // Если есть — убираем реакцию (отжимаем)
                        $collection->updateOne(
                            ['_id' => $messageIdObj],
                            [
                                '$pull' => [
                                    'reactions' => [
                                        'user' => $userIdObj,
                                        'type' => $reactionType,
                                    ]
                                ]
                            ]
                        );
                    } else {
                        // Удаляем любые другие реакции от этого пользователя
                        $collection->updateOne(
                            ['_id' => $messageIdObj],
                            [
                                '$pull' => [
                                    'reactions' => [
                                        'user' => $userIdObj
                                    ]
                                ]
                            ]
                        );

                        // Добавляем новую реакцию
                        $collection->updateOne(
                            ['_id' => $messageIdObj],
                            [
                                '$push' => [
                                    'reactions' => [
                                        'user' => $userIdObj,
                                        'type' => $reactionType,
                                        'created_at' => time()
                                    ]
                                ]
                            ]
                        );
                    }


                    return true;
                }
            ],

            'channelDeleteMessage' => [
                'type' => Shm::bool(),
                'args' => [
                    "channel" => Shm::ID(),
                    "message" => Shm::ID(),
                ],
                'resolve' => function ($rootValue, array $args) {
                    if (!isset($args['channel']) || !isset($args['message'])) {
                        Shm::error('Канал или сообщение не найдены');
                    }
                    self::accessValidator($args['channel']);

                    $channelId = mDB::id($args['channel']);
                    $messageId = mDB::id($args['message']);
                    $userId = mDB::id(Auth::getAuthID());

                    // Получаем сообщение
                    $message = ShmChannelMessages::structure()->findOne(['_id' => $messageId, 'channel' => $channelId, 'member' => $userId]);
                    if (!$message) return false;

                    ShmChannelMessages::structure()->deleteOne(['_id' =>  $message->_id]);

                    return true;
                }
            ],

            'channelDocumentUpload' => ShmRPC::fileUpload()->document()->make(),
            'channelVoiceUpload' => ShmRPC::fileUpload()->audio()->make(),
        ];
    }
}
