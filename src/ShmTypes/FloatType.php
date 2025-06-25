<?php

namespace Shm\ShmTypes;


use Shm\CachedType\CachedInputObjectType;
use Shm\Shm;
use Shm\ShmRPC\ShmRPCCodeGen\TSType;

class FloatType extends BaseType
{
    public string $type = 'float';

    public function __construct()
    {
        // Nothing extra for now
    }

    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        if ($addDefaultValues &&  $value === null && $this->defaultIsSet) {
            return $this->default;
        }

        if (is_numeric($value)) {
            return  $value;
        }
        return null;
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_float($value) && !is_int($value)) {
            $field = $this->title ?? 'Value';
            throw new \InvalidArgumentException("{$field} must be a float.");
        }
    }


    public function filterType($safeMode = false): ?BaseType
    {



        $itemTypeFilter =  Shm::structure([
            'gte' => Shm::float()->title('Больше')->col(8),
            'eq' => Shm::float()->title('Равно')->col(8),
            'lte' => Shm::float()->title('Меньше')->col(8),
        ])->fullEditable()->staticBaseTypeName("FloatFilterType");

        return  $itemTypeFilter->fullEditable()->fullInAdmin($this->inAdmin)->title($this->title);
    }





    public function filterToPipeline($filter, array | null  $absolutePath = null): ?array
    {



        $path  = $absolutePath ? implode('.', $absolutePath) . '.' . $this->key : $this->key;


        $match = [];

        if (isset($filter['gte'])) {
            $match['$gte'] = (float) $filter['gte'];
        }
        if (isset($filter['eq'])) {
            $match['$eq'] = (float) $filter['eq'];
        }
        if (isset($filter['lte'])) {
            $match['$lte'] = (float) $filter['lte'];
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
        $TSType = new TSType('number');


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
