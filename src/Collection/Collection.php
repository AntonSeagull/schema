<?php

namespace Shm\Collection;

use Error;
use MongoDB\InsertOneResult;
use MongoDB\UpdateResult;
use Shm\Shm;
use Shm\ShmAdmin\SchemaCollections\SubAccountsSchema;
use Shm\ShmAuth\Auth;
use Shm\ShmTypes\IDsType;
use Shm\ShmTypes\IDType;
use Shm\ShmUtils\Response;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmInit;

class Collection
{


    public static $target = false;

    public static function isTarget(bool | null $target = null): bool
    {
        if ($target === null) {
            return self::$target;
        }

        self::$target = $target;

        return self::$target;
    }

    public function __construct()
    {


        if (!$this->collection) {
            $this->collection = $this->getShortClassName();
        }
    }

    public static function isAuthenticated(): bool
    {
        $_this = new static();

        if (Auth::getAuthCollection() != $_this->collection) {
            return false;
        }

        return true;
    }



    public static function token($_id): string
    {

        return Auth::genToken(self::structure(), $_id);
    }

    public static function isApiKeyAuthenticated(): bool
    {
        $_this = new static();

        if (Auth::getApiKeyCollection() != $_this->collection) {
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


    public static function apiKeyAuthenticateOrThrow()
    {
        $_this = new static();



        if (Auth::getApiKeyCollection() != $_this->collection) {
            Response::unauthorized();
        }
    }



    public $collection;






    public static function cloneSchema(): StructureType | null
    {
        $_this = new static();

        if (method_exists($_this, 'schema')) {
            return $_this->schema();
        }

        return null;
    }




    public static function flatten(): StructureType | null
    {

        $_this = new static();

        return $_this->expectSchema();
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


        return $_this->expectSchema()->expand();
    }




    public function schema(): StructureType | null
    {
        return null;
    }

    public function getShortClassName(): string
    {
        $fullClassName = static::class;
        $parts = explode('\\', $fullClassName);
        $shortName = end($parts);

        return lcfirst($shortName);
    }


    public function prepare(StructureType $schema): StructureType
    {
        return $schema;
    }

    final public function expectSchema(): StructureType | null
    {


        if (!$this->collection) {
            $this->collection = $this->getShortClassName();
        }




        $schema =   $this->schema()
            ->key($this->collection)
            ->collection($this->collection);

        $schema->addUUIDInArray();


        $schema->addFieldIfNotExists("_id", Shm::ID()->editable(false));

        if ($schema->manualSort) {
            $schema->addFieldIfNotExists("_sortWeight", Shm::int()->editable(false));
        }


        $schema->addFieldIfNotExists("created_at", Shm::int()->editable(false));
        $schema->addFieldIfNotExists("updated_at", Shm::int()->editable(false));



        if (Auth::subAccountAuth()) {

            SubAccountsSchema::updateSchema($schema);
        }


        $schema =  $this->prepare($schema);



        return  $schema;
    }



    public static function insertOne($document, array $options = []): InsertOneResult
    {
        return self::structure()->insertOne($document, $options);
    }

    public static function updateMany(array $filter, array $update, array $options = []): UpdateResult
    {
        return self::structure()->updateMany($filter, $update, $options);
    }

    public static function updateOne(array $filter = [], array $update = [], array $options = []): UpdateResult
    {
        return self::structure()->updateOne($filter, $update, $options);
    }

    public static function findOne(array $filter = [], array $options = [])
    {
        return self::structure()->findOne($filter, $options);
    }

    public static function find(array $filter = [], array $options = [])
    {
        return self::structure()->find($filter, $options);
    }

    public static function distinct(string $field, array $filter = [], array $options = []): array
    {
        return self::structure()->distinct($field, $filter, $options);
    }




    public static function create(): Collection
    {
        return new static();
    }

    public static function count(array $filter = []): int
    {
        return self::structure()->count($filter);
    }
}
