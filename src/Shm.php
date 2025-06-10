<?php

namespace Shm;

use Shm\Types\StringType;
use Shm\Types\ArrayOfType;
use Shm\Types\IntType;
use Shm\Types\FloatType;
use Shm\Types\BoolType;
use Shm\Types\BaseType;
use Shm\Types\ColorType;
use Shm\Types\CompositeTypes\FileTypes\FileAnyType;
use Shm\Types\CompositeTypes\FileTypes\FileImageType;
use Shm\Types\CompositeTypes\GeoTypes\GeoPointType;
use Shm\Types\CompositeTypes\GeoTypes\MongoPointType;
use Shm\Types\CompositeTypes\GeoTypes\MonogPointType;
use Shm\Types\StructureType;
use Shm\Types\UnixdateType;
use Shm\Types\EnumType;
use Shm\Types\IDsType;
use Shm\Types\IDType;
use Shm\Types\PhoneType;
use Shm\Types\UnixDateTimeType;
use Shm\Types\Utils\JsonLogicBuilder;

class Shm
{

    public static function structure(array $fields): StructureType
    {
        return new StructureType($fields);
    }

    public static function html(): StringType
    {
        return (new StringType())->type('html');
    }

    public static function text(): StringType
    {
        return (new StringType())->type('text');
    }

    public static function string(): StringType
    {
        return new StringType();
    }

    public static function email(): StringType
    {
        return (new StringType())->type('email');
    }
    public static function password(): StringType
    {
        return (new StringType())->type('password');
    }

    public static function arrayOf(BaseType $itemType): ArrayOfType
    {
        return new ArrayOfType($itemType);
    }

    public static function int(): IntType
    {
        return new IntType();
    }

    public static function integer(): IntType
    {
        return self::int();
    }

    public static function number(): FloatType
    {
        return self::float();
    }


    public static function ID(StructureType | null $document = null): IDType
    {
        return (new IDType($document));
    }

    public static function IDs(StructureType | null $document = null): IDsType
    {
        return (new IDsType($document));
    }


    public static function float(): FloatType
    {
        return new FloatType();
    }

    public static function boolean(): BoolType
    {
        return self::bool();
    }

    public static function bool(): BoolType
    {
        return new BoolType();
    }

    public static function unixdate(): UnixDateType
    {
        return new UnixDateType();
    }

    public static function unixdatetime(): UnixDateTimeType
    {
        return new UnixDateTimeType();
    }

    public static function enum(array $values): EnumType
    {
        return new EnumType($values);
    }

    public static function phone(): PhoneType
    {
        return new PhoneType();
    }

    public static function color(): ColorType
    {
        return new ColorType();
    }


    public static function fileImage(): FileImageType
    {
        return new FileImageType();
    }
    public static function file(): FileAnyType
    {
        return new FileAnyType();
    }

    public static function geoPoint(): GeoPointType
    {
        return new GeoPointType();
    }
    public static function monogoPoint(): MongoPointType
    {
        return new MongoPointType();
    }

    public static function cond(): JsonLogicBuilder
    {
        return new JsonLogicBuilder();
    }

    public static function nonNull(BaseType $type): BaseType
    {
        return $type->nullable(false);
    }
}