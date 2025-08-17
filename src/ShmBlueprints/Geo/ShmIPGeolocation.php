<?php

namespace Shm\ShmBlueprints\Geo;

use GeoIp2\Database\Reader;
use Shm\Shm;
use Shm\ShmUtils\ShmInit;

class ShmIPGeolocation
{



    public static function rpc()
    {


        return [


            'type' => Shm::structure([
                'latitude' => Shm::float(),
                'longitude' => Shm::float(),
            ]),
            'args' => [
                'ip' => Shm::string()
            ],
            'resolve' => function ($root, $args) {

                $currentIp = $args['ip'] ??  $_SERVER['REMOTE_ADDR'] ?? null;

                if (explode('.', $currentIp) !== 4) {
                    return null;
                }

                if (!$currentIp) return null;



                $cityDbReader = new Reader(ShmInit::$shmDir . '/../assets/geo/GeoLite-City.mmdb');

                $record = $cityDbReader->city($currentIp);
                $latitude = $record->location->latitude;
                $longitude = $record->location->longitude;

                return [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];
            }


        ];
    }
}
