<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\EnumType as GraphQLEnumType;
use Shm\CachedType\CachedEnumType;
use Shm\CachedType\CachedInputObjectType;
use Shm\GQLUtils\AutoPostfix;
use Shm\GQLUtils\Utils;

class EnumType extends BaseType
{
    public string $type = 'enum';


    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function normalize(mixed $value): mixed
    {
        if ($value === null) {
            return $this->default;
        }
        if (isset($this->values[$value])) {
            return $value;
        }
        return null;
    }


    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!isset($this->values[$value])) {
            $field = $this->title ?? 'Value';
            throw new \InvalidArgumentException("{$field} must be one of the allowed values: " . implode(', ', array_keys($this->values)));
        }
    }



    public function GQLFilterTypeInput(): ?Type
    {


        return CachedInputObjectType::create([
            'name' => Utils::onlyLetters($this->key) . AutoPostfix::get(array_keys($this->values)) . "InputFilterInput",
            'fields' => [
                'in' => [
                    'type' => Type::listOf($this->GQLType()),

                ],
                'nin' => [
                    'type' => Type::listOf($this->GQLType()),
                ],
                'all' => [
                    'type' => Type::listOf($this->GQLType()),
                ],

            ],
        ]);
    }


    private function getEnumTypeName(): string
    {
        if (!$this->key) {
            throw new \InvalidArgumentException("Key is not set for EnumType." . print_r($this->values, true));
        }

        return Utils::onlyLetters($this->key) . AutoPostfix::get(array_keys($this->values)) . 'Enum';
    }


    public function GQLType(): Type | array | null
    {


        return  CachedEnumType::create([
            'name' => $this->getEnumTypeName(),
            'values' => $this->values,
        ]);
    }


    public function GQLTypeInput(): ?Type
    {
        return $this->GQLType();
    }

    public function tsTypeName(): string
    {
        return $this->getEnumTypeName();
    }

    public function tsType(): string
    {
        $tsType =  `export enum ` . $this->tsTypeName() . ` {\n`;

        foreach ($this->values as $key => $value) {
            $tsType .= `    ` . Utils::upperCase(Utils::onlyLetters($key)) . ` = "` . Utils::onlyLetters($key) . `",\n`;
        }


        $tsType .=  `}\n`;


        return $tsType;
    }
}