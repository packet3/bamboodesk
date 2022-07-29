<?php

namespace BambooDesk;



class Database extends \PDO
{
    private $_dbPrefix;
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
        return $statement;
    }

    public function createSQLString($selectColumns, $whereColumns = null, $limitBy = null, $orderByTable = null,
                                    $orderByField = null,  $orderBy = null, $joinColumns = null)
    {

        $sqlSelect = "SELECT ";
        foreach($selectColumns as $column)
        {
            $sqlSelect .= "t.".$column .", ";
        }

        $sqlSelect = rtrim($sqlSelect, ", ");

        $sqlJoin = "LEFT JOIN ";

        if (count($joinColumns) > 1) {
            foreach($joinColumns as $join)
            {
                $sqlJoin .= $this->_dbPrefix.$join ." LEFT JOIN ";
            }
        } else {
            $sqlJoin .= $this->_dbPrefix.$joinColumns[0];
        }

        $sqlJoin = rtrim($sqlJoin, " LEFT JOIN");

        $sqlWhere = "WHERE ";
        foreach($whereColumns as $where)
        {
            $sqlWhere .= $where;
        }

        $sqlOrder = "ORDER BY ".$orderByTable.".".$orderByField ." " .$orderBy;
        $sqlLimit = "LIMIT ".$limitBy;

        //CREATE SQL QUERY
        return $sqlSelect ." FROM " .$this->_dbPrefix ."tickets t ". $sqlJoin ." ". $sqlWhere ." ". $sqlOrder ." ". $sqlLimit;
    }
    public function databaseVersion()
    {
        return $this->query('SELECT version()')->fetchColumn();
    }
}