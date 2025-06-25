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
            throw new \InvalidArgumentException("{$field} must be a boolean.");
        }
    }


    public $columnsWidth = 100;

    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {



        if (is_bool($filter)) {


            $path = $absolutePath ? implode('.', $absolutePath) . '.' . $this->key : $this->key;

            if ($filter) {
                return [
                    [
                        '$match' => [
                            $path => true
                        ]
                    ]
                ];
            } else {


                return [
                    [
                        '$match' => [
                            $path => ['$ne' => true]
                        ]
                    ]
                ];
            }
        }

        return null;
    }

    public function filterType($safeMode = false): ?BaseType
    {

        if ($this->filterType) {
            return $this->filterType;
        }

        $itemTypeFilter = Shm::bool()->editable()->inAdmin();

        $this->filterType = $itemTypeFilter->fullEditable()->fullInAdmin($this->inAdmin)->title($this->title);
        return  $this->filterType;
    }

    public function tsType(): TSType
    {
        $TSType = new TSType('boolean');


        return $TSType;
    }
}
