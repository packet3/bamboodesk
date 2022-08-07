<?php

namespace BambooDesk;

class Attachment
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function get_attachments($filter, $filterColumn)
    {
        $data = [];
        $data['filter'] = $this->db->buildFilterString($filter, "IN");
        $data['filter_column'] = $filterColumn;
        $data['table'] = $this->db->_dbPrefix."attachments";


        $sql = "SELECT id, original_name, size FROM :table WHERE :filter_column = :filter";

        $rows = $this->trellis->database->runSql($sql, $data)->fetchAll();
        if ( ! $rows)
        {
            return false;
        }

        foreach($rows as $a)
        {
            if ( $a['id'] )
            {
                $return[ $a['id'] ] = $a;
            }
            else
            {
                $return[] = $a;
            }
        }

        return $return;
    }

    public function assign($ids, $cid)
    {
        if ( ! $cid = intval( $cid ) ) return false;

        if ( ! is_array( $ids ) && intval( $ids ) )
        {
            $ids = array( $ids );
        }

        $data = [];
        $data['table'] = $this->db->_dbPrefix."attachments";
        $data['cid'] = $cid;
        $data['ids'] = $this->db->buildFilterString($ids, "IN");

        $sql = "UPDATE :table SET content_id = :cid WHERE id :ids";
        return $this->db->runSql($sql, $data)->rowCount();

    }
}