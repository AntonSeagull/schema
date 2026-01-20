<?php

namespace Shm\ShmTypes\Utils;

use Shm\Shm;
use Shm\ShmTypes\StructureType;
use Shm\ShmTypes\BaseType;
use Shm\ShmDB\mDB;
use Shm\ShmDB\mDBRedis;
use Shm\ShmTypes\ArrayOfType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileIDType;
use Shm\ShmTypes\CompositeTypes\FileTypes\Utils\FileIDResolver;
use Shm\ShmTypes\IDsType;
use Shm\ShmTypes\IDType;
use Shm\ShmUtils\Response;

class ExtensionsResolver
{



    public BaseType | null $structure = null;
    public mixed $data = null;

    public array $extensions = [];

    public function __construct($structure, mixed $data, array $extensions)
    {


        $this->extensions = $extensions;


        $this->structure = $structure;
        $this->data = $data;
    }

    private function getPathValues(array $path, mixed $value): array
    {

        $result = [];

        $firstSegment = array_shift($path);
        if (count($path) == 0) {

            if ($firstSegment === '[]') {
                if ($value) {
                    return $value;
                } else {
                    return [];
                }
            } else {
                if (isset($value[$firstSegment])) {
                    return [$value[$firstSegment]];
                } else {
                    return [];
                }
            }
        }





        if ($firstSegment === '[]') {



            if (is_object($value) || is_array($value) || $value instanceof \Traversable) {
                foreach ($value as $item) {


                    $vals = $this->getPathValues($path, $item);
                    $result = [...$result, ...$vals];
                }
            }
        } else {



            if (isset($value[$firstSegment])) {

                $vals = $this->getPathValues($path, $value[$firstSegment]);


                $result = [...$result, ...$vals];
            }
        }


        return $result;
    }



    public function resolve()
    {



        if (!$this->structure || !$this->data || count($this->extensions) == 0) {
            return null;
        }




        if (!($this->structure instanceof StructureType || $this->structure instanceof ArrayOfType)) {
            return null;
        }

        Response::startTraceTiming("extensionsResolver");


        $extensionsStructure = $this->structure->extensionsStructure();

        $enabledExtensions = array_keys($extensionsStructure);



        $this->extensions = array_intersect($this->extensions, $enabledExtensions);


        if (count($this->extensions) == 0) {
            return null;
        }

        if ($this->structure instanceof ArrayOfType) {

            $this->structure->itemType->updateKeys();
            $this->structure->itemType->updatePath();
        } else {
            $this->structure->updateKeys();
            $this->structure->updatePath();
        }


        $extensionItems = $this->structure->findItemsByCondition(function ($item) {
            if ($item instanceof IDType || $item instanceof IDsType) {



                if (in_array($item->collection, $this->extensions)) {
                    return true;
                }
            }
            return false;
        });






        $documentIds = [];


        $resultStructureItems = [];




        foreach ($extensionItems as $extensionItem) {


            $path = $extensionItem->getPathArrayToRoot([], true);


            if ($extensionItem instanceof IDsType) {
                $path[] = '[]';
            }




            $documentIdsFromPath =  $this->getPathValues($path, $this->data);

            if (isset($documentIds[$extensionItem->collection])) {
                $documentIds[$extensionItem->collection] = [...$documentIds[$extensionItem->collection], ...$documentIdsFromPath];
            } else {
                $documentIds[$extensionItem->collection] = $documentIdsFromPath;
            }
        }


        /*(
            $documentIdsForMongoDB = [];
            foreach ($documentIdsFromPath as $documentId) {

                $cacheValue = mDBRedis::get($extensionItem->collection, (string)$documentId);

                if($cacheValue){

                    $extensionsData[$extensionItem->collection][] = $cacheValue;

                    

                }else{
                    $documentIdsForMongoDB[] = $documentId;
                }

            }
*/
        $extensionsData = [];


        $_documentIds = [];

        foreach ($documentIds as $collection => $documentIds) {

            $documentIds = array_unique($documentIds);

            $documentIdsForMongoDB = [];

            foreach ($documentIds as $documentId) {
                $cacheValue = mDBRedis::get($collection, (string)$documentId);
                if ($cacheValue) {

                    if (!isset($extensionsData[$collection])) {
                        $extensionsData[$collection] = [];
                    }



                    $extensionsData[$collection][] = $cacheValue;
                } else {
                    $documentIdsForMongoDB[] = $documentId;
                }
            }


            $_documentIds[$collection] = $documentIdsForMongoDB;
        }







        foreach ($_documentIds as $collection => $documentIds) {


            $structure = $extensionsStructure[$collection];

            if ($structure instanceof StructureType) {

                $arrayOfStructure = Shm::arrayOf($structure);

                $resultStructureItems[$collection] =  $arrayOfStructure;

                if (count($documentIds) == 0) {
                    continue;
                }



                $_data = $structure->find(['_id' => ['$in' => $documentIds]]);

                foreach ($_data as $item) {
                    mDBRedis::save($structure->collection, (string)$item['_id'], $item);
                }


                if (isset($extensionsData[$collection])) {
                    $extensionsData[$collection] = [...$extensionsData[$collection], ...$_data];
                } else {
                    $extensionsData[$collection] = $_data;
                }
            }
        }



        $resultStructure = Shm::structure($resultStructureItems)->key("base");



        $extensionsData = $resultStructure->normalize($extensionsData);
        $extensionsData = $resultStructure->removeOtherItems($extensionsData);


        $fileIDResolver = new FileIDResolver($resultStructure, $extensionsData);
        $extensionsData = $fileIDResolver->resolve();

        $extensionsData = mDB::replaceObjectIdsToString($extensionsData);


        // exit;


        Response::endTraceTiming("extensionsResolver");

        return  $extensionsData;
    }
}
