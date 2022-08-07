<?php

namespace BambooDesk;

class Ticket
{
    protected $db;
    protected $user;

    public int $id;
    public array $auto_assigned;
    public string $mask;
    public string $priorityName;
    private array $assigned_override;

    public int $did;
    public int $uid;
    public string $email;
    public string $subject;

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

    public function __construct(Database $db, $user)
    {
        $this->db = $db;
        $this->user = $user;
    }
    public function prepare_ticket_notification(array $template_data) :string
    {
        $ticketId = $template_data['ticket_id'];
        $ticketLink = $template_data['ticket_link'];
        $department = $template_data['department_name'];
        $priority =  $template_data['priority_level'];
        $subject = $template_data['subject'];
        $message = $template_data['ticket_message'];
        $personName =  $template_data['person_name'];

        $html = "<p>Dear $personName,</p><p>A new guest ticket has been submitted on your behalf. Our staff will review your ticket shortly and reply accordingly.</p>
                <p>---------------------------</p>
                <p>Ticket ID: $ticketId 
                    <br />Subject: $subject
                    <br />Department: $department
                    <br />Priority: $priority</p>
                <p>---------------------------</p>
                <p>$message</p>
                <p>---------------------------</p>
                <p>You can view your ticket using this link: 
                <a href='$ticketLink'>View Ticket</a></p><p>Regards,</p>
                <p>The Bamboo Desk team.<br /><a href='#'>http://bamboo.local</a></p>";

        return $html;
    }
    public function fetch_ticket_priorty_level(int $ticketId): string
    {
        $data = [];
        $data['ticket_id'] = $ticketId;

        $table = $this->db->_dbPrefix."priorities";
        $joinTable = $this->db->_dbPrefix."tickets";

        $sql = "SELECT p.name FROM $table p
                INNER JOIN $joinTable t ON  p.id = t.priority
                WHERE t.id = :ticket_id";

        return $this->priorityName = $this->db->runSql($sql, $data)->fetchColumn();
    }
    public function create_admin_ticket(array $ticket): bool
    {
        $ticket['mask'] = uniqid('T');
        $this->mask = $ticket['mask'];

        try {
            $sql = "INSERT INTO " .$this->db->_dbPrefix."tickets (did, uid, email, subject, priority, message,
                                                                date, last_reply, last_uid, ipadd, status, accepted, mask, name, lang, notify)
                                                          VALUES (:did, :uid, :email, :subject, :priority, :message,
                                                                :date, :last_reply, :last_uid, :ipadd, :status, :accepted, :mask, :name, :lang, :notify)";
        $this->db->runSql($sql, $ticket);
        $this->id = $this->db->lastInsertedId;

        //Increment Department tickets count.
        $this->increment_department_tickets_count($ticket['did']);

        //Increment User Ticket Count.
        if($ticket['uid'])
        {
          $this->increment_user_tickets_count($ticket['uid']);
        }

        return true;
        } catch (\PDOException $e) {
            if ($e->errorInfo[1] === 1062){
                return false;
            }
            throw $e;
        }


    }


    public function add_assignment($uid, $tid, $skip_check=0, $return_name=0, $no_email=0, $data=array())
    {
        # Not safe to use as shut down query
        $data = [];

        if ( ! $uid = intval( $uid ) ) return false;
        if ( ! $tid = intval( $tid ) ) return false;

        if ( ! $skip_check )
        {
            if ( $this->check_assignment( $uid, $tid ) ) return false;
        }

        if ( $return_name )
        {
            $data['id'] = $uid;
            $data['offset'] = 0;
            $data['row_count'] = 1;

            $sql = "SELECT name FROM users WHERE id = :id LIMIT :offset, :row_count";
            if (!count($this->db->runSql($sql, $data)->fetchAll()))
            {
                return false;
            }

            $result = $this->db->runSql($sql, $data)->fetch();

        }

        $data = [];
        $data['tid'] = $tid;
        $data['uid'] = $uid;

        $sql = "INSERT INTO assign_map (tid, uid) VALUES (:tid, :uid)";
        $this->db->runSql($sql, $data);

        if ( ! $no_email )
        {
            $this->trellis->load_email();

            $email_tags = array(
                '{TICKET_ID}'        => $tid,
                '{UNAME}'            => $data['uname'],
                '{SUBJECT}'            => $data['subject'],
                '{DEPARTMENT}'        => $this->trellis->cache->data['departs'][ $data['did'] ]['name'],
                '{PRIORITY}'        => $this->trellis->cache->data['priorities'][ $data['priority'] ]['name'],
                '{MESSAGE}'            => $this->trellis->prepare_email( $data['message'], 0, 'plain' ),
                '{MESSAGE_HTML}'    => $this->trellis->prepare_email( $data['message'], 0, 'html' ),
                '{LINK}'            => $this->trellis->config['hd_url'] .'/admin.php?section=manage&page=tickets&act=view&id='. $tid,
                '%7BLINK%7D'        => $this->trellis->config['hd_url'] .'/admin.php?section=manage&page=tickets&act=view&id='. $tid,
                '{ACTION_USER}'        => $this->trellis->user['name'],
            );

            if ( $uid != $this->trellis->user['id'] ) $this->trellis->email->send_email( array( 'to' => $uid, 'msg' => 'ticket_assign_staff', 'replace' => $email_tags, 'type' => 'staff_assign', 'type_staff' => 'assign' ) );
        }
        if ( $return_name )
        {
            return $result['name'];
        }
        else
        {
            return true;
        }

    }

    public function check_assignment($uid, $tid) : int
    {
        if ( ! $uid = intval( $uid ) ) return false;
        if ( ! $tid = intval( $tid ) ) return false;

        $data = [];
        $data['tid'] = $tid;
        $data['uid'] = $uid;
        $data['offset'] = 0;
        $data['row_count'] = 1;

        $sql = "SELECT id FROM assign_map WHERE tid = :tid AND uid = :uid";
        return count($this->db->runSql($sql, $data)->fetchAll());

    }


    public function increment_department_tickets_count(int $departmentId)
    {
        $data = [];
        $data['id'] = $departmentId;

        $table = $this->db->_dbPrefix."departments";
        $sql = "SELECT tickets_total FROM $table WHERE id = :id";
        $totalTicketsDept = $this->db->runSql($sql, $data)->fetchColumn();

        $data['total'] = $totalTicketsDept + 1;

        $sql = "UPDATE $table SET tickets_total = :total WHERE id = :id";
        $this->db->runSql($sql, $data);

    }

    public function increment_user_tickets_count(int $userId)
    {
        $data = [];
        $data['id'] = $userId;

        $table = $this->db->_dbPrefix."users";
        $sql = "SELECT tickets_total, tickets_open FROM $table WHERE id =:id";
        $statement = $this->db->runSql($sql, $data);

        $ticketsCurrentTotal = $statement->fetchColumn();
        $ticketsCurrentOpen = $statement->fetchColumn(1);

        $data['tickets_total'] = $ticketsCurrentTotal;
        $data['tickets_open'] = $ticketsCurrentOpen;

        $sql = "UPDATE $table SET tickets_total = :tickets_total, tickets_open = :tickets_open WHERE id = :id";
        $this->db->runSql($sql, $data);

    }

    public function fetch_ticket_by_id_and_userid($tid)
    {
        $data = [];
        $data['table'] = $this->db->_dbPrefix."tickets";
        $data['tid'] = $tid;
        $data['uid'] = $this->user['id'];

        $sql = "SELECT id FROM :table WHERE id = :tid AND uid = :uid";
        return $this->db->runSql($sql, $data)->fetch();

    }

    public function check_ticket_permission($tid, $did, $type)
    {
        if ( $this->user['id'] == 1 ) return true;

        if ( ! $this->user['g_acp_depart_perm'][ $did ]['v'] )
        {
            if ( ! $this->assigned_override[ $tid ] )
            {
                if ( ! $a = $this->fetch_ticket_by_id_and_userid($tid) )
                {
                    return false;
                }

                $this->assigned_override[ $tid ] = $a['id'];
            }
        }

        if ( $type == 'v' ) return true;

        if ( ! $this->user['g_acp_depart_perm'][ $did ][ $type ] ) return false;

        return true;
    }

}