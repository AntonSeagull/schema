<?php

namespace Shm\ShmTypes;


use GraphQL\Type\Definition\EnumType as GraphQLEnumType;
use Shm\CachedType\CachedEnumType;
use Shm\CachedType\CachedInputObjectType;


use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;
use Shm\ShmUtils\AutoPostfix;
use Shm\ShmUtils\ShmUtils;

/**
 * Enum type for schema definitions
 * 
 * This class represents an enum type with predefined values
 * and optional color associations for UI display.
 */
class EnumType extends BaseType
{
    public string $type = 'enum';
    public array $valuesColor = [];

    /**
     * Constructor
     * 
     * @param array $values Enum values (associative or simple array)
     * @throws \Exception If values are invalid
     */
    public function __construct(array $values)
    {
        if (is_numeric(array_keys($values)[0]) && array_keys($values)[0] == 0) {
            $values = array_combine($values, $values);
            if ($values === false) {
                throw new \Exception("Values must be an associative array or a simple array.");
            }
        }

        $this->values = $values;
    }

    /**
     * Set color for enum values
     * 
     * @param string|array $key Key or array of keys
     * @param mixed $color Color value
     * @return static
     */
    public function color(string|array $key, $color): static
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

    /**
     * Normalize enum value
     * 
     * @param mixed $value Value to normalize
     * @param bool $addDefaultValues Whether to add default values
     * @param string|null $processId Process ID for tracking
     * @return mixed Normalized value
     */
    public function normalize(mixed $value, $addDefaultValues = false, string|null $processId = null): mixed
    {
        if ($addDefaultValues && $value === null && $this->defaultIsSet) {
            return $this->getDefault();
        }

        if (is_string($value) && isset($this->values[$value])) {
            return $value;
        }
        return null;
    }

    /**
     * Validate enum value
     * 
     * @param mixed $value Value to validate
     * @throws \Exception If validation fails
     */
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


        if ($this->parent->type == "array") {
            $itemTypeFilter =  Shm::structure([
                'in' => Shm::arrayOf(Shm::enum($this->values))->title('Содержит хотя бы один из'),
                'nin' => Shm::arrayOf(Shm::enum($this->values))->title('Исключить'),
                'all' => Shm::arrayOf(Shm::enum($this->values))->title('Содержит все значения'),
                'isEmpty' => Shm::enum([
                    'true' => 'Да',
                    'false' => 'Нет'
                ])->title('Не заполнено'),
            ])->editable()->inAdmin(true)->staticBaseTypeName($this->key . "EnumFilter" . AutoPostfix::get(array_keys($this->values, true)));
        } else {

            $itemTypeFilter =  Shm::structure([
                'eq' => Shm::enum($this->values)->title('Равно'),
                'in' => Shm::arrayOf(Shm::enum($this->values))->title('Содержит хотя бы один из'),
                'nin' => Shm::arrayOf(Shm::enum($this->values))->title('Исключает значения'),
                'all' => Shm::arrayOf(Shm::enum($this->values))->title('Содержит все значения'),
                'isEmpty' => Shm::enum([
                    'true' => 'Да',
                    'false' => 'Нет'
                ])->title('Не заполнено'),
            ])->editable()->inAdmin(true)->staticBaseTypeName($this->key . "EnumFilter" . AutoPostfix::get(array_keys($this->values, true)));
        }
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

        return ShmUtils::onlyLetters($this->key) . AutoPostfix::get(array_keys($this->values), true) . 'Enum';
    }




    public function tsType(): TSType
    {

        $tsTypeValue = [];

        foreach ($this->values as $key => $value) {
            $tsTypeValue[] = '"' . $key . '"';
        }
        $TSType = new TSType($this->getEnumTypeName(),  implode('|', $tsTypeValue), false);



        return $TSType;
    }


    public function exportRow(mixed $value): string | array | null
    {
        if ($value) {
            if (isset($this->values[$value])) {
                return $this->values[$value];
            } else {
                return $value;
            }
        } else {
            return "";
        }
    }

    public function fallbackDisplayValues($value): array | string | null
    {
        if ($value) {
            if (isset($this->values[$value])) {
                return $this->values[$value];
            } else {
                return $value;
            }
        } else {
            return "";
        }
    }
}
