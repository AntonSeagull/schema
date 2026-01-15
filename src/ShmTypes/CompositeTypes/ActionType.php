<?php

namespace Shm\ShmTypes\CompositeTypes;

use Shm\Shm;
use Shm\ShmDB\mDB;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;

class ActionType extends BaseType
{
    public string $type = 'action';


    public StructureType | null $args = null;
    /**
     * @var callable | null
     */
    public $resolve = null;


    public function setArgs(StructureType $args): static
    {

        $args->inAdmin()->editable();


        $this->args = $args;
        return $this;
    }



    public $actionPosition = 'sidebar';

    public function inSidebarAction(): static
    {
        $this->actionPosition = 'sidebar';
        return $this;
    }


    public function inInlineAction(): static
    {
        $this->actionPosition = 'inline';
        return $this;
    }

    public function inTableAction(): static
    {
        $this->actionPosition = 'table';
        return $this;
    }


    public function setResolve(callable | null $resolve = null): static
    {


        if ($resolve && !is_callable($resolve)) {
            throw new \Exception("Resolve must be a callable function.");
        }


        $this->resolve = $resolve;
        return $this;
    }

    public function callResolve(mixed $root, mixed $args): mixed
    {

        if (!$this->resolve) {
            throw new \Exception("Resolve is not set.");
        }


        if ($this->actionPosition !== 'table') {

            $ids = $args['args']['ids'] ?? null;

            if (!$ids || count($ids) === 0) {
                ShmRPC::error("Не возможно выполнить действие!");
            }

            $ids = array_map(fn($id) => mDB::id($id), $ids);
            $args['args']['ids'] = $ids;
        }


        return call_user_func($this->resolve, $root, $args['args'] ?? []);
    }
}