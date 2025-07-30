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


    public bool $trim = false;
    public bool $uppercase = false;


    public function trim(bool $trim = true): static
    {
        $this->trim = $trim;
        return $this;
    }

    public function uppercase(bool $uppercase = true): static
    {
        $this->uppercase = $uppercase;
        return $this;
    }


    private function processValue(mixed $value): mixed
    {

        if (!$value || !is_string($value)) {
            return $value;
        }

        if ($this->trim) {
            $value = trim($value);
        }
        if ($this->uppercase) {
            $value = mb_strtoupper($value);
        }
        return $value;
    }





    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if ($addDefaultValues &&  $value === null && $this->defaultIsSet) {
            return  $this->processValue($this->default);
        }
        return $this->processValue((string) $value);
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);

        if ($value === null) {
            return;
        }

        if (!is_string($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be a string.");
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