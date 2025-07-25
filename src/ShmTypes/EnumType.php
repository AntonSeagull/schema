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
                throw new \Exception("Values must be an associative array or a simple array.");
            }
        }




        $this->values = $values;
    }

    public array $valuesColor;

    public function color(string | array $key, $color): EnumType
    {

        if (is_array($key)) {
            foreach ($key as $k) {
                $this->valuesColor[$k] = $color;
            }
            return $this;
        }

        $this->valuesColor[$key] = $color;
        return $this;
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
            throw new \Exception("{$field} must be one of the allowed values: " . implode(', ', array_keys($this->values)));
        }
    }


    public function filterType($safeMode = false): ?BaseType
    {




        $itemTypeFilter =  Shm::structure([
            'eq' => Shm::enum($this->values)->title('Равно'),
            'in' => Shm::arrayOf(Shm::enum($this->values))->title('Включает значения'),
            'nin' => Shm::arrayOf(Shm::enum($this->values))->title('Исключает значения'),
            'all' => Shm::arrayOf(Shm::enum($this->values))->title('Все значения'),
            'isEmpty' => Shm::enum([
                'true' => 'Да',
                'false' => 'Нет'
            ])->title('Не заполнено'),
        ])->editable()->inAdmin(true);

        return $itemTypeFilter->inAdmin($this->inAdmin)->title($this->title);
    }


    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {

        $in = $filter['in'] ?? null;
        $nin = $filter['nin'] ?? null;
        $all = $filter['all'] ?? null;
        $eq = $filter['eq'] ?? null;
        $isEmpty = $filter['isEmpty'] ?? null;

        $path = $absolutePath ? implode('.', $absolutePath) . '.' . $this->key : $this->key;

        $pipeline = [];

        if ($eq !== null) {
            $pipeline[] = [
                '$match' => [
                    $path => $eq
                ]
            ];
        }


        if ($in !== null) {
            $pipeline[] = [
                '$match' => [
                    $path => ['$in' => $in]
                ]
            ];
        }
        if ($nin !== null) {
            $pipeline[] = [
                '$match' => [
                    $path => ['$nin' => $nin]
                ]
            ];
        }
        if ($all !== null) {
            $pipeline[] = [
                '$match' => [
                    $path => ['$all' => $all]
                ]
            ];
        }
        if ($isEmpty !== null) {


            if ($isEmpty == 'true') {
                $pipeline[] = [
                    '$match' => [
                        '$or' => [
                            [$path => null],
                            [$path => ['$exists' => false]],
                        ]
                    ]
                ];
            } else {
                $pipeline[] = [
                    '$match' => [
                        $path => ['$ne' => null]
                    ]
                ];
            }
        }


        return $pipeline;
    }









    private function getEnumTypeName(): string
    {
        if (!$this->key) {
            throw new \Exception("getEnumTypeName -> Key is not set for EnumType" . ' ' . print_r($this->path) . ' ' .  print_r($this->values, true));
        }

        return ShmUtils::onlyLetters($this->key) . AutoPostfix::get(array_keys($this->values)) . 'Enum';
    }




    public function tsType(): TSType
    {

        $tsTypeValue = [];

        foreach ($this->values as $key => $value) {
            $tsTypeValue[] = '"' . $key . '"';
        }
        $TSType = new TSType($this->getEnumTypeName(),  implode('|', $tsTypeValue), false);

        /*
        $tsTypeValue = [];

        foreach ($this->values as $key => $value) {
            $tsTypeValue[] = ShmUtils::upperCase($key) . ' = "' . $key . '"';
        }
        $TSType = new TSType($this->getEnumTypeName(), '{\n' . implode(',\n', $tsTypeValue) . '\n}', true);

*/

        return $TSType;
    }
}
