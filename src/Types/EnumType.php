<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\EnumType as GraphQLEnumType;
use Shm\CachedType\CachedEnumType;
use Shm\CachedType\CachedInputObjectType;
use Shm\GQLUtils\AutoPostfix;
use Shm\GQLUtils\Utils;
use Shm\Shm;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;

class EnumType extends BaseType
{
    public string $type = 'enum';


    public function __construct(array $values)
    {

        if (is_numeric(array_keys($values)[0])) {


            $values = array_combine($values, $values);
            if ($values === false) {
                throw new \InvalidArgumentException("Values must be an associative array or a simple array.");
            }
        }




        $this->values = $values;
    }

    public function normalize(mixed $value, $addDefaultValues = false): mixed
    {

        if ($addDefaultValues &&  $value === null && $this->defaultIsSet) {
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


    public function filterType(): ?BaseType
    {

        return  Shm::structure([
            'in' => Shm::arrayOf(Shm::enum($this->values)->title('Включает значения')),
            'nin' => Shm::arrayOf(Shm::enum($this->values)->title('Исключает значения')),
            'all' => Shm::arrayOf(Shm::enum($this->values)->title('Все значения')),
        ])->fullEditable();
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

    public function tsType(): TSType
    {




        $tsTypeValue = [];

        foreach ($this->values as $key => $value) {
            $tsTypeValue[] = Utils::upperCase($key) . ' = "' . $key . '"';
        }
        $TSType = new TSType($this->getEnumTypeName(), '{\n' . implode(',\n', $tsTypeValue) . '\n}', true);



        return $TSType;
    }
}
