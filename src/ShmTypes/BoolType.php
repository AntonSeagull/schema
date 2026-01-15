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
            return $this->getDefault();
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





    // $columnsWidth is inherited from BaseType

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
