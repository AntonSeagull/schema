<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;
use Shm\Shm;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;

class IntType extends BaseType
{
    public string $type = 'int';

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
            return (int) $value;
        }

        return  null;
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_int($value)) {
            $field = $this->title ?? 'Value';
            throw new \InvalidArgumentException("{$field} must be an integer.");
        }
    }

    public function GQLType(): Type | array | null
    {
        return Type::int();
    }

    public function GQLTypeInput(): ?Type
    {
        return Type::int();
    }
    public function filterType(): ?BaseType
    {

        return  Shm::structure([
            'gte' => Shm::int()->title('Больше или равно'),
            'eq' => Shm::int()->title('Равно'),
            'lte' => Shm::int()->title('Меньше или равно'),
        ])->fullEditable();
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
        $TSType = new TSType("Int", "number");


        return $TSType;
    }
}
