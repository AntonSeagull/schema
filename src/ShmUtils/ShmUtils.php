<?php

namespace Shm\ShmUtils;

use Shm\Shm;
use Shm\ShmTypes\StructureType;

class ShmUtils
{

    public static function validate(array $schema, $data = null)
    {

        $schema = Shm::structure($schema);

        if ($data === null) {
            $data = ShmUtils::allRequest();
        }

        $schema->validate($data);
    }

    public static function translitIfCyrillic(string $input): string
    {
        $translit_map = [
            'а' => 'a',
            'б' => 'b',
            'в' => 'v',
            'г' => 'g',
            'д' => 'd',
            'е' => 'e',
            'ё' => 'yo',
            'ж' => 'zh',
            'з' => 'z',
            'и' => 'i',
            'й' => 'y',
            'к' => 'k',
            'л' => 'l',
            'м' => 'm',
            'н' => 'n',
            'о' => 'o',
            'п' => 'p',
            'р' => 'r',
            'с' => 's',
            'т' => 't',
            'у' => 'u',
            'ф' => 'f',
            'х' => 'kh',
            'ц' => 'ts',
            'ч' => 'ch',
            'ш' => 'sh',
            'щ' => 'shch',
            'ъ' => '',
            'ы' => 'y',
            'ь' => '',
            'э' => 'e',
            'ю' => 'yu',
            'я' => 'ya',
            'А' => 'A',
            'Б' => 'B',
            'В' => 'V',
            'Г' => 'G',
            'Д' => 'D',
            'Е' => 'E',
            'Ё' => 'Yo',
            'Ж' => 'Zh',
            'З' => 'Z',
            'И' => 'I',
            'Й' => 'Y',
            'К' => 'K',
            'Л' => 'L',
            'М' => 'M',
            'Н' => 'N',
            'О' => 'O',
            'П' => 'P',
            'Р' => 'R',
            'С' => 'S',
            'Т' => 'T',
            'У' => 'U',
            'Ф' => 'F',
            'Х' => 'Kh',
            'Ц' => 'Ts',
            'Ч' => 'Ch',
            'Ш' => 'Sh',
            'Щ' => 'Shch',
            'Ъ' => '',
            'Ы' => 'Y',
            'Ь' => '',
            'Э' => 'E',
            'Ю' => 'Yu',
            'Я' => 'Ya'
        ];

        // Проверяем, есть ли хотя бы одна кириллическая буква
        if (preg_match('/[\p{Cyrillic}]/u', $input)) {
            return strtr($input, $translit_map);
        }

        // Возвращаем оригинал, если кириллицы нет
        return $input;
    }


    public static function allRequest()
    {

        $get = $_GET;
        $post = $_POST;
        $request = $_REQUEST;
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


    public static function cleanKey($key)
    {

        // Удаляем управляющие и невидимые символы, пробелы
        $cleanKey = preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200D}\x{FEFF}]+/u', '', $key);
        $cleanKey = trim($cleanKey);

        return $cleanKey;
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


        $validKey = Config::get('validKey', true);

        if (!$validKey) {
            return;
        }
        if (!preg_match('/^[a-zA-Z0-9_*]+$/', $key)) {
            throw new \Exception("Key must contain only letters, numbers, underscores, and asterisks (*). Invalid key: {$key}");
        }
    }
}
