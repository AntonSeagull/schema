<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use Shm\Shm;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;

class BoolType extends BaseType
{
    public string $type = 'bool';

    public function __construct()
    {
        // Nothing extra for now
    }

    public function normalize(mixed $value, $addDefaultValues = false): mixed
    {

        if ($addDefaultValues && $value === null && $this->defaultIsSet) {
            return $this->default;
        }

        if (is_bool($value)) {
            return $value;
        } else {
            return null;
        }
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_bool($value)) {
            $field = $this->title ?? 'Value';
            throw new \InvalidArgumentException("{$field} must be a boolean.");
        }
    }


    public function GQLType(): Type | array | null
    {
        return Type::boolean();
    }


    public function GQLTypeInput(): ?Type
    {
        return Type::boolean();
    }

    public function filterToPipeline($filter, array | null $absolutePath = null): ?array
    {



        if (is_bool($filter)) {


            $path = $absolutePath ? implode('.', $absolutePath) . '.' . $this->key : $this->key;

            if ($filter) {
                return [
                    [
                        '$match' => [
                            $path => true
                        ]
                    ]
                ];
            } else {


                return [
                    [
                        '$match' => [
                            $path => ['$ne' => true]
                        ]
                    ]
                ];
            }
        }

        return null;
    }

    public function filterType(): ?BaseType
    {
        return Shm::bool()->editable();
    }

    public function tsType(): TSType
    {
        $TSType = new TSType('Boolean', 'boolean');


        return $TSType;
    }
}