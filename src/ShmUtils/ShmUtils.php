<?php

namespace Shm\ShmUtils;

use Shm\ShmTypes\StructureType;

class ShmUtils
{


    public static function allRequest()
    {

        $get = $_GET ?? [];
        $post = $_POST ?? [];
        $request = $_REQUEST ?? [];
        $requestBody = [];
        $body = file_get_contents('php://input');
        if ($body) {
            $requestBody = json_decode($body, true);
        }

        return [...$get, ...$post, ...$request, ...$requestBody];
    }

    public static function privateAccess($valid_username, $valid_password)
    {

        if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
            header('WWW-Authenticate: Basic realm="Restricted Area"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Authentication required';
            exit;
        } else {
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = $_SERVER['PHP_AUTH_PW'];

            if ($username !== $valid_username || $password !== $valid_password) {
                header('WWW-Authenticate: Basic realm="Restricted Area"');
                header('HTTP/1.0 401 Unauthorized');
                echo 'Invalid credentials';
                exit;
            }
        }
    }

    public static   function num2str($n, $textForms)
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;
        if ($n > 10 && $n < 20) {
            return $textForms[2];
        }
        if ($n1 > 1 && $n1 < 5) {
            return $textForms[1];
        }
        if ($n1 === 1) {
            return $textForms[0];
        }
        return $textForms[2];
    }


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
            throw new \Exception("Key must contain only letters, numbers, underscores, and asterisks (*). Invalid key: {$key}");
        }
    }
}
