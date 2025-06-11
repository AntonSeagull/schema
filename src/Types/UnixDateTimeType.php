<?php

namespace Shm\Types;

use GraphQL\Type\Definition\Type;
use Shm\CachedType\CachedInputObjectType;
use Shm\Shm;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;

class UnixDateTimeType extends BaseType
{
    public string $type = 'unixdatetime';

    public function __construct()
    {
        // Nothing extra for now
    }

    public function normalize(mixed $value, $addDefaultValues = false): mixed
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

        return  Shm::structure([
            'gte' => Shm::int()->title('Больше или равно'),
            'eq' => Shm::int()->title('Равно'),
            'lte' => Shm::int()->title('Меньше или равно'),
        ])->fullEditable();
    }



    public function tsType(): TSType
    {
        $TSType = new TSType("Int", "number");



        return $TSType;
    }
}
