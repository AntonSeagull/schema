<?php

namespace Shm\ShmTypes;

use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;
use Shm\Shm;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;

class UnixDateType extends BaseType
{
    public string $type = 'unixdate';

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
            throw new \InvalidArgumentException("{$field} must be a Unix timestamp (integer).");
        }
    }

    /**
     * Return GraphQL scalar type for output.
     */
    public function GQLType(): Type | array | null
    {
        return Type::int();
    }

    /**
     * Return GraphQL scalar type for input.
     */
    public function GQLTypeInput(): ?Type
    {
        return Type::int();
    }


    public function filterType(): ?BaseType
    {

        if ($this->filterType) {
            return $this->filterType;
        }


        $itemTypeFilter = Shm::structure([
            'gte' => Shm::int()->title('Больше или равно'),
            'eq' => Shm::int()->title('Равно'),
            'lte' => Shm::int()->title('Меньше или равно'),
        ])->fullEditable();

        $this->filterType = $itemTypeFilter;
        return  $this->filterType;
    }





    public function tsType(): TSType
    {
        $TSType = new TSType("Int", "number");


        return $TSType;
    }
}