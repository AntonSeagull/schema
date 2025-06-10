<?php

namespace Shm\GQLUtils;


class Utils
{

    public static function upperCase($str)
    {
        if (empty($str)) {
            return '';
        }

        $result = mb_strtoupper($str);
        return $result;
    }

    public static function onlyLetters($str)
    {
        $result = preg_replace('/[^a-zа-я]/ui', '*', $str);
        $result = explode('*', $result ?? "");
        $result = array_diff($result, ['']);

        $text = [];
        foreach ($result as $index => $val) {

            $text[] = $val;
        }
        return ucfirst(implode('', $text));
    }
}