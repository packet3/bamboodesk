<?php

namespace BambooDesk;

class Ticket
{
    protected $db;

    public int $id;
    public string $mask;
    public int $did;
    public int $uid;
    public string $email;
    public string $subject;
    public int $priority;
    public string $message;
    public int $html;
    public int $last_uid;
    public int $replies;
    public int $votes;
    public float $rating;
    public float $rating_total;
    public string $notes;
    public int $close_uid;
    public int $close_date;
    public int $status;
    public int $accepted;
    public int $aua;
    public int $escalated;
    public int $onhold;
    public int $closed;
    public int $allow_reopen;
    public int $last_reply;
    public int $last_reply_staff;
    public int $date;
    public string $ipadd;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function create_ticket(array $ticket): bool
    {
        $ticket['mask'] = uniqid('T');

        try {
            $sql = "INSERT INTO " .$this->db->_dbPrefix."tickets (did, uid, email, subject, priority, message,
                                                                date, last_reply, last_uid, ipadd, status, accepted, mask)
                                                          VALUES (:did, :uid, :email, :subject, :priority, :message,
                                                                :date, :last_reply, :last_uid, :ipadd, :status, :accepted, :mask)";
        $this->db->runSql($sql, $ticket);
        return true;
        } catch (\PDOException $e) {
            if ($e->errorInfo[1] === 1062){
                return false;
            }
            throw $e;
        }


    }






}