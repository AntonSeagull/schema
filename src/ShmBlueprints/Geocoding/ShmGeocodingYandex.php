<?php

namespace Shm\ShmBlueprints\Geocoding;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Shm\Shm;
use Shm\ShmDB\mDB;

class ShmGeocodingYandex
{

    private $apikey;

    public function __construct(string $apikey)
    {
        $this->apikey = $apikey;
    }


    public function make()
    {

        return  [

            "type" => Shm::arrayOf(Shm::structure([
                'id' => Shm::string(),
                'name' => Shm::string(),
                'description' => Shm::string(),
                'coordinates' => Shm::structure([
                    'latitude' => Shm::float(),
                    'longitude' => Shm::float(),

                ]),

            ])),
            'args' => [
                'params' => Shm::structure([
                    'lang' => Shm::enum([
                        'ru_RU',
                        'uk_UA',
                        'be_BY',
                        'en_RU',
                        'en_US',
                        'tr_TR'
                    ])->default('ru_RU'),
                    'kind' => Shm::enum([
                        'house' => 'Улица и дом',
                        'street' => 'Улица',
                        'metro' => 'Метро',
                        'district' => 'Район города',
                        'locality' => 'Населенный пункт (город/поселок/деревня/село)',
                    ])->default('house'),
                    'results' => Shm::int()->default(5),
                    'skip' => Shm::int()->default(0),
                ]),
                'byCoords' => Shm::structure([
                    'latitude' => Shm::float(),
                    'longitude' => Shm::float(),
                ]),
                'byString' => Shm::structure(
                    [
                        'string' => Shm::string(),
                        'latitude' => Shm::float(),
                        'longitude' => Shm::float()
                    ]
                )

            ],
            'resolve' => function ($root, $args) {


                $byCoordsLat = $args['byCoords']['latitude'] ?? null;
                $byCoordsLon = $args['byCoords']['longitude'] ?? null;

                $byString = $args['byString']['string'] ?? null;
                $byStringLat = $args['byString']['latitude'] ?? null;
                $byStringLon = $args['byString']['longitude'] ?? null;

                $params = $args['params'] ?? [];

                $queryParams = [
                    'apikey' => $this->apikey,
                    'format' => 'json',
                    'lang' =>  $params['lang'] ?? "ru_RU",
                    'kind' => $params['kind'] ?? "house",
                    'results' => $params['results'] ?? 5,
                    'skip' => $params['skip'] ?? 0,
                ];

                $geocodeSeted = false;

                if ($byCoordsLat && $byCoordsLon) {
                    $queryParams['geocode'] = $byCoordsLon . ',' . $byCoordsLat;
                    $geocodeSeted = true;
                } elseif ($byString) {
                    $queryParams['geocode'] = $byString;
                    $geocodeSeted = true;
                    if ($byStringLat && $byStringLon) {
                        $queryParams['ll'] = $byStringLon . ',' . $byStringLat;
                    }
                }

                if (!$geocodeSeted) return [];

                $client = new Client();
                $request = new Request('GET', 'https://geocode-maps.yandex.ru/1.x/?' . http_build_query($queryParams));
                $res = $client->sendAsync($request)->wait();
                $body = $res->getBody();

                $body =  json_decode($body, true);

                $featureMember = $body['response']['GeoObjectCollection']['featureMember'] ?? null;
                $result = [];

                $updateData = [];

                foreach ($featureMember as $val) {


                    $cord = explode(' ', $val['GeoObject']['Point']['pos']);

                    $name = $val['GeoObject']['name'] ?? "";
                    $description = $val['GeoObject']['description'] ?? "";
                    if ($name) {

                        $resultItem =  [
                            'id' => md5($name . $description . $cord[0] . $cord[1]),
                            'name' => $name,
                            'description' => $description,
                            'coordinates' => [
                                "longitude" =>  +$cord[0],
                                "latitude" =>   +$cord[1]
                            ],
                            'provider' => 'yandex',
                        ];
                        $result[] = $resultItem;


                        $updateData[] = [
                            'updateOne' => [
                                [
                                    "key" => $resultItem['id'],
                                    'provider' => 'yandex'

                                ],
                                [
                                    '$set' => $resultItem
                                ],
                                [
                                    'upsert' => true,
                                ],
                            ],
                        ];
                    }
                }

                if (count($updateData) > 0) {
                    mDB::collection('_geocodingCache')->bulkWrite($updateData);
                }

                return $result;
            },

        ];
    }
}
