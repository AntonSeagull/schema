<?php

namespace Shm\ShmTypes;


use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;

class BoolType extends BaseType
{
    public string $type = 'bool';

    public function __construct()
    {
        // Nothing extra for now
    }

    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if ($addDefaultValues && $value === null && $this->defaultIsSet) {
            return $this->default;
        }

        if (is_bool($value)) {
            return $value;
        } else {
            return null;
        }
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_bool($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be a boolean.");
        }
    }



    public function exportRow(mixed $value): string | array | null
    {
        if ($value) {
            return "Да";
        } else {
            return "Нет";
        }
    }


    public function computedReport(StructureType | null $root = null, $path = [], $pipeline = [])
    {

        if (!$root) {

            new \Exception("Root structure is not set for EnumType report. Path: " . implode('.', $path));
        }

        if (!$this->report) {
            return null;
        }


        $basePipeline = [


            [
                '$addFields' => [
                    implode('.', $path) => [
                        '$cond' => [
                            'if' => [
                                '$and' => [
                                    ['$ne' => ['$' . implode('.', $path), null]],
                                    ['$in' => ['$' . implode('.', $path), [true, false]]]
                                ]
                            ],
                            'then' => '$' . implode('.', $path),
                            'else' => false
                        ]
                    ]
                ]
            ],
            [
                '$group' => [
                    '_id' => '$' . implode('.', $path),
                    'value' => ['$sum' => 1],


                ]
            ],
            [
                '$project' => [
                    'value' => 1,

                ]
            ]
        ];




        $result =  $root->aggregate([
            ...$pipeline,
            ...$basePipeline,


        ])->toArray();


        foreach ($result as &$item) {

            $item['name'] = $item['_id'] ? 'Да' : 'Нет';
        }



        return [

            'type' => $this->type,

            'title' => $this->title,

            'main' => [
                [
                    'view' => 'pie',
                    'title' =>  $this->title,
                    'result' => $result,
                ],


            ],


        ];
    }


    public $columnsWidth = 100;

    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {






        $path = $absolutePath ? implode('.', $absolutePath) . '.' . $this->key : $this->key;

        if ($filter == 'true') {
            return [
                [
                    '$match' => [
                        $path => true
                    ]
                ]
            ];
        }
        if ($filter == 'false') {


            return [
                [
                    '$match' => [
                        $path => ['$ne' => true]
                    ]
                ]
            ];
        }


        return null;
    }

    public function filterType($safeMode = false): ?BaseType
    {


        $itemTypeFilter = Shm::enum([
            "true" => "Да",
            "false" => "Нет",
        ])->editable();

        return $itemTypeFilter->inAdmin($this->inAdmin)->title($this->title);
    }

    public function tsType(): TSType
    {
        $TSType = new TSType('boolean');


        return $TSType;
    }
}
