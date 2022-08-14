<?php

namespace BambooDesk;



class User
{
    private $db;
    public $userEmail;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function fetch_user_email_by_id(int $userId) :bool
    {
        $data = [];
        $data['id'] = $userId;

        $sql = "SELECT email FROM ".$this->db->_dbPrefix."users WHERE id = :id";
        $userEmail = $this->db->runSql($sql, $data)->fetchColumn();
        if($userEmail)
        {
            $this->userEmail = $userEmail;
            return true;
        }

        return false;


    }

    public function fetch_user_by_id(int $userId) :array
    {
        $data = [];
        $data['user_id'] = $userId;
        $table = $this->db->_dbPrefix."users";

        $sql = "SELECT * FROM $table WHERE id = :user_id ";
        return $this->db->runSql($sql, $data)->fetch();

    }

    public function fetch_user_by_email(string $userEmail)
    {
        $data = [];
        $data['user_email'] = $userEmail;
        $table = $this->db->_dbPrefix."users";

        $sql = "SELECT * FROM $table WHERE id = :user_email ";
        return $this->db->runSql($sql, $data)->fetch();

    }

}