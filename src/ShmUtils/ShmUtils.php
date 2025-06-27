<?php

namespace Shm\ShmUtils;


class ShmUtils
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

    public static function isValidKey($key)
    {

        if (!$key) {
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_*]+$/', $key)) {
            throw new \InvalidArgumentException("Key must contain only letters, numbers, underscores, and asterisks (*). Invalid key: {$key}");
        }
    }
}
