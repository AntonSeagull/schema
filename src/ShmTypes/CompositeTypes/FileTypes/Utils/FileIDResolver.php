<?php

namespace Shm\ShmTypes\CompositeTypes\FileTypes\Utils;

use Shm\ShmTypes\StructureType;
use Shm\ShmTypes\BaseType;
use Shm\ShmDB\mDB;
use Shm\ShmDB\mDBRedis;
use Shm\ShmTypes\ArrayOfType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileIDType;
use Shm\ShmUtils\Response;

class FileIDResolver
{



    public BaseType | null $structure = null;
    public mixed $data = null;

    public function __construct($structure, mixed $data)
    {


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
                foreach ($value as $key => $item) {



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

    private function setPathValues(array $path, mixed $value, array $filesById, FileIDType $fileItem): mixed
    {
        $firstSegment = array_shift($path);

        if (count($path) == 0) {

            if ($firstSegment == '[]') {
                $newValue = [];

                if (is_object($value) || is_array($value) || $value instanceof \Traversable) {
                    foreach ($value as $item) {


                        if (isset($filesById[(string)$item])) {
                            $item = $fileItem->getDocument()->normalize($filesById[(string)$item]);
                            $item = $fileItem->getDocument()->removeOtherItems($item);
                        }

                        $newValue[] = $item;
                    }
                }

                return $newValue;
            }



            if (isset($value[$firstSegment]) && $value[$firstSegment]) {

                $fieldValue = $value[$firstSegment];


                if (isset($filesById[(string)$fieldValue])) {
                    $fieldValue = $fileItem->getDocument()->normalize($filesById[(string)$fieldValue]);
                    $fieldValue = $fileItem->getDocument()->removeOtherItems($fieldValue);
                }

                $value[$firstSegment] = $fieldValue;

                return $value;
            }


            return $value;
        }



        if ($firstSegment === '[]') {



            if (is_object($value) || is_array($value) || $value instanceof \Traversable) {
                foreach ($value as $key => $item) {


                    $value[$key] = $this->setPathValues($path, $item, $filesById, $fileItem);
                }
            }
        } else {



            if (isset($value[$firstSegment])) {

                $value[$firstSegment] = $this->setPathValues($path, $value[$firstSegment], $filesById, $fileItem);
            }
        }

        return $value;
    }

    public function resolve()
    {



        if (!$this->structure || !$this->data) {
            return $this->data;
        }




        if (!($this->structure instanceof StructureType || $this->structure instanceof ArrayOfType)) {
            return $this->data;
        }



        Response::startTraceTiming("fileResolver");


        if ($this->structure instanceof ArrayOfType) {

            $this->structure->itemType->updateKeys();
        } else {
            $this->structure->updateKeys();
        }

        $this->structure->updatePath();


        $fileItems = $this->structure->findItemsByCondition(function ($item) {
            return $item instanceof FileIDType;
        });






        $fileIds = [];



        $cacheFilesById = [];

        foreach ($fileItems as $fileItem) {



            $path = $fileItem->path;







            $fileIdsFromPath = $this->getPathValues($path, $this->data);



            $fileIdsFromPathResult = [];
            foreach ($fileIdsFromPath as $fileId) {
                $cacheFileValue = mDBRedis::get("_files", (string)$fileId);

                if ($cacheFileValue) {

                    $cacheFilesById[(string)$fileId] = $cacheFileValue;
                } else {
                    $fileIdsFromPathResult[] = $fileId;
                }
            }

            $fileIds = [...$fileIds, ...$fileIdsFromPathResult];
        }



        $files = [];
        if (count($fileIds) > 0) {
            $files = mDB::collection('_files')->find(['_id' => ['$in' => $fileIds]])->toArray();
        }

        $filesById = [];
        foreach ($files as $file) {
            $filesById[(string)$file['_id']] = $file;

            mDBRedis::save("_files", (string)$file['_id'], $file);
        }

        $filesById = [...$filesById, ...$cacheFilesById];


        $resolvedData = $this->data;


        foreach ($fileItems as $fileItem) {
            $path = $fileItem->path;
            $resolvedData = $this->setPathValues($path, $resolvedData, $filesById, $fileItem);
        }

        Response::endTraceTiming("fileResolver");

        return $resolvedData;
    }
}
