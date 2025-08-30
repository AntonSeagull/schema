<?php

namespace Shm\ShmAdmin\SchemaCollections;

use Shm\Collection\Collection;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmTypes\StructureType;

class ManualTags extends Collection
{
    public $collection = "_manualTags";
    public function schema(): StructureType
    {
        $schema = Shm::structure([
            "_id" => Shm::ID(),
            'collection' => Shm::string(),
            "title" => Shm::string()->inAdmin()->editable()->title("Название тега")->required()->default("Новый тег"),
            "color" => Shm::color()->inAdmin()->editable()->title("Цвет тега")->required()->default("#701e1e")
        ]);


        $schema->pipeline([
            [
                '$match' => [
                    "ownerID" => Auth::getAuthOwner(),
                    "ownerCollection" => Auth::getAuthCollection(),
                    "subAccountID" => Auth::getSubAccountID()
                ]
            ]
        ]);

        $schema->insertValues([
            "ownerID" => Auth::getAuthOwner(),
            "ownerCollection" => Auth::getAuthCollection()
        ]);



        $schema->canCreate();
        $schema->canUpdate();
        $schema->canDelete();

        return $schema;
    }
}
