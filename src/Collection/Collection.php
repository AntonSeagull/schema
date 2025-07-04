<?php

namespace Shm\Collection;

use Error;
use Shm\Shm;
use Shm\ShmAuth\Auth;
use Shm\ShmTypes\IDsType;
use Shm\ShmTypes\IDType;
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


    public static $flattenCache = [];

    public static function flatten(): StructureType | null
    {

        $_this = new static();

        if (isset(self::$flattenCache[$_this->collection])) {
            return self::$flattenCache[$_this->collection];
        }


        if (method_exists($_this, 'expect')) {
            return self::$flattenCache[$_this->collection] = $_this->expect()->baseTypePrefix("Flat");
        }

        return null;
    }


    public static function ID(): IDType
    {

        return Shm::ID(fn() => self::flatten());
    }

    public static function IDs(): IDsType
    {

        return Shm::IDs(fn() => self::flatten());
    }


    public static $structureCache = [];


    public static function structure(): StructureType
    {

        $_this = new static();

        if (isset(self::$structureCache[$_this->collection])) {
            return self::$structureCache[$_this->collection];
        }


        if (method_exists($_this, 'expect')) {
            return self::$structureCache[$_this->collection] = $_this->expect()->stripNestedIds();
        }


        return Shm::structure([]);
    }




    public function schema(): StructureType | null
    {
        return null;
    }

    final public function expect(): StructureType | null
    {


        $schema =  $this->schema()
            ->key($this->collection)
            ->collection($this->collection)
            ->pipeline($this->basePipeline() ?? []);

        $schema->addField("_id", Shm::ID());
        $schema->addField("_sortWeight", Shm::int());
        $schema->addField("created_at", Shm::int());
        $schema->addField("updated_at", Shm::int());


        return $schema;
    }



    public static function create(): static
    {
        return new static();
    }
}
