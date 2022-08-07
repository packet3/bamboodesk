<?php

namespace BambooDesk;

class Department
{
    private $db;
    public $name;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function fetch_department_name_by_ticket_id(string $ticketId): string
    {
        $data = [];
        $data['ticket_id'] = $ticketId;

        $table = $this->db->_dbPrefix."departments";
        $joinTable = $this->db->_dbPrefix."tickets";

        $sql = "SELECT d.name FROM $table d
                INNER JOIN $joinTable t ON  d.id = t.did
                WHERE t.id = :ticket_id";

        return $this->name = $this->db->runSql($sql, $data)->fetchColumn();
    }

    public function get_custom_department_fields_by_ticket_id(int $ticketId) : array
    {
        $return = [];

        $data = [];
        $data['ticket_id'] = $ticketId;

        $table = $this->db->_dbPrefix."depart_fields_data";
        $sql = "SELECT fid, data, extra FROM $table WHERE tid = :ticket_id";
        $data = $this->db->runSql($sql, $data)->fetchAll();

        if ( ! count($data) )
        {
            return [];
        }

        foreach($data as $row)
        {
            if ( $row['extra'] )
            {
                $return[ $row['fid'] ][ $row['extra'] ] = $row['data'];
            }
            else
            {
                $return[ $row['fid'] ] = $row['data'];
            }
        }

        return $return;
    }

}