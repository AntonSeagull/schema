<?php

namespace Shm;

use Shm\ShmAdmin\Types\VisualGroupType;
use Shm\ShmTypes\StringType;
use Shm\ShmTypes\ArrayOfType;
use Shm\ShmTypes\IntType;
use Shm\ShmTypes\FloatType;
use Shm\ShmTypes\BoolType;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\ColorType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileAnyType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileImageLinkType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileImageType;
use Shm\ShmTypes\CompositeTypes\GeoTypes\GeoPointType;
use Shm\ShmTypes\CompositeTypes\GeoTypes\MongoPointType;
use Shm\ShmTypes\CompositeTypes\GeoTypes\MongoPolygonType;
use Shm\ShmTypes\CompositeTypes\GeoTypes\MonogPointType;
use Shm\ShmTypes\CompositeTypes\RangeUnixDateType;
use Shm\ShmTypes\CompositeTypes\SocialType;
use Shm\ShmTypes\CompositeTypes\TimeType;
use Shm\ShmTypes\StructureType;
use Shm\ShmTypes\UnixDateType;
use Shm\ShmTypes\EnumType;
use Shm\ShmTypes\IDsType;
use Shm\ShmTypes\IDType;
use Shm\ShmTypes\MixedType;
use Shm\ShmTypes\PhoneType;
use Shm\ShmTypes\SelfRefType;
use Shm\ShmTypes\StaticType;
use Shm\ShmTypes\SupportTypes\StageType;
use Shm\ShmTypes\UnixDateTimeType;
use Shm\ShmTypes\Utils\JsonLogicBuilder;
use Shm\ShmTypes\UUIDType;

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

    public static function login(): StringType
    {
        return (new StringType())->type('login');
    }

    public static function password(): StringType
    {
        return (new StringType())->type('password');
    }

    public static function arrayOf(BaseType $itemType): ArrayOfType
    {
        return new ArrayOfType($itemType);
    }

    public static function listOf(BaseType $itemType): ArrayOfType
    {
        return self::arrayOf($itemType);
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

    public static function rangeunixdate(): RangeUnixDateType
    {
        return new RangeUnixDateType();
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

    public static function rate(): FloatType
    {
        return (new FloatType())->type('rate');
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

    public static function fileImageLink(): FileImageLinkType
    {
        return new FileImageLinkType();
    }

    public static function fileImage(): FileImageType
    {
        return new FileImageType();
    }
    public static function file(): FileAnyType
    {
        return new FileAnyType();
    }

    public static function time(): TimeType
    {
        return (new TimeType());
    }

    public static function geoPoint(): GeoPointType
    {
        return new GeoPointType();
    }
    public static function monogoPoint(): MongoPointType
    {
        return new MongoPointType();
    }


    public static function monogoPolygon(): MongoPolygonType
    {
        return new MongoPolygonType();
    }

    public static function social(): SocialType
    {
        return new SocialType();
    }
    public static function cond(): JsonLogicBuilder
    {
        return new JsonLogicBuilder();
    }

    public static function uuid(): UUIDType
    {
        return new UUIDType();
    }

    public static function selfRef(callable $type): SelfRefType
    {
        return new SelfRefType($type);
    }


    public static function mixed(): MixedType
    {
        return new MixedType();
    }

    public static function nonNull(BaseType $type): BaseType
    {
        return $type->nullable(false);
    }

    public static function fragment(StructureType $type,  string $key): ?BaseType
    {
        return $type->findItemByKey($key);
    }

    public static function visualGroup(array $fields): VisualGroupType
    {
        return new VisualGroupType($fields);
    }

    public static function stage(): StageType
    {
        return new StageType();
    }

    public static function static(mixed $staticValue): StaticType
    {
        return new StaticType($staticValue);
    }
}
