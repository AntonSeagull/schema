<?php

namespace Shm\ShmUtils;


use Predis\Client;
use Predis\Connection\ConnectionException;
use Predis\Response\ServerException;
use Shm\ShmCmd\Cmd;

class ShmMetrics
{
    /** @var Client|null */
    private static $client = null;

    private const KEY_PREFIX = 'metrics:redis:rps:'; // ключи вида metrics:redis:rps:<unix_ts>
    private const BUCKET_TTL = 70; // живут чуть больше минуты

    public static function init(): void
    {
        // Команда: вывести график RPS за последнюю минуту (по секундам)
        Cmd::command("rps", function () {
            $series = ShmMetrics::getSeriesLastMinute(); // [ts => count] 60 точек
            $spark  = ShmMetrics::renderSparkline($series, true);

            $values = array_values($series);
            $now    = end($values) ?: 0;
            $max    = max($values ?: [0]);
            $avg    = $values ? array_sum($values) / count($values) : 0;

            $tsList = array_keys($series);
            $fromTs = reset($tsList) ?: time() - 59;
            $toTs   = end($tsList) ?: time();

            echo "RPS (last 60s)  [" . date('H:i:s', $fromTs) . " — " . date('H:i:s', $toTs) . "]\n";
            echo $spark . "\n";
            echo "now: {$now} | max: {$max} | avg: " . number_format($avg, 2) . "\n";
        });

        self::mark(); // каждое обращение к init считаем
    }

    private static function client()
    {
        if (self::$client instanceof Client) {
            return self::$client;
        }

        try {
            self::$client = new Client([
                'scheme'  => 'tcp',
                'host'    => Config::get('redis.host', '127.0.0.1'),
                'port'    => Config::get('redis.port', 6379),
                'timeout' => Config::get('redis.timeout', 0.5),
            ]);
        } catch (ConnectionException | ServerException $e) {
            self::$client = null;
        }

        return self::$client;
    }

    /** Инкремент бакета текущей секунды */
    private static function mark(): void
    {
        $client = self::client();
        if (!$client instanceof Client) {
            return;
        }

        $ts = time();
        $key = self::KEY_PREFIX . $ts;

        try {
            $client->pipeline(function ($pipe) use ($key) {
                $pipe->incr($key);
                $pipe->expire($key, self::BUCKET_TTL);
            });
        } catch (\Throwable $e) {
            // тихо игнорируем
        }
    }

    public static function getSeriesLastMinute(): array
    {
        $client = self::client();
        $now = time();

        // 1) Ключи строго ОТ СТАРЫХ К НОВЫМ: now-59, now-58, ..., now-0
        $keys = [];
        for ($i = 59; $i >= 0; $i--) {
            $keys[] = self::KEY_PREFIX . ($now - $i);
        }

        // Если редис недоступен — вернём нули
        if (!$client instanceof Client) {
            $out = [];
            for ($i = 59; $i >= 0; $i--) {
                $out[$now - $i] = 0;
            }
            return $out;
        }

        try {
            $values = $client->mget($keys); // порядок значений = порядок ключей
            $result = [];

            // 2) Складываем в том же порядке: первый = now-59 ... последний = now
            for ($i = 0; $i < 60; $i++) {
                $ts = $now - (59 - $i); // эквивалентно: $now - 59 + $i
                $val = $values[$i] ?? null;
                $result[$ts] = $val !== null ? (int)$val : 0;
            }

            return $result;
        } catch (\Throwable $e) {
            // Фоллбек — нули
            $out = [];
            for ($i = 59; $i >= 0; $i--) {
                $out[$now - $i] = 0;
            }
            return $out;
        }
    }

    private static function renderSparkline(array $series, bool $color = true): string
    {
        $levels = ['▁', '▂', '▃', '▄', '▅', '▆', '▇', '█']; // 8 уровней

        $vals   = array_values($series);
        $tsList = array_keys($series);
        $n      = count($vals);

        if ($n === 0) {
            return '';
        }

        $max = max($vals);
        if ($max <= 0) {
            $spark = str_repeat($levels[0], $n);
        } else {
            $spark = '';
            $lastN = 10; // подсветка последних N секунд
            $tailColor = "\033[1;37m"; // белый
            $currentColor = "\033[1;32m"; // зелёный
            $endColor = "\033[0m";

            foreach ($vals as $i => $v) {
                $ratio = $v / $max;
                $idx = (int) floor($ratio * (count($levels) - 1));
                $ch  = $levels[$idx];

                if ($color) {
                    if ($i === $n - 1) {
                        // Текущая секунда → зелёный
                        $spark .= $currentColor . $ch . $endColor . '[' . $v . ']';
                    } elseif ($i >= $n - $lastN) {
                        // Хвост последних 10 сек → белый
                        $spark .= $tailColor . $ch . $endColor;
                    } else {
                        $spark .= $ch;
                    }
                } else {
                    $spark .= $ch;
                }
            }
        }

        return $spark;
    }
}