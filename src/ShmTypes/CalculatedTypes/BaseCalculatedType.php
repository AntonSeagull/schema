<?php


namespace Shm\ShmTypes\CalculatedTypes;

use Shm\ShmTypes\BaseType;

class BaseCalculatedType extends BaseType
{
    public string $type = 'calculated';

    /** @var callable|null */
    protected $resolver = null;

    public function resolveUsing(callable $resolver): static
    {
        $this->resolver = $resolver;
        return $this;
    }

    public function resolve(mixed $input = null, array $context = []): mixed
    {
        if (!$this->resolver) {
            throw new \LogicException("No resolver defined for calculated field '{$this->key}'");
        }

        return call_user_func($this->resolver, $input, $context);
    }

    public function normalize(mixed $value, $addDefaultValues = false, ?string $processId = null): mixed
    {
        return $value;
    }
}
