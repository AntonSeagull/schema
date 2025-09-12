<?php

namespace Shm\ShmBlueprints\Geocoding;

class ShmGeocoding
{





    public function yandex(string $apikey): ShmGeocodingYandex
    {

        return new ShmGeocodingYandex($apikey);
    }
}
