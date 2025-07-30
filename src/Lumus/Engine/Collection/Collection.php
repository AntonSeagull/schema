<?php

namespace Lumus\Engine\Collection;

use Shm\ShmTypes\StructureType;
use Shm\Collection\Collection as CollectionCollection;
use Shm\Shm;


class Collection extends CollectionCollection
{

    use CollectionPipeline;


    private $key = null;


    public function expect(): StructureType | null
    {
        return null;
    }

    public $single = false;


    public function title()
    {
        return $this->collection;
    }


    public $icon = '';


    public function access()
    {
        return [
            "update" => false,
            "delete" => false,
            "add" => false,
        ];
    }


    public function initialValues(): array
    {
        return [];
    }


    public function basePipeline(): array
    {

        return [];
    }





    public function tabs()
    {

        return [];
    }


    public function expectGroups()
    {
        return [];
    }



    public function schema(): ?StructureType
    {
        $schema  =  $this->expect();


        $expectGroups = $this->expectGroups();
        if (count($expectGroups) > 0) {

            foreach ($expectGroups as $group) {


                foreach ($group['keys'] as $key) {

                    $field = $schema->findItemByKey($key);

                    if (!$field) {
                        continue;
                    }
                    $field->group($group['title'], null);
                }
            }
        }

        $schema->title($this->title());

        $schema->icon($this->icon);

        $access = $this->access();

        $update = $access['update'] ?? false;
        $delete = $access['delete'] ?? false;
        $add = $access['add'] ?? false;

        if ($update) {
            $schema->canUpdate();
        }

        if ($delete) {
            $schema->canDelete();
        }

        if ($add) {
            $schema->canCreate();
        }


        $tabs = $this->tabs();

        if ($tabs && count($tabs) > 0) {

            $stages = [];
            foreach ($tabs as $tab) {

                $stages[md5($tab['title'])] = Shm::stage()->pipeline($tab['filter'])->title($tab['title'] ?? '');
            }
            if (count($stages) > 0) {
                $schema->stages(Shm::structure($stages));
            }
        }


        $initialValues = $this->initialValues();

        if ($initialValues && count($initialValues) > 0) {
            $schema->insertValues($initialValues);
        }

        $basePipeline = $this->basePipeline();
        if ($basePipeline && count($basePipeline) > 0) {
            $schema->pipeline($basePipeline);
        }

        if ($this->single) {
            $schema->single();
        }

        return $schema;
    }

    public static function create(): Collection
    {
        return new static();
    }

    public static function doAggregate(array $pipeline = [])
    {
        return self::structure()->aggregate($pipeline);
    }

    public  function aggregate()
    {
        return self::structure()->aggregate($this->pipeline);
    }

    public static function richFindOne($params)
    {


        throw new \Error('Method richFindOne not implemented');
    }
}
