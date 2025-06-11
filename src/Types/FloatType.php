<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;
use Shm\Shm;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;

class FloatType extends BaseType
{
    public string $type = 'float';

    public function __construct()
    {
        // Nothing extra for now
    }

    public function normalize(mixed $value, $addDefaultValues = false): mixed
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

    public function GQLType(): Type | array | null
    {
        return Type::float();
    }

    public function GQLTypeInput(): ?Type
    {
        return Type::float();
    }

    public function filterType(): ?BaseType
    {

        return  Shm::structure([
            'gte' => Shm::float()->title('Больше или равно'),
            'eq' => Shm::float()->title('Равно'),
            'lte' => Shm::float()->title('Меньше или равно'),
        ])->fullEditable();
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
        $TSType = new TSType('Float', 'number');


        return $TSType;
    }
}
