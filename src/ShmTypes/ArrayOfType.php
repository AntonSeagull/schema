<?php

namespace Shm\ShmTypes;

use Sentry\Util\Str;
use Shm\Shm;
use Shm\ShmDB\mDB;
use Shm\ShmDB\mDBRedis;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmUtils\DeepAccess;
use Shm\ShmUtils\Response;
use Traversable;

class ArrayOfType extends BaseType
{
    public string $type = 'array';


    public function __construct(BaseType $itemType)
    {


        if ($itemType instanceof EnumType) {
            $this->type = 'enums';
        }




        $this->itemType = $itemType;
    }





    public function normalize(mixed $value, $addDefaultValues = false, string|null $processId = null): mixed
    {

        if (!(is_array($value) || $value instanceof Traversable)) {
            return null;
        }


        if ($addDefaultValues && !$value && $this->defaultIsSet) {
            return $this->default;
        }

        if (!$value) {
            return [];
        }

        $newValue = [];


        foreach ($value as $valueItem) {
            if ($valueItem === null) {
                continue;
            }

            $newValue[] = $this->itemType->normalize($valueItem, $addDefaultValues, $processId);
        }


        return $newValue;
    }



    public function removeOtherItems(mixed $value): mixed
    {
        if (!(is_array($value) || $value instanceof Traversable)) {
            return null;
        }

        $newValue = [];
        foreach ($value as $valueItem) {
            if ($valueItem === null) {
                continue;
            }

            $newValue[] = $this->itemType->removeOtherItems($valueItem);
        }

        return $newValue;
    }






    public function validate(mixed $value): void
    {
        parent::validate($value);

        if ($value === null) {
            return;
        }

        if (!is_array($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be an array.");
        }

        foreach ($value as $k => $item) {
            try {
                $this->itemType->validate($item);
            } catch (\Exception $e) {
                $field = $this->title ?? "Element {$k}";
                throw new \Exception("{$field}[{$k}]: " . $e->getMessage());
            }
        }
    }




    public function fullCleanDefault(): static
    {
        $this->defaultIsSet = false;
        $this->default = null;
        $this->itemType->fullCleanDefault();

        return $this;
    }







    public function filterType($safeMode = false): ?BaseType
    {



        $this->itemType->key = $this->key;

        $itemTypeFilter = $this->itemType->filterType($safeMode);
        if (!$itemTypeFilter) {
            return null;
        }
        $itemTypeFilter->editable();

        return $itemTypeFilter->inAdmin($this->inAdmin)->title($this->title);
    }

    public function tsType(): TSType
    {



        $TSType = new TSType($this->itemType->tsType()->getTsTypeName() . '[]', '');



        return $TSType;
    }

    public function tsInputType(): TSType
    {
        $TSType = new TSType($this->itemType->tsInputType()->getTsTypeName() . '[]', '');
        return $TSType;
    }



    public function externalData($data, $onlyDisplayRelations = false): mixed
    {



        if (!($this->itemType instanceof StructureType)) {
            return $data;
        }



        $structureType = $this->itemType;

        $structureType->updateKeys();
        $structureType->updatePath();
        $paths = $structureType->getIDsPaths([]);


        $pathsByCollections = [];


        foreach ($paths as $pathItem) {

            if (!isset($pathsByCollections[$pathItem['document']->collection])) {
                $pathsByCollections[$pathItem['document']->collection] = [];
            }

            $pathsByCollections[$pathItem['document']->collection][] = $pathItem;
        }






        foreach ($pathsByCollections as $collection => $collectionPaths) {

            Response::startTraceTiming("externalData-" . $collection);

            $allIds = [];
            foreach ($collectionPaths as $pathItem) {

                $val = [];


                foreach ($data as $item) {
                    $val = [...$val, ...DeepAccess::getByPath($item, $pathItem['path'])];
                }


                if (!$val || !is_array($val) || count($val) == 0) {
                    continue;
                }

                $allIds = [...$allIds, ...$val];
            }




            if (count($allIds) == 0) {
                continue;
            }


            $documentsById = [];



            foreach ($allIds as $index => $id) {

                //Находим в redis если есть
                $redisDoc = mDBRedis::get($collection, (string)$id);
                if ($redisDoc) {
                    $documentsById[(string)$id] = $redisDoc;

                    unset($allIds[$index]);
                }
            }

            $allIds = array_values(array_unique($allIds));




            $pathItem = $collectionPaths[0];


            $mongoDocs = [];
            if (count($allIds) > 0) {



                $mongoDocs = mDB::collection($pathItem['document']->collection)->aggregate([

                    ...$pathItem['document']->getPipeline(),
                    [
                        '$match' => [
                            '_id' => ['$in' => $allIds]
                        ]
                    ],

                ])->toArray();

                mDBRedis::updateCacheAfterChange($collection, [
                    '_id' => ['$in' => $allIds]
                ], true);
            }



            if (count($mongoDocs) == 0 && count($documentsById) == 0) {
                continue;
            }


            $mongoDocsType = Shm::arrayOf($pathItem['document']);



            $mongoDocs = $mongoDocsType->removeOtherItems($mongoDocs);

            $mongoDocs = $mongoDocsType->toOutput($mongoDocs);




            foreach ($mongoDocs as $doc) {


                $documentsById[(string) $doc['_id']] = $doc;
            }


            foreach ($collectionPaths as $pathItem) {

                $many = $pathItem['many'] ?? false;

                foreach ($data as &$item) {

                    DeepAccess::applyRecursive($item, $pathItem['path'], function ($node) use ($many, $documentsById) {

                        if ($many) {

                            $result = [];

                            if (is_object($node) || is_array($node) || $node instanceof \Traversable) {

                                foreach ($node as $id) {
                                    if (isset($documentsById[(string) $id])) {
                                        $result[] = $documentsById[(string) $id];
                                    }
                                }
                            }

                            return $result;
                        } else {



                            if (isset($documentsById[(string) $node])) {
                                return $documentsById[(string) $node];
                            }
                        }

                        return null;
                    });
                }
            }




            Response::endTraceTiming("externalData-" . $collection);
        }




        return $data;
    }

    public function exportRow(mixed $value): string | array | null
    {
        if (is_array($value) || $value instanceof Traversable) {
            $result = [];
            foreach ($value as $item) {
                $result[] = $this->itemType->exportRow($item);
            }

            if (count($result) == 0) {
                return "";
            }

            return $result;
        }
        return "";
    }
}
