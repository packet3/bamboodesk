<?php

namespace BambooDesk;

class Log
{
    private $db;
    private $settings;
    private $bamboo;
    private $user;

    public function __construct(Database $db, array $settings, $bamboo, $user)
    {
        $this->db = $db;
        $this->settings = $settings;
        $this->bamboo = $bamboo;
        $this->user = $user;
    }

    public function log(array $params)
    {
        if ( ! $this->settings['log']['enable'] ) return true;

        if ( IN_TDA === true )
        {
            if ( ! $this->settings['log']['acp'] ) return true;
        }
        else
        {
            if ( ! $this->settings['log']['nonacp'] ) return true;
        }

        if ( ! $params['msg'] ) return false;
        if ( ! $params['type'] ) return false;

        if ( ! $this->settings['log'][ $params['type'] ] ) return true;

        if ( ! $params['level'] ) $params['level'] = 1;

        if ( is_array( $params['msg'] ) )
        {
            $action = vsprintf( $this->lang[ 'log_'. array_shift( $params['msg'] ) ], $params['msg'] );
        }
        else
        {
            $action = ( $this->lang[ 'log_'. $params['msg'] ] ) ? $this->lang[ 'log_'. $params['msg'] ] : $params['msg'];
        }


        if ( $params['extra'] ) $db_array['extra'] = serialize( $params['extra'] );
        if ( $params['uid'] ) $this->user['id'] = $params['uid'];

        $data = [];
        $data['uid'] = $this->user['id'];
        $data['action'] = $action;
        $data['type'] = $params['type'];
        $data['level'] = $params['level'];
        $data['content_type'] = $params['content_type'];
        $data['content_id'] = $params['content_id'];
        $data['admin'] = ( ( IN_TDA === true ) ? 1 : 0 );
        $data['date'] = time();
        $data['ipadd'] = $this->input['ip_address'];
        $data['extra'] = 'Not Used Yet';
        $data['table'] = $this->db->_dbPrefix."logs";

        $sql = "INSERT INTO :table (uid, action, extra, type, level, content_type, content_id, admin, date, ipadd)
                VALUES(:uid, :action, :extra, :type, :level, :content_type, :content_id, :admin, :date, :ipadd)";

        $this->db->runSql($sql, $data);
    }

}