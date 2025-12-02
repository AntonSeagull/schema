<?php

namespace Shm\ShmGeo\GridMap;

use Shm\ShmCmd\Cmd;
use Shm\ShmDB\mDB;

class GridMap
{
    const EARTH_RADIUS = 6378137;
    const CELL_SIZE = 1000; // 1 км клетка

    const COLLECTION = '_gridMapCols';
    // --- ПРОЕКЦИЯ ---
    public static function latLonToMeters($lat, $lon)
    {
        $originShift = 2 * pi() * self::EARTH_RADIUS / 2.0;

        $mx = $lon * $originShift / 180.0;
        $my = log(tan((90 + $lat) * pi() / 360.0)) * self::EARTH_RADIUS;

        return [$mx, $my];
    }

    public static function metersToLatLon($mx, $my)
    {
        $lon = ($mx / self::EARTH_RADIUS) * 180.0 / pi();
        $lat = (2 * atan(exp($my / self::EARTH_RADIUS)) - pi() / 2) * 180.0 / pi();

        return [$lat, $lon];
    }

    // --- ИНДЕКСЫ ---
    public static function getCellIndex($lat, $lon)
    {
        list($mx, $my) = self::latLonToMeters($lat, $lon);

        return [
            floor($mx / self::CELL_SIZE),
            floor($my / self::CELL_SIZE)
        ];
    }

