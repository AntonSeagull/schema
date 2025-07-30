<?php

namespace Shm\ShmUtils;


class AutoPostfix


{


    public static function get(array $keys)
    {


        $keys = array_map(function ($key) {
            return ShmUtils::translitIfCyrillic($key);
        }, $keys);



        $firstLetters = array_map(function ($key) {
            if ($key[0] === '_') {
                return isset($key[1]) ? strtoupper($key[1]) : '';
            } else {
                return strtoupper($key[0]);
            }
        }, $keys);

        $uniqueLetters = array_unique($firstLetters);
        sort($uniqueLetters);


        if (count($uniqueLetters) > 4) {
            $length = count($uniqueLetters);
            $result = [];
            $result[] = $uniqueLetters[0];
            $result[] = $uniqueLetters[max(1, floor($length / 4))];
            $result[] = $uniqueLetters[max(2, floor($length / 2))];
            $result[] = $uniqueLetters[max(3, $length - 1)];
            $uniqueLetters = array_unique($result);
        }


        return implode('', $uniqueLetters);
    }
}
