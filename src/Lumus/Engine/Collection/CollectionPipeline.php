<?php

namespace Lumus\Engine\Collection;

use InvalidArgumentException;
use Lumus\GraphQL\GQLBuffer;

use Lumus\Schema\Elements\AnyOf;
use Lumus\Schema\Elements\Structure;
use Lumus\Schema\Elements\Type;
use Shm\ShmDB\mDB;

trait CollectionPipeline
{
    private $pipeline = [];
    private $options = [];


    public function getPipeline()
    {
        return $this->pipeline;
    }

    public function setPipeline($pipeline)
    {
        $this->pipeline = $pipeline;
        return $this;
    }






    /**
     * Функция addToPipeline добавляет в пайплайн новые этапы
     *  
     * @param array|string $pipeline
     * 
     */
    public function addToPipeline($pipeline): static
    {
        if (!$pipeline) {
            return $this;
        }

        if (is_string($pipeline)) {

            $pipeline = json_decode($pipeline, true);
            $pipeline = mDB::replaceStringToObjectIds($pipeline);
        }

        foreach ($pipeline as $val) {
            $this->pipeline[] = $val;
        }




        return $this;
    }






    public function match(array | string $filter)
    {
        if (!$filter) {
            return $this;
        }

        if (is_string($filter)) {
            $filter = json_decode($filter, true);
        }

        if (count($filter) > 0) {
            $this->validateArray($filter, 'filter');
            $filter = mDB::replaceStringToObjectIds($filter);
            $this->pipeline[] = ['$match' => $filter];
        }
        return $this;
    }

    public function search($search, $searchField = "search_string")
    {
        if (!$search) return $this;

        $search = mb_strtolower(trim($search ?? ""));

        if (strlen($search) > 0) {

            $search = str_replace(["+"], "", $search);

            $this->pipeline[] = [
                '$match' => [
                    $searchField => ['$regex' => $search, '$options' => 'i'],
                ],
            ];
        }

        return $this;
    }

    private function is_array_of_strings($array)
    {
        return is_array($array) && array_reduce($array, function ($result, $item) {
            return $result && is_string($item);
        }, true);
    }

    public function project(array | null $projection)
    {

        if (!$projection) {
            return $this;
        }

        $project = $projection;

        if ($this->is_array_of_strings($project)) {
            $project = array_values($project);

            $tmp = [];
            foreach ($project as $val) {

                $val = explode('.', $val)[0];

                $tmp[$val] = 1;
            }
            $project = $tmp;
        }

        $this->validateArray($project, 'projection');
        $this->pipeline[] = ['$project' => $project];
        return $this;
    }

    public function group(array $group)
    {
        $this->validateArray($group, 'group');
        $this->pipeline[] = ['$group' => $group];
        return $this;
    }

    public function sort(array | null $sort)
    {
        if (!$sort) {
            return $this;
        }

        $this->validateArray($sort, 'sort');
        $this->pipeline[] = ['$sort' => $sort];
        return $this;
    }

    public function skip(int | null $skip)
    {

        if (!$skip) {
            return $this;
        }

        $this->validateInt($skip, 'skip');
        $this->pipeline[] = ['$skip' => $skip];
        return $this;
    }

    public function limit(int | null $limit)
    {
        if (!$limit) {
            $limit = 10;
        }

        $this->validateInt($limit, 'limit');
        $this->pipeline[] = ['$limit' => $limit];
        return $this;
    }

    public function unwind(string $field)
    {
        $this->validateString($field, 'field');
        $this->pipeline[] = ['$unwind' => '$' . $field];
        return $this;
    }



    public function out(string $collection)
    {
        $this->validateString($collection, 'collection');
        $this->pipeline[] = ['$out' => $collection];
        return $this;
    }

    public function sample(int $size)
    {
        $this->validateInt($size, 'size');
        $this->pipeline[] = ['$sample' => ['size' => $size]];
        return $this;
    }

    public function lookup(array $lookup)
    {
        $this->validateArray($lookup, 'lookup');
        $lookup = mDB::replaceStringToObjectIds($lookup);
        $this->pipeline[] = ['$lookup' => $lookup];
        return $this;
    }

    public function addFields(array $fields)
    {

        $this->validateArray($fields, 'fields');
        $this->pipeline[] = ['$addFields' => $fields];
        return $this;
    }

    public function geoNear(array $near, string $distanceField, array $options)
    {
        $this->validateArray($near, 'near');
        $this->validateString($distanceField, 'distanceField');
        $this->validateArray($options, 'options');
        $this->pipeline[] = ['$geoNear' => [
            'near' => $near,
            'distanceField' => $distanceField,
            'spherical' => true,
        ] + $options];
        return $this;
    }

    public function geoWithin(array $geometry)
    {
        $this->validateArray($geometry, 'geometry');
        $this->pipeline[] = ['$geoWithin' => ['$geometry' => $geometry]];
        return $this;
    }

    public function groupBy(string $field, array $group)
    {
        $this->validateString($field, 'field');
        $this->validateArray($group, 'group');
        $this->pipeline[] = ['$group' => [
            '_id' => '$' . $field,
        ] + $group];
        return $this;
    }

    public function allowDiskUse(bool $allow)
    {
        $this->options['allowDiskUse'] = $allow;
        return $this;
    }

    public function batchSize(int $size)
    {
        $this->validateInt($size, 'size');
        $this->options['batchSize'] = $size;
        return $this;
    }

    public function explain(bool $explain)
    {
        $this->options['explain'] = $explain;
        return $this;
    }

    public function maxTimeMS(int $maxTimeMS)
    {
        $this->validateInt($maxTimeMS, 'maxTimeMS');
        $this->options['maxTimeMS'] = $maxTimeMS;
        return $this;
    }

    private function validateArray($value, $name)
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("The $name parameter must be an array");
        }
    }

    private function validateString($value, $name)
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException("The $name parameter must be a string");
        }
    }

    private function validateInt($value, $name)
    {
        if (!is_int($value)) {
            throw new InvalidArgumentException("The $name parameter must be an integer");
        }
    }
}
