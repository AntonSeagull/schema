<?php

namespace Shm\ShmTypes\CompositeTypes\GeoTypes;

use GraphQL\Type\Definition\ObjectType;

use Shm\CachedType\CachedInputObjectType;
use Shm\CachedType\CachedObjectType;
use Shm\Shm;
use Shm\ShmGeo\GridMap\GridMap;
use Shm\ShmTypes\BaseType;
use Shm\ShmTypes\StructureType;
use Shm\ShmUtils\ShmUtils;

class GeoPointType extends StructureType
{
    public string $type = 'geopoint';

    protected StructureType $fields;

    public bool $compositeType = true;



    public function __construct()
    {


        parent::__construct(
            [
                'uuid' => Shm::UUID(),
                'address' => Shm::string()->deprecated('Use "name" instead'), //Устарвевшее поле, оставлено для совместимости, используйте 'name'
                'name' => Shm::string(),    // Полный адрес, если известен
                'context' => Shm::string(),      // Контекст адреса, общее описание, страна, город и т. д.
                'meta' => Shm::structure([
                    'label' => Shm::string(),        // человеко-понятная подпись, например "Главный офис", "Склад №2"
                    'floor' => Shm::string(),          // этаж ("3", "цоколь", "мансарда")
                    'entrance' => Shm::string(),       // описание входа ("через арку", "с торца здания" или подъезд №3)
                    'apartment' => Shm::string(),      // квартира / офис / помещение
                    'comment' => Shm::string(),        // произвольное примечание
                    'phone' => Shm::phone(),           // контактный телефон
                ]),
                'grid' => Shm::ID(),
                'lat' => Shm::float(),
                'lng' => Shm::float(),
                'location' => Shm::mongoPoint(),
            ]
        );
    }

    public function exportRow(mixed $value): string | array | null
    {

        $name = $value['name'] ?? $value['address'] ?? null;




        if (!$name) {
            return "";
        }

        $label = $value['meta']['label'] ?? null;
        $floor = $value['meta']['floor'] ?? null;
        $entrance = $value['meta']['entrance'] ?? null;
        $apartment = $value['meta']['apartment'] ?? null;
        $comment = $value['meta']['comment'] ?? null;
        $phone = $value['meta']['phone'] ?? null;


        $result = [$name];

        if ($label) {
            $result[] = $label;
        }

        if ($floor) {
            $result[] = $floor;
        }

        if ($entrance) {
            $result[] = $entrance;
        }

        if ($apartment) {
            $result[] = $apartment;
        }

        if ($comment) {
            $result[] = $comment;
        }

        if ($phone) {
            $result[] = $phone;
        }


        return implode(", ", $result);
    }

    public function equals(mixed $a, mixed $b): bool
    {

        $nameA = $a['name'] ?? null;
        $latA = $a['lat'] ?? null;
        $lngA = $a['lng'] ?? null;

        $nameB = $b['name'] ?? null;
        $latB = $b['lat'] ?? null;
        $lngB = $b['lng'] ?? null;


        return $nameA === $nameB && $latA === $latB && $lngA === $lngB;
    }

    public function normalize(mixed $value, $addDefaultValues = false, string | null $processId = null): mixed
    {

        $lat = $value['lat'] ?? null;
        $lng = $value['lng'] ?? null;

        if (!$lat && !$lng) {
            return null;
        }


        $value = parent::normalize($value, $addDefaultValues, $processId);


        if ($value) {

            //Установка полей address и name для совместимости
            if (empty($value['name']) && !empty($value['address'])) {
                $value['name'] = $value['address'];
            }
            if (empty($value['address']) && !empty($value['name'])) {
                $value['address'] = $value['name'];
            }


            $lat = $value['lat'] ?? null;
            $lng = $value['lng'] ?? null;

            if ($lat && $lng) {

                $value['grid'] = GridMap::getCellsByPoint($lat, $lng, 0)[0] ?? null;

                $value['location'] = [
                    'type' => 'Point',
                    'coordinates' => [$lng, $lat]
                ];
            } else {
                $value['location'] = null;
            }
        }

        return $value;
    }

    public function validate(mixed $value): void
    {
        parent::validate($value);
        if ($value === null) {
            return;
        }
        if (!is_array($value)) {
            $field = $this->title ?? 'Value';
            throw new \Exception("{$field} must be an object/structure (associative array).");
        }
        foreach ($this->items as $name => $type) {
            try {
                $type->validate($value[$name] ?? null);
            } catch (\Exception $e) {
                $field = $this->title ?? $name;
                throw new \Exception("{$field}.{$name}: " . $e->getMessage());
            }
        }
    }




    public function baseTypeName()
    {
        return  ShmUtils::onlyLetters($this->type);
    }
}
