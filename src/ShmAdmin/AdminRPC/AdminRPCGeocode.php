<?php

namespace Shm\ShmAdmin\AdminRPC;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Shm\Shm;
use Shm\ShmAdmin\AdminPanel;
use Shm\ShmAuth\Auth;
use Shm\ShmRPC\ShmRPC;
use Shm\ShmUtils\Config;

class AdminRPCGeocode
{
    public static function rpc()
    {

        return ShmRPC::lazy(function () {

            return [
                "type" => Shm::arrayOf(Shm::geoPoint()),
                'args' => [
                    'byCoords' => Shm::structure([
                        'latitude' => Shm::float(),
                        'longitude' => Shm::float(),
                    ]),
                    'byString' => Shm::structure(
                        [
                            'string' => Shm::string(),
                            'latutude' => Shm::float(),
                            'longitude' => Shm::float()
                        ]
                    )

                ],
                'resolve' => function ($root, $args) {

                    Auth::authenticateOrThrow(...AdminPanel::$authStructures);

                    $byCoordsLat = $args['byCoords']['latitude'] ?? null;
                    $byCoordsLon = $args['byCoords']['longitude'] ?? null;

                    $byString = $args['byString']['string'] ?? null;
                    $byStringLat = $args['byString']['latitude'] ?? null;
                    $byStringLon = $args['byString']['longitude'] ?? null;

                    $queryParams = [
                        'apikey' => Config::get("yandexGeocodeKey"),
                        'format' => 'json',
                        'lang' => "ru_RU",
                        'kind' => "house"
                    ];

                    $geocodeSet = false;

                    if ($byCoordsLat && $byCoordsLon) {
                        $queryParams['geocode'] = $byCoordsLon . ',' . $byCoordsLat;
                        $geocodeSet = true;
                    } elseif ($byString) {
                        $queryParams['geocode'] = $byString;
                        $geocodeSet = true;
                        if ($byStringLat && $byStringLon) {
                            $queryParams['ll'] = $byStringLon . ',' . $byStringLat;
                        }
                    }

                    if (!$geocodeSet) return [];

                    $client = new Client();
                    $request = new Request('GET', 'https://geocode-maps.yandex.ru/1.x/?' . http_build_query($queryParams));
                    $res = $client->sendAsync($request)->wait();
                    $body = $res->getBody();

                    $body =  json_decode($body, true);

                    $featureMember = $body['response']['GeoObjectCollection']['featureMember'] ?? null;
                    $result = [];
                    foreach ($featureMember as $val) {


                        $cord = explode(' ', $val['GeoObject']['Point']['pos']);

                        $name = $val['GeoObject']['name'] ?? "";
                        $description = $val['GeoObject']['description'] ?? "";
                        if ($name) {
                            $result[] = [
                                'uuid' => md5($name . $description . $cord[0] . $cord[1]),
                                'name' => $name,
                                'address' => $name,
                                'context' => $description,
                                'lat' => +$cord[1],
                                'lng' => +$cord[0],
                                'meta' => [
                                    'label' => null,
                                    'floor' => null,
                                    'entrance' => null,
                                    'apartment' => null,
                                    'comment' => null,
                                    'phone' => null,
                                ],
                                'location' => [
                                    'type' => 'Point',
                                    'coordinates' => [
                                        +$cord[0],
                                        +$cord[1]
                                    ]
                                ],

                            ];
                        }
                    }

                    return $result;
                },

            ];
        });
    }
}
