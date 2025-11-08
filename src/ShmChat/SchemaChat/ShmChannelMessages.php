<?php

namespace Shm\ShmChat\SchemaChat;

use Shm\Collection\Collection;
use Shm\Shm;
use Shm\ShmChat\ShmChat;
use Shm\ShmDB\mDB;
use Shm\ShmTypes\StructureType;

class ShmChannelMessages extends Collection
{




    public function schema(): ?StructureType
    {
        $schema = Shm::structure([

            "channel" => ShmChannels::ID(),
            "member" => ShmChannelMembers::ID(),
            "text" => Shm::string()->title("Текст сообщения"),
            "type" => Shm::enum([
                "regular" => "Обычное",
                "system" => "Системное",
            ])->title("Тип сообщения")->default("regular"),
            "attachments" => Shm::arrayOf(Shm::fileDocument())->title("Вложения"),
            "reply" => ShmChannelMessages::ID(),

            'voice' => Shm::fileAudio(),

            "actions" => Shm::arrayOf(
                Shm::structure([
                    "name" => Shm::string()->title("Имя действия"),
                    "type" => Shm::string()->title("Тип")->default("button"),
                    "value" => Shm::string()->title("Значение"),
                    "text" => Shm::string()->title("Текст кнопки"),
                    "style" => Shm::enum([
                        "default" => "По умолчанию",
                        "primary" => "Основная",
                        "danger" => "Опасная"
                    ])->title("Стиль")->default("default")
                ])
            )->title("Кнопки"),

            "reactions" => Shm::arrayOf(
                Shm::structure([
                    "user" => Shm::ID(),
                    "type" => Shm::enum([
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
                    ]),
                    "created_at" => Shm::timestamp()->title("Когда поставлена")
                ])
            )->title("Реакции"),

            'pinned' => Shm::structure([
                'enabled' => Shm::boolean()->title("Закреплено"),
                'user' => Shm::ID()->title("Кто закрепил"),
                'timestamp' => Shm::timestamp()->title("Когда закреплено"),
            ]),


            "payload" => ShmChat::$messagePayload ?? Shm::structure([
                '*' => Shm::mixed()
            ]),



        ]);



        return $schema;
    }
}