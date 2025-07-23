<?php

namespace Shm\ShmTypes;

use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\BaseType;

class ComputedType extends BaseType
{
    public string $type = 'computed';

    /**
     * Функция-резолвер, вызываемая при вычислении значения
     * @var callable
     */
    public $computedResolve;

    /**
     * Аргументы, необходимые для вычисления
     * @var array|BaseType|null
     */
    public $computedArgs;

    /**
     * Ожидаемый тип возвращаемого значения
     * @var BaseType
     */
    public BaseType $computedReturnType;

    /**
     * ComputedType constructor.
     *
     * @param array{
     *     resolve: callable,           // Функция, вычисляющая значение: function ($root, $args)
     *     args?: array|BaseType|null, // Аргументы для вычисления
     *     type: BaseType              // Ожидаемый тип возвращаемого значения
     * } $computedParams Параметры вычисляемого типа
     *
     * @throws \Exception Если параметры некорректны
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
            throw new \Exception('"args" must be an instance of BaseType or null.');
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
