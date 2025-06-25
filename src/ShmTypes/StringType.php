<?php

namespace Shm\ShmTypes;


use Shm\ShmRPC\ShmRPCCodeGen\TSType;

class StringType extends BaseType
{
    public string $type = 'string';

    public function __construct()
    {
        // Nothing extra for now
    }

    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if ($addDefaultValues &&  $value === null && $this->defaultIsSet) {
            return $this->default;
        }
        return (string) $value;
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);

        if ($value === null) {
            return;
        }

        if (!is_string($value)) {
            $field = $this->title ?? 'Value';
            throw new \InvalidArgumentException("{$field} must be a string.");
        }
    }


    public $columnsWidth = 200;


    public function tsType(): TSType
    {
        $TSType = new TSType("string");


        return $TSType;
    }

    public function getSearchPaths(): array
    {


        return [
            [
                'path' => $this->path,
            ]
        ];
    }
}
