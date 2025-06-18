<?php

namespace Shm\ShmUtils;


class DeepAccess
{


    public static function safeGet(string | int $key, $data): mixed
    {
        if (is_object($data) || $data instanceof \Traversable) {
            if (isset($data->{$key})) {
                return $data->{$key};
            }
        } elseif (is_array($data)) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }
        return null;
    }




    public static function applyRecursive(mixed &$node, array $path, callable $replacer): void
    {
        if (empty($path)) {
            $node = $replacer($node);
            return;
        }

        $segment = array_shift($path);

        if ($segment === '[]') {
            if (is_object($node) || is_array($node) || $node instanceof \Traversable) {
                foreach ($node as &$item) {
                    self::applyRecursive($item, $path, $replacer);
                }
            }
        } else {
            if (isset($node[$segment])) {
                self::applyRecursive($node[$segment], $path, $replacer);
            }
        }
    }


    public static function getByPathValues(mixed $data, array $path): mixed
    {


        if (count($path) == 0) {

            return [$data];
        }

        $firstSegment = array_shift($path);
        $values = [];


        if ($firstSegment === '[]') {

            if (is_object($data) || is_array($data) || $data instanceof \Traversable) {

                foreach ($data as $key => $item) {
                    $results = self::getByPathValues($item, $path);

                    if ($results)
                        foreach ($results as $res) {
                            $values[] = $res;
                        }
                }
            }
        } else {
            if (isset($data[$firstSegment])) {


                $results = self::getByPathValues($data[$firstSegment], $path);

                if ($results)
                    foreach ($results as $res) {
                        $values[] = $res;
                    }
            }
        }

        return $values;
    }


    public static function getByPath(mixed $data, array $path): mixed
    {


        if (count($path) == 0) {

            return $data;
        }

        $firstSegment = array_shift($path);
        $values = [];

        if ($firstSegment === '[]') {
            if (is_object($data) || is_array($data) || $data instanceof \Traversable) {


                foreach ($data as $item) {
                    $results = self::getByPath($item, $path);

                    if ($results instanceof \MongoDB\BSON\ObjectId) {
                        $values[] = $results;
                    } else {
                        foreach ($results as $res) {
                            $values[] = $res;
                        }
                    }
                }
            }
        } else {
            if (isset($data[$firstSegment])) {


                $results = self::getByPath($data[$firstSegment], $path);

                if ($results instanceof \MongoDB\BSON\ObjectId) {
                    $values[] = $results;
                } else {
                    foreach ($results as $res) {
                        $values[] = $res;
                    }
                }
            }
        }

        return $values;
    }

    /**
     * Применить колбэк к каждому элементу по пути.
     * Колбэк получает ссылку на элемент.
     */
    public static  function setByPath(array|object &$data, array $path, callable $callback): void
    {
        $refs = [&$data];

        foreach ($path as $i => $segment) {
            $newRefs = [];

            foreach ($refs as &$ref) {
                if ($segment === '[]') {
                    if (is_array($ref)) {
                        foreach ($ref as &$subRef) {
                            $newRefs[] = &$subRef;
                        }
                    }
                } else {
                    if (is_array($ref) && array_key_exists($segment, $ref)) {
                        $newRefs[] = &$ref[$segment];
                    } elseif (is_object($ref) && isset($ref->$segment)) {
                        $newRefs[] = &$ref->$segment;
                    }
                }
            }

            unset($ref); // очистить ссылку
            $refs = &$newRefs;
        }

        foreach ($refs as &$target) {
            $callback($target);
        }
    }
}