    // --- ГЕНЕРАЦИЯ КЛЕТКИ ПО ИНДЕКСАМ ---
    public static function buildCellPolygon(int $kx, int $ky): array
    {
        $mx1 = $kx * self::CELL_SIZE;
        $my1 = $ky * self::CELL_SIZE;
        $mx2 = $mx1 + self::CELL_SIZE;
        $my2 = $my1 + self::CELL_SIZE;

        // угол A
        list($latA, $lonA) = self::metersToLatLon($mx1, $my1);
        // угол B
        list($latB, $lonB) = self::metersToLatLon($mx2, $my1);
        // угол C
        list($latC, $lonC) = self::metersToLatLon($mx2, $my2);
        // угол D
        list($latD, $lonD) = self::metersToLatLon($mx1, $my2);

        return [
            'key' => "{$kx}_{$ky}_" . self::CELL_SIZE,
            'properties' => [
                'kx' => $kx,
                'ky' => $ky,
                'cell' => self::CELL_SIZE,
            ],
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [$lonA, $latA],
                    [$lonB, $latB],
                    [$lonC, $latC],
                    [$lonD, $latD],
                    [$lonA, $latA]
                ]]
            ]
        ];
    }

    public static function ensureGridIndexes()
    {
        $collection = mDB::_collection(self::COLLECTION);

        // Какие индексы должны быть
        $required = [
            'properties.kx' => 1,
            'properties.ky' => 1,
            'key'           => 1
        ];

        // Существующие индексы
        $existing = [];
        foreach ($collection->listIndexes() as $idx) {
            foreach ($idx['key'] as $field => $value) {
                $existing[$field] = $value;
            }
        }

        // Создаём недостающие индексы
        foreach ($required as $field => $type) {
            if (!isset($existing[$field])) {
                $collection->createIndex([$field => $type]);
                echo "Created index: {$field} => {$type}\n";
            }
        }

        // Композитный индекс kx + ky
        $hasComposite = false;
        foreach ($collection->listIndexes() as $idx) {
            if (isset($idx['key']['properties.kx']) && isset($idx['key']['properties.ky'])) {
                $hasComposite = true;
                break;
            }
        }

        if (!$hasComposite) {
            $collection->createIndex([
                'properties.kx' => 1,
                'properties.ky' => 1
            ]);
            echo "Created index: properties.kx + properties.ky\n";
        }
    }
    // --- ОСНОВНАЯ ФУНКЦИЯ ---
    public static function getCellsByPoint(float $lat, float $lon, int $radiusKm): array
    {
        // 1. Центр в метрах
        list($mx0, $my0) = self::latLonToMeters($lat, $lon);

        // 2. Индекс центра
        list($kx0, $ky0) = self::getCellIndex($lat, $lon);

        // Радиус в клетках
        $R = ceil($radiusKm);

        // 3. Список всех нужных клеток
        $needed = [];
        for ($kx = $kx0 - $R; $kx <= $kx0 + $R; $kx++) {
            for ($ky = $ky0 - $R; $ky <= $ky0 + $R; $ky++) {
                $needed[] = [$kx, $ky];
            }
        }

        // 4. Чтение существующих из Mongo
        $cursor = mDB::_collection(self::COLLECTION)->find([
            "properties.kx" => ['$gte' => $kx0 - $R, '$lte' => $kx0 + $R],
            "properties.ky" => ['$gte' => $ky0 - $R, '$lte' => $ky0 + $R]
        ]);

        $existing = [];
        foreach ($cursor as $doc) {
            $existing[$doc['properties']['kx'] . "_" . $doc['properties']['ky']] = $doc['_id'];
        }

        // 5. Находим отсутствующие клетки
        $toCreate = [];
        foreach ($needed as [$kx, $ky]) {
            $key = "{$kx}_{$ky}";
            if (!isset($existing[$key])) {
                $toCreate[] = [$kx, $ky];
            }
        }

        // 6. Генерируем отсутствующие
        $newCells = [];
        foreach ($toCreate as [$kx, $ky]) {
            $newCells[] = self::buildCellPolygon($kx, $ky);
        }

        // 7. Вставляем в Mongo (если есть что вставлять)
        if (!empty($newCells)) {
            $result = mDB::_collection(self::COLLECTION)->insertMany($newCells);
            $insertedIds = $result->getInsertedIds(); // Array of ObjectId

            foreach ($newCells as $i => $cell) {
                $kx = $cell['properties']['kx'];
                $ky = $cell['properties']['ky'];

                $existing["{$kx}_{$ky}"] = $insertedIds[$i];
            }
        }

        // 8. Фильтрация по реальной дистанции (если R > 3)
        $radiusM = $radiusKm * 1000;
        $result = [];

        foreach ($needed as [$kx, $ky]) {
            $key = "{$kx}_{$ky}";
            $id = $existing[$key] ?? null;
            if (!$id) continue;

            if ($radiusKm >= 3) {
                $mx = ($kx + 0.5) * self::CELL_SIZE;
                $my = ($ky + 0.5) * self::CELL_SIZE;

                $dist = sqrt(($mx - $mx0) ** 2 + ($my - $my0) ** 2);

                if ($dist <= $radiusM) {
                    $result[] = $id;
                }
            } else {
                $result[] = $id;
            }
        }

        return $result;
    }

    public static function ensureCellsInRadius(float $lat, float $lon, int $radiusKm)
    {

        //Убираем лимит памяти
        ini_set('memory_limit', '-1');

        self::ensureGridIndexes();

        // 1. Индекс центра
        list($kx0, $ky0) = self::getCellIndex($lat, $lon);

        // Радиус в клетках
        $R = ceil($radiusKm);

        // 3. Список всех нужных клеток
        $needed = [];
        for ($kx = $kx0 - $R; $kx <= $kx0 + $R; $kx++) {
            for ($ky = $ky0 - $R; $ky <= $ky0 + $R; $ky++) {
                $needed[] = [$kx, $ky];
            }
        }

        // 4. Читаем существующие клетки
        $cursor = mDB::_collection(self::COLLECTION)->find([
            "properties.kx" => ['$gte' => $kx0 - $R, '$lte' => $kx0 + $R],
            "properties.ky" => ['$gte' => $ky0 - $R, '$lte' => $ky0 + $R]
        ]);

        $existing = [];
        foreach ($cursor as $doc) {
            $existing[$doc['properties']['kx'] . "_" . $doc['properties']['ky']] = true;
        }

        // 5. Находим отсутствующие
        $toCreate = [];
        foreach ($needed as [$kx, $ky]) {
            $key = "{$kx}_{$ky}";
            if (!isset($existing[$key])) {
                $toCreate[] = [$kx, $ky];
            }
        }

        // 6. Генерируем отсутствующие
        $newCells = [];
        foreach ($toCreate as [$kx, $ky]) {
            $newCells[] = self::buildCellPolygon($kx, $ky);
        }

        // 7. Вставляем одним большим куском
        if (!empty($newCells)) {
            mDB::_collection(self::COLLECTION)->insertMany($newCells);
        }

        if (Cmd::cli()) {
            echo 'totalNeeded: ' . count($needed) . ', existing: ' . count($existing) . ', created: ' . count($newCells);
        }
    }
}