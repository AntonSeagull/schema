<?php

namespace Shm\ShmTypes;

use Shm\Shm;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\BaseType;

/**
 * Computed type for schema definitions
 * 
 * This class represents a computed type that calculates its value
 * based on a resolver function and arguments.
 */
class ComputedType extends BaseType
{
    public string $type = 'computed';
    public mixed $computedResolve;
    public mixed $computedArgs;
    public BaseType $computedReturnType;

    /**
     * ComputedType constructor
     *
     * @param array{
     *     resolve: callable,           // Function that computes the value: function ($root, $args)
     *     args?: array|BaseType|null, // Arguments for computation
     *     type: BaseType              // Expected return type
     * } $computedParams Computed type parameters
     *
     * @throws \Exception If parameters are invalid
     */
    public function __construct($computedParams)
    {
        if (!is_array($computedParams)) {
            throw new \Exception('Parameter $computedParams must be an array.');
        }

        if (!isset($computedParams['resolve']) || !is_callable($computedParams['resolve'])) {
            throw new \Exception('Missing or invalid "resolve" key in $computedParams. It must be a callable.');
        }

        if (!isset($computedParams['type']) || !$computedParams['type'] instanceof BaseType) {
            throw new \Exception('Missing or invalid "type" key in $computedParams. It must be an instance of BaseType.');
        }

        if (isset($computedParams['args']) && !$computedParams['args'] instanceof BaseType) {
            $computedParams['args'] = Shm::structure($computedParams['args']);
        }

        $this->computedResolve = $computedParams['resolve'];
        $this->computedArgs = $computedParams['args'] ?? null;

        if ($this->computedArgs) {
            $this->computedArgs->editable()->inAdmin();
        }

        $this->computedReturnType = $computedParams['type'];
    }

    /**
     * Выполняет вычисление значения с помощью заданного resolve-коллбэка.
     * @return mixed Результат вычисления
     */
    public function computed($params)
    {

        if ($this->computedArgs) {
            $params = $this->computedArgs->normalize($params);
        }

        $result = call_user_func($this->computedResolve, [
            'root' => $this->computedReturnType,
            'args' => $this->computedArgs
        ], $params);



        $result = $this->computedReturnType->normalize($result, false);

        $result = $this->computedReturnType->removeOtherItems($result);

        return $result;
    }

    public function getSearchPaths(): array
    {
        return [];
    }
}
