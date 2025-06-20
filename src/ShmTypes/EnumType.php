<?php

namespace Shm\ShmTypes;


use GraphQL\Type\Definition\EnumType as GraphQLEnumType;
use Shm\CachedType\CachedEnumType;
use Shm\CachedType\CachedInputObjectType;


use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmUtils\AutoPostfix;
use Shm\ShmUtils\ShmUtils;

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

    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if ($addDefaultValues &&  $value === null && $this->defaultIsSet) {
            return $this->default;
        }



        if (is_string($value) && isset($this->values[$value])) {
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

        if ($this->filterType) {
            return $this->filterType;
        }

        $itemTypeFilter =  Shm::structure([
            'in' => Shm::arrayOf(Shm::enum($this->values)->title('Включает значения')),
            'nin' => Shm::arrayOf(Shm::enum($this->values)->title('Исключает значения')),
            'all' => Shm::arrayOf(Shm::enum($this->values)->title('Все значения')),
        ])->fullEditable();

        $this->filterType = $itemTypeFilter;
        return  $this->filterType;
    }









    private function getEnumTypeName(): string
    {
        if (!$this->key) {
            throw new \InvalidArgumentException("getEnumTypeName -> Key is not set for EnumType" . ' ' . print_r($this->path) . ' ' .  print_r($this->values, true));
        }

        return ShmUtils::onlyLetters($this->key) . AutoPostfix::get(array_keys($this->values)) . 'Enum';
    }




    public function tsType(): TSType
    {




        $tsTypeValue = [];

        foreach ($this->values as $key => $value) {
            $tsTypeValue[] = ShmUtils::upperCase($key) . ' = "' . $key . '"';
        }
        $TSType = new TSType($this->getEnumTypeName(), '{\n' . implode(',\n', $tsTypeValue) . '\n}', true);



        return $TSType;
    }
}
