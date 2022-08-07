<?php

namespace BambooDesk;

class Settings
{
    private $db;
    public $settings;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function fetch_settings()
    {
        $table = $this->db->_dbPrefix."settings";
        $sql = "SELECT cf_group, cf_key, cf_value FROM $table";
        $statement = $this->db->runSql($sql);
        //$group = $statement->fetchColumn();
        $rows = $statement->fetchAll();



        $groupSettings = [];


        foreach($rows as $item)
        {

            if(!array_key_exists($item['cf_group'], $groupSettings))
            {

                $groupSettings += array($item['cf_group'] => array($item['cf_key'] => $item['cf_value']));


            } else {
                $groupSettings[$item['cf_group']] += array($item['cf_key'] => $item['cf_value']);
            }


        }

        return $this->settings = $groupSettings;


    }
}