<?php

namespace Shm\ShmTypes;


use Shm\CachedType\CachedInputObjectType;
use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;

class UnixDateTimeType extends BaseType
{
    public string $type = 'unixdatetime';

    public function __construct()
    {
        // Nothing extra for now
    }

    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if ($addDefaultValues &&  $value === null && $this->defaultIsSet) {
            return $this->default;
        }
        return (int) $value;
    }

    /**
     * Validate that the value is a valid Unix timestamp (int).
     */
    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_int($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be a Unix timestamp (integer).");
        }
    }





    public function filterType($safeMode = false): ?BaseType
    {



        $itemTypeFilter = Shm::structure([
            'gte' => Shm::unixdatetime()->title('Больше')->setCol(12),
            'lte' => Shm::unixdatetime()->title('Меньше')->setCol(12),
            'eq' => Shm::unixdatetime()->title('Равно'),
        ])->editable()->staticBaseTypeName("UnixDateFilterType");



        return $itemTypeFilter->editable()->inAdmin($this->inAdmin)->title($this->title);
    }


    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {


        $path = $absolutePath ? implode('.', $absolutePath) . '.' . $this->key : $this->key;


        $match = [];

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



        return null;
    }






    public function tsType(): TSType
    {
        $TSType = new TSType("number");



        return $TSType;
    }
}
