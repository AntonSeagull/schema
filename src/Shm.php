<?php

namespace Shm;


use Shm\ShmAdmin\Types\VisualGroupType;
use Shm\ShmCmd\Cmd;
use Shm\ShmTypes\DashboardType;
use Shm\ShmTypes\StringType;
use Shm\ShmTypes\ArrayOfType;
use Shm\ShmTypes\IntType;
use Shm\ShmTypes\FloatType;
use Shm\ShmTypes\BoolType;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\ColorType;
use Shm\ShmTypes\CompositeTypes\ActionType;
use Shm\ShmTypes\CompositeTypes\BalanceTypes\BalanceType;
use Shm\ShmTypes\CompositeTypes\CodeType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileAnyType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileAudioLinkType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileAudioType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileDocumentType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileIDType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileImageLinkType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileImageType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileDocumentLinkType;
use Shm\ShmTypes\CompositeTypes\FileTypes\FileVideoType;
use Shm\ShmTypes\CompositeTypes\GeoTypes\GeoPointType;
use Shm\ShmTypes\CompositeTypes\GeoTypes\MongoPointType;
use Shm\ShmTypes\CompositeTypes\GeoTypes\MongoPolygonType;
use Shm\ShmTypes\CompositeTypes\GeoTypes\MonogPointType;
use Shm\ShmTypes\CompositeTypes\GradientType;
use Shm\ShmTypes\CompositeTypes\RangeType;

use Shm\ShmTypes\CompositeTypes\SocialType;
use Shm\ShmTypes\CompositeTypes\TimeType;

use Shm\ShmTypes\StructureType;
use Shm\ShmTypes\UnixDateType;
use Shm\ShmTypes\EnumType;
use Shm\ShmTypes\IDsType;
use Shm\ShmTypes\IDType;
use Shm\ShmTypes\MixedType;
use Shm\ShmTypes\PasswordType;
use Shm\ShmTypes\PhoneType;
use Shm\ShmTypes\SelfRefType;
use Shm\ShmTypes\StaticType;


use Shm\ShmTypes\UnixDateTimeType;
use Shm\ShmTypes\Utils\JsonLogicBuilder;
use Shm\ShmTypes\UUIDType;
use Shm\ShmUtils\Response;

class Shm
{


    public static function json(): StructureType
    {
        return Shm::structure([
            '*' => Shm::mixed()
        ])->type('json');
    }

    public static function structure(array $fields): StructureType
    {
        return new StructureType($fields);
    }

    public static function html(): StringType
    {
        return (new StringType())->type('html');
    }

    public static function url(): StringType
    {
        return (new StringType())->type('url');
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
        return (new StringType())->type('email')->globalUnique()->lowercase();
    }

    public static function login(): StringType
    {
        return (new StringType())->type('login')->globalUnique()->lowercase();
    }

    public static function password(): PasswordType
    {
        return (new PasswordType())->private();
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

    public static function range(BaseType $type): RangeType
    {
        return new RangeType($type);
    }



    public static function ID(callable  | StructureType $documentResolver = null, string $collection = null): IDType
    {



        return (new IDType($documentResolver, $collection));
    }

    public static function IDs(callable | StructureType $documentResolver = null, string $collection = null): IDsType
    {



        return (new IDsType($documentResolver, $collection));
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

    public static function code(string | null $codeLang = null): CodeType
    {
        return (new CodeType())->codeLanguage($codeLang ?? 'js');
    }

    public static function bool(): BoolType
    {
        return new BoolType();
    }

    public static function unixdate(): UnixDateType
    {
        return new UnixDateType();
    }

    public static function timestamp(): UnixDateTimeType
    {
        return new UnixDateTimeType();
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



    public static function fileLink(): FileDocumentLinkType
    {
        return new FileDocumentLinkType();
    }

    public static function fileImageLink(): FileImageLinkType
    {
        return new FileImageLinkType();
    }

    public static function fileAudioLink(): FileAudioLinkType
    {
        return new FileAudioLinkType();
    }

    public static function fileImage(): FileImageType
    {
        return new FileImageType();
    }

    public static function fileIDImage(): FileIDType
    {
        return new FileIDType('image');
    }


    public static function fileAudio(): FileAudioType
    {
        return new FileAudioType();
    }

    public static function fileIDAudio(): FileIDType
    {
        return new FileIDType('audio');
    }


    public static function fileVideo(): FileVideoType
    {
        return new FileVideoType();
    }

    public static function fileIDVideo(): FileIDType
    {
        return new FileIDType('video');
    }

    public static function fileDocument(): FileDocumentType
    {
        return new FileDocumentType();
    }

    public static function fileIDDocument(): FileIDType
    {
        return new FileIDType('document');
    }

    public static function time(): TimeType
    {
        return (new TimeType());
    }

    public static function geoPoint(): GeoPointType
    {
        return new GeoPointType();
    }
    public static function mongoPoint(): MongoPointType
    {
        return new MongoPointType();
    }


    public static function mongoPolygon(): MongoPolygonType
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


    public static function action(): ActionType
    {
        return new ActionType();
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


    //@deprecated
    //Use Shm::mongoPolygon() instead
    public static function geoRegion(): ArrayOfType
    {
        return  Shm::arrayOf(Shm::structure([
            "geometry" => Shm::mongoPolygon()
        ]))->type('geoRegion');
    }



    public static function gradient(): GradientType
    {
        return new GradientType();
    }




    public static function static(mixed $staticValue): StaticType
    {
        return new StaticType($staticValue);
    }


    public static function error($message)
    {

        if (Cmd::cli()) {

            echo '🛑 ' . $message . "\n";
            exit;
        }

        Response::validation($message);
    }

    public static function unauthorizedError()
    {
        Response::unauthorized();
    }


    public static function dashboard(array $fields = []): DashboardType
    {
        return new DashboardType($fields);
    }

    public static function balance(string $currency): BalanceType
    {
        return new BalanceType($currency);
    }
}
