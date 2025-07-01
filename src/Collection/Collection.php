<?php

namespace Shm\Collection;

use Error;
use Shm\ShmAuth\Auth;
use Shm\ShmUtils\Response;
use Shm\ShmTypes\StructureType;

class Collection
{



    public static function isAuthenticated(): bool
    {
        $_this = new static();

        if (Auth::getAuthCollection() != $_this->collection) {
            return false;
        }

        return true;
    }

    public static function authenticateOrThrow()
    {
        $_this = new static();

        if (Auth::getAuthCollection() != $_this->collection) {
            Response::unauthorized();
        }
    }


    public $key;
    public $collection;


    public function initialValues(): array
    {
        return [];
    }


    public function basePipeline(): array
    {

        return [];
    }


    public function __construct()
    {

        if (!isset($this->collection) || empty($this->collection)) {

            new Error("Collection class must have a 'collection' property defined.");
        }
    }


    public static function cloneSchema(): StructureType | null
    {
        $_this = new static();

        if (method_exists($_this, 'schema')) {
            return $_this->schema();
        }

        return null;
    }



    private static $cachedStructure = null;

    public static function structure(): StructureType | null
    {

        if (self::$cachedStructure) {
            return self::$cachedStructure;
        }

        $_this = new static();

        if (method_exists($_this, 'expect')) {
            self::$cachedStructure = $_this->expect();
        }

        return self::$cachedStructure;
    }


    public function schema(): StructureType | null
    {
        return null;
    }

    final public function expect(): StructureType | null
    {
        return $this->schema()
            ->key($this->collection)
            ->collection($this->collection)
            ->pipeline($this->basePipeline() ?? []);
    }



    public static function create(): static
    {
        return new static();
    }
}
