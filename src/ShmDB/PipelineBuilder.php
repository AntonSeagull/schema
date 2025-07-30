<?php


namespace Shm\ShmDB;

use InvalidArgumentException;

class PipelineBuilder
{


    public $pipeline = [];


    public static function create(): PipelineBuilder
    {
        return new self();
    }


    public function getPipeline(): array
    {
        return $this->pipeline;
    }

    public function setPipeline(array $pipeline): static
    {
        $this->pipeline = $pipeline;
        return $this;
    }


    public function addToPipeline($pipeline): static
    {
        if (!$pipeline) {
            return $this;
        }

        $pipeline = mDB::replaceStringToObjectIds($pipeline);

        $this->pipeline = array_merge($this->pipeline, $pipeline);




        return $this;
    }

    public function addFields(array $fields): static
    {
        if (!$fields || !is_array($fields) || count($fields) === 0) {
            return $this;
        }

        $this->pipeline[] = ['$addFields' => $fields];

        return $this;
    }


    public function match(array $match)
    {
        if (!$match || !is_array($match) || count($match) === 0) {
            return $this;
        }


        $this->pipeline[] = ['$match' => $match];

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


    public function project(array | null $project)
    {

        if (!$project) {
            return $this;
        }

        if (array_is_list($project)) {
            $project = array_combine($project, array_fill(0, count($project), 1));
        }

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

        if (is_null($skip)) return $this;



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

    public function count(string $name)
    {
        $this->validateString($name, 'name');
        $this->pipeline[] = ['$count' => $name];
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




    public function groupBy(string $field, array $group)
    {
        $this->validateString($field, 'field');
        $this->validateArray($group, 'group');
        $this->pipeline[] = ['$group' => [
            '_id' => '$' . $field,
        ] + $group];
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

    public function toJson(): string
    {
        return json_encode($this->pipeline, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
