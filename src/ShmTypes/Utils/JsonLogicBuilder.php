<?php

namespace Shm\ShmTypes\Utils;


class JsonLogicBuilder
{
    private array $logic = [];

    private array $fields = [];


    public function equals(string $field, $value): static
    {

        if ($value === false) {
            return $this->notEquals($field, true);
        }

        $this->fields[] = $field;
        $this->logic[] = [
            "===" => [["var" => $field], $value]
        ];
        return $this;
    }


    public function notEquals(string $field, $value): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "!=" => [["var" => $field], $value]
        ];
        return $this;
    }


    public function greaterThan(string $field, $value): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            ">" => [["var" => $field], $value]
        ];
        return $this;
    }

    public function lessThan(string $field, $value): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "<" => [["var" => $field], $value]
        ];
        return $this;
    }


    public function contains(string $field, string $value): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "in" => [$value, ["var" => $field]]
        ];
        return $this;
    }


    public function missing(string $field): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "missing" => [$field]
        ];
        return $this;
    }


    public function some(string $field, array $condition): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "some" => [["var" => $field], $condition]
        ];
        return $this;
    }

    public function in(string $field, array $values): static
    {
        $this->fields[] = $field;

        $singleValueCondition = [
            "or" => array_map(function ($value) use ($field) {
                return [
                    "===" => [["var" => $field], $value]
                ];
            }, $values)
        ];

        $arrayCondition = [
            "some" => [
                ["var" => $field],
                [
                    "or" => array_map(function ($value) {
                        return [
                            "===" => [["var" => ""], $value]
                        ];
                    }, $values)
                ]
            ]
        ];

        $this->logic[] = [
            "or" => [$singleValueCondition, $arrayCondition]
        ];

        return $this;
    }


    public function all(string $field, array $condition): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "all" => [["var" => $field], $condition]
        ];
        return $this;
    }

    public function isNull(string $field): static
    {
        $this->fields[] = $field;
        $this->logic[] = [
            "or" => [
                ["missing" => [$field]],
                ["===" => [["var" => $field], null]]
            ]
        ];
        return $this;
    }


    public function and(): static
    {
        $this->logic = [
            "and" => $this->logic
        ];
        return $this;
    }


    public function or(): static
    {
        $this->logic = [
            "or" => $this->logic
        ];
        return $this;
    }


    public function build(): array
    {
        return $this->logic;
    }


    public function getFields(): array
    {
        return array_unique($this->fields);
    }


    public function reset(): static
    {
        $this->logic = [];
        return $this;
    }
}
