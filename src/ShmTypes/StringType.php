<?php

namespace Shm\ShmTypes;

use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;

/**
 * String type for schema definitions
 * 
 * This class represents a string type with various processing options
 * including trimming and case conversion.
 */
class StringType extends BaseType
{
    public string $type = 'string';
    public bool $trim = false;
    public bool $uppercase = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Nothing extra for now
    }

    /**
     * Enable trimming of string values
     * 
     * @param bool $trim Whether to trim strings
     * @return static
     */
    public function trim(bool $trim = true): static
    {
        $this->trim = $trim;
        return $this;
    }

    /**
     * Enable uppercase conversion of string values
     * 
     * @param bool $uppercase Whether to convert to uppercase
     * @return static
     */
    public function uppercase(bool $uppercase = true): static
    {
        $this->uppercase = $uppercase;
        return $this;
    }

    public bool $lowercase = false;

    //Противоположный метод для uppercase
    public function lowercase(bool $lowercase = true): static
    {
        $this->lowercase = $lowercase;
        return $this;
    }

    /**
     * Process string value according to configured options
     * 
     * @param mixed $value Value to process
     * @return mixed Processed value
     */
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

        if ($this->lowercase) {
            $value = mb_strtolower($value);
        }
        return $value;
    }





    /**
     * Normalize string value
     * 
     * @param mixed $value Value to normalize
     * @param bool $addDefaultValues Whether to add default values
     * @param string|null $processId Process ID for tracking
     * @return mixed Normalized value
     */
    public function normalize(mixed $value, $addDefaultValues = false, string|null $processId = null): mixed
    {
        if ($addDefaultValues && $value === null && $this->defaultIsSet) {
            return $this->processValue($this->getDefault());
        }
        return $this->processValue((string) $value);
    }

    /**
     * Validate string value
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

        if (!is_string($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be a string.");
        }
    }


    // $columnsWidth is inherited from BaseType


    public function tsType(): TSType
    {
        $TSType = new TSType("string");


        return $TSType;
    }



    public function exportRow(mixed $value): string | array | null
    {
        if ($value) {
            return (string)$value;
        } else {
            return "";
        }
    }

    public function fallbackDisplayValues($values): array | string | null
    {
        return $values;
    }



    public function filterType($safeMode = false): ?BaseType
    {



        $itemTypeFilter = Shm::structure([
            'startsWith' => Shm::string()->title('Начинается с'),
            'endsWith' => Shm::string()->title('Заканчивается на'),
            'contains' => Shm::string()->title('Содержит'),
            'notContains' => Shm::string()->title('Не содержит'),
            'isEmpty' => Shm::enum([
                'true' => 'Да',
                'false' => 'Нет'
            ])->title('Не заполнено'),
        ])->editable();


        $itemTypeFilter->staticBaseTypeName("StringFilterType");

        return $itemTypeFilter->editable()->inAdmin($this->inAdmin)->title($this->title);
    }



    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {


        $startsWith = $filter['startsWith'] ?? null;
        $endsWith = $filter['endsWith'] ?? null;
        $contains = $filter['contains'] ?? null;
        $notContains = $filter['notContains'] ?? null;
        $isEmpty = $filter['isEmpty'] ?? null;

        $path = $absolutePath ? implode('.', $absolutePath) . '.' . $this->key : $this->key;

        $pipeline = [];

        if (!!$startsWith) {
            $pipeline[] = [
                '$match' => [
                    $path => ['$regex' => '^' . $startsWith, '$options' => 'i']
                ]
            ];
        }
        if (!!$endsWith) {
            $pipeline[] = [
                '$match' => [
                    $path => ['$regex' => $endsWith . '$', '$options' => 'i']
                ]
            ];
        }

        if (!!$contains) {
            $pipeline[] = [
                '$match' => [
                    $path => ['$regex' => $contains, '$options' => 'i']
                ]
            ];
        }
        if (!!$notContains) {
            $pipeline[] = [
                '$match' => [
                    $path => ['$regex' => '^(?!' . $notContains . ').*$', '$options' => 'i']
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
            }
            if ($isEmpty == 'false') {
                $pipeline[] = [
                    '$match' => [
                        '$or' => [
                            [$path => ['$exists' => true]],
                            [$path => ['$ne' => null]],
                        ]
                    ]
                ];
            }
        }


        return $pipeline;
    }
}
