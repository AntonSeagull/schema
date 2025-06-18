<?php

namespace Shm\ShmTypes;

use GraphQL\Type\Definition\Type;
use Shm\ShmGQL\ShmGQLCodeGen\TSType;

class SelfRefType extends BaseType
{
    public string $type = 'selfRef';



    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        return  $value;
    }

    /**
     * @var callable|null
     * This is used to define the type of the callable.
     */
    public  $callableType = null;

    public function __construct(callable $type)
    {
        $this->callableType = $type;
    }

    private ?BaseType $resolved = null;
    private bool $resolving = false;

    public function resolveType(): BaseType
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        if ($this->resolving) {
            throw new \RuntimeException("Circular reference detected in SelfRefType.");
        }

        $this->resolved = ($this->callableType)(); // лениво разрешаем ссылку

        return $this->resolved;
    }
}
