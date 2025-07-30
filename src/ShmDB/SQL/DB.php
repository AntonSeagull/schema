<?php

namespace Shm\ShmDB\SQL;

use PDO;
use PDOException;
use Sentry;

class DB
{

    protected static DBInstance  $instance;

    public static function connect($host, $user, $pass, $dbname, $port = 3306)
    {
        self::$instance = new DBInstance($host, $user, $pass, $dbname, $port);
    }

    protected static function reportError($sql = '')
    {
        self::$instance->reportError($sql);
    }

    public static function query($sql)
    {
        return self::$instance->query($sql);
    }

    public static function select($fields = "*", $from = "", $where = "", $orderby = "", $limit = "")
    {
        return self::$instance->select($fields, $from, $where, $orderby, $limit);
    }

    public static function insert($fields, $table)
    {
        return self::$instance->insert($fields, $table);
    }

    public static function update($fields, $table, $where = "")
    {
        return self::$instance->update($fields, $table, $where);
    }

    public static function delete($table, $where = "")
    {
        return self::$instance->delete($table, $where);
    }

    public static function getRow($stmt)
    {
        return self::$instance->getRow($stmt);
    }

    public static function makeArray($stmt)
    {
        return self::$instance->makeArray($stmt);
    }

    public static function getColumn($name, $stmt)
    {
        return self::$instance->getColumn($name, $stmt);
    }

    public static function escape($s)
    {
        return self::$instance->escape($s);
    }

    public static function getLastError()
    {
        return self::$instance->getLastError();
    }

    public static function getInsertId()
    {
        return self::$instance->getInsertId();
    }

    public static function getRecordCount($stmt)
    {
        return self::$instance->getRecordCount($stmt);
    }

    public static function getTableMetaData($table)
    {
        return self::$instance->getTableMetaData($table);
    }

    public static function haveField($table, $fields = [])
    {
        return self::$instance->haveField($table, $fields);
    }
}
