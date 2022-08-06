<?php

namespace BambooDesk;



class Database extends \PDO
{
    public $_dbPrefix;
    public $lastInsertedId;
    public function __construct(string $dsn, string $username, string $password, string $dbPrefix, array $options = [])
    {
        $default_options[\PDO::ATTR_DEFAULT_FETCH_MODE] = \PDO::FETCH_ASSOC;
        $default_options[\PDO::ATTR_EMULATE_PREPARES] = false;
        $default_options[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;
        $options = array_replace($default_options, $options);
        $this->_dbPrefix = $dbPrefix;
        parent::__construct($dsn, $username, $password, $options);
    }

    public function runSql(string $sql, $arguments = null)
    {
        if (!$arguments){
            return $this->query($sql);
        }
        $statement = $this->prepare($sql);
        $statement->execute($arguments);
        $this->lastInsertedId = $this->lastInsertId();
        return $statement;
    }

    public function generateInsertStatement(string $table, array $data)
    {
        $fields = '';
        $values = '';

        foreach($data as $field => $value)
        {
            $fields .= $field.',';
            $values .= "'$value',";
        }

        $fields .= rtrim($fields, ", ");
        $values .= rtrim($values, ", ");

        $insertStatement = "INSERT INTO ".$this->_dbPrefix.$table." ($fields) VALUES ($values)";

        return $insertStatement;
    }

    public function createSQLString($selectColumns, $fromTable, $whereColumns = null, $limitBy = null, $orderByTable = null,
                                    $orderByField = null,  $orderBy = null, $joinColumns = null)
    {
        //SELECT

        $sqlSelect = "SELECT ";
        if (is_array($selectColumns)) {
            foreach ($selectColumns as $column) {
                $sqlSelect .= $column . ", ";
            }

            $sqlSelect = rtrim($sqlSelect, ", ");
        } else {
            $sqlSelect .= $selectColumns;
        }

        $sqlString = $sqlSelect." FROM " .$this->_dbPrefix.$fromTable;

        // JOIN STATEMENT

        if ($joinColumns != null)
        {
            $sqlJoin = " LEFT JOIN ";

            if (count($joinColumns) > 1) {
                foreach($joinColumns as $join)
                {
                    $sqlJoin .= $this->_dbPrefix.$join ." LEFT JOIN ";
                }
            } else {
                $sqlJoin .= $this->_dbPrefix.$joinColumns[0];
            }

            $sqlJoin = rtrim($sqlJoin, " LEFT JOIN");
            $sqlString .= $sqlJoin;
        }

        //WHERE FILTERS
        if ($whereColumns != null)
        {
            $sqlWhere = " WHERE ";
            if(is_array($whereColumns))
            {
                foreach($whereColumns as $where)
                {
                    $sqlWhere .= $where;
                }
            } else {
                $sqlWhere .= $whereColumns;
            }

            $sqlString .= $sqlWhere;
        }

        //ORDER BY
        if($orderByTable != null)
        {
            $sqlOrder = " ORDER BY ".$orderByTable.".".$orderByField ." " .$orderBy;

            $sqlString .= $sqlOrder;
        }

        //LIMIT
        if($limitBy != null)
        {
            $sqlLimit = " LIMIT ".$limitBy;
            $sqlString .= $sqlLimit;
        }


        //SQL STRING
        return $sqlString.';';
    }

    public function buildFilterString(array $items, string $filterType)
    {
        $sqlFilterString = $filterType.' (';
        $filterItems = '';
        foreach($items as $item)
        {
            $filterItems .= "'$item', ";
        }

        $sqlFilterString .= rtrim($filterItems, ", ");
        $sqlFilterString .= ")";

        return $sqlFilterString;
    }

    public function databaseVersion()
    {
        return $this->query('SELECT version()')->fetchColumn();
    }
}