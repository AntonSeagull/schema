<?php

namespace Shm\ShmTypes;


use Shm\CachedType\CachedInputObjectType;
use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;

/**
 * Integer type for schema definitions
 * 
 * This class represents an integer type with validation and normalization.
 */
class IntType extends BaseType
{
    public string $type = 'int';

    /**
     * Constructor
     */
    public function __construct()
    {
        // Nothing extra for now
    }

    /**
     * Normalize integer value
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

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Validate integer value
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
        if (!is_int($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be an integer.");
        }
    }



    public function filterType($safeMode = false): ?BaseType
    {



        $itemTypeFilter = Shm::structure([
            'gte' => Shm::int()->title('Больше или равно')->col(8),
            'gt' => Shm::int()->title('Больше')->col(8),
            'eq' => Shm::int()->title('Равно')->col(8),
            'lte' => Shm::int()->title('Меньше или равно')->col(8),
            'lt' => Shm::int()->title('Меньше')->col(8),
        ])->editable()->staticBaseTypeName("IntFilterType");

        return $itemTypeFilter->editable()->inAdmin($this->inAdmin)->title($this->title);
    }


    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {


        $path = $absolutePath ? implode('.', $absolutePath) . '.' . $this->key : $this->key;


        $match = [];

        if (isset($filter['gt'])) {
            $match['$gt'] = (int) $filter['gt'];
        }
        if (isset($filter['lt'])) {
            $match['$lt'] = (int) $filter['lt'];
        }

        if (isset($filter['gte'])) {
            $match['$gte'] = (int) $filter['gte'];
        }
        if (isset($filter['eq'])) {
            $match['$eq'] = (int) $filter['eq'];
        }
        if (isset($filter['lte'])) {
            $match['$lte'] = (int) $filter['lte'];
        }
        if (empty($match)) {
            return null;
        }
        return [
            [
                '$match' => [
                    $path => $match
                ]
            ]
        ];
    }




    public function tsType(): TSType
    {
        $TSType = new TSType("number");


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

    public function exportRow(mixed $value): string | array | null
    {
        if ($value) {
            return (string)$value;
        } else {
            return 0;
        }
    }
}
