<?php

namespace Shm\ShmDB\SQL;

use Sentry;
use Shm\ShmUtils\ShmUtils;

class DBInstance
{


    protected  $dbConnect;



    public  function reportError($sql = '')
    {

        $lastError = $this->getLastError();
        if ($lastError) {
            $message = "Database Error: {$lastError}\nSQL Query: {$sql}";
            Sentry\captureException(new \ErrorException($message));
        }
    }


    public  function __construct($host, $user, $pass, $dbname, $port = 3306)
    {

        $this->dbConnect = mysqli_connect($host, $user, $pass, $dbname, $port);
        mysqli_set_charset($this->dbConnect, "utf8mb4");

        if (mysqli_connect_errno()) {
            echo 'no database connect';
            exit;
        }
    }




    public  function escape($s)
    {
        $s = mysqli_real_escape_string($this->dbConnect, trim($s));
        return $s;
    }

    public  function getRow($result)
    {
        $row = mysqli_fetch_assoc($result);



        return $row;
    }

    public  function getError()
    {
        return mysqli_errno($this->dbConnect);
    }


    public  function query($sql)
    {
        $result = mysqli_query($this->dbConnect, $sql);



        $this->reportError($sql);


        return $result;
    }


    public  function select($fields = "*", $from = "", $where = "", $orderby = "", $limit = "")
    {
        if (!$from) {
            echo 'Ошибка при работе с Базой.';
            exit;
        } else {
            $table = $from;
            $where = ($where != "") ? "WHERE $where" : "";
            $orderby = ($orderby != "") ? "ORDER BY $orderby " : "";
            $limit = ($limit != "") ? "LIMIT $limit" : "";

            $result = $this->query("select $fields FROM $table $where $orderby $limit");

            $this->reportError("select $fields FROM $table $where $orderby $limit");

            return $result;
        }
    }

    public  function makeArray($rs = '')
    {
        if (!$rs) return false;
        $rsArray = array();
        $qty = $this->getRecordCount($rs);
        for ($i = 0; $i < $qty; $i++) $rsArray[] = $this->getRow($rs);
        return $rsArray;
    }

    public  function makeArrayWithKey($rs = '', $key_name = false)
    {
        if (!$key_name)  return false;
        if (!$rs) return false;
        $rsArray = array();
        $qty = $this->getRecordCount($rs);
        for ($i = 0; $i < $qty; $i++) {
            $row = $this->getRow($rs);
            $rsArray[$row[$key_name]] = $row;
        }
        return $rsArray;
    }


    public  function getLastError()
    {
        return mysqli_error($this->dbConnect);
    }


    public  function getRecordCount($result)
    {
        $row_cnt = mysqli_num_rows($result);
        return $row_cnt;
    }

    public  function update($fields, $table, $where = "")
    {

        if (!$table)
            return false;
        else {
            if (!is_array($fields))
                $flds = $fields;
            else {
                $flds = '';
                foreach ($fields as $key => $value) {
                    if (!empty($flds))
                        $flds .= ",";
                    $flds .= "`" . $key . "` =";
                    $flds .= "'" . $value . "'";
                }
            }
            $where = ($where != "") ? "WHERE $where" : "";

            $result = $this->query("UPDATE $table SET $flds $where");


            $this->reportError("UPDATE $table SET $flds $where");



            return $result;
        }
    }


    public  function getInsertId()
    {
        return mysqli_insert_id($this->dbConnect);
    }


    public  function getTableMetaData($table)
    {
        $metadata = false;
        if (!empty($table)) {
            $sql = "SHOW FIELDS FROM $table";
            if ($ds = $this->query($sql)) {
                while ($row = $this->getRow($ds)) {
                    $fieldName = $row['Field'];
                    $metadata[$fieldName] = $row;
                }
            }
        }
        return $metadata;
    }

    public  function delete($from, $where = '', $fields = '')
    {
        if (!$from)
            return false;
        else {
            $table = $from;
            $where = ($where != "") ? "WHERE $where" : "";
            return $this->query("DELETE $fields FROM $table $where");
        }
    }

    public    function getColumn($name, $dsq)
    {
        $col = array();
        while ($row = $this->getRow($dsq)) {
            $col[] = $row[$name];
        }
        return $col;
    }


    public  function haveField($table, $fields = [])
    {


        $db_info = (array)$this->getTableMetaData($table);

        $have_count = 0;

        foreach ($db_info as $db_info_key => $db_info_item)
            if (in_array($db_info_key, $fields)) $have_count++;


        return $have_count == count($fields);
    }

    public  function insert($fields, $intotable, $fromfields = "*", $fromtable = "", $where = "", $limit = "")
    {
        if (!$intotable)
            return false;
        else {

            $sql = "";

            if (!is_array($fields))
                $flds = $fields;
            else {
                $keys = array_keys($fields);
                $values = array_values($fields);
                $flds = "(" . implode(",", $keys) . ") " . (!$fromtable && $values ? "VALUES('" . implode("','", $values) . "')" : "");
                if ($fromtable) {
                    $where = ($where != "") ? "WHERE $where" : "";
                    $limit = ($limit != "") ? "LIMIT $limit" : "";
                    $sql = "select $fromfields FROM $fromtable $where $limit";
                }
            }

            $rt = $this->query("INSERT INTO $intotable $flds $sql");


            $lid = $this->getInsertId();

            $this->reportError("INSERT INTO $intotable $flds $sql");





            return $lid ? $lid : $rt;
        }
    }
}
