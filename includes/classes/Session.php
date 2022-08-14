<?php

namespace BambooDesk;

class Session
{
    protected $db;
    protected $bamboo;

    public function __construct(Database $db, object $bamboo)
    {
        $this->db =  $db;
        $this->bamboo = $bamboo;
    }

    #=======================================
    # @ Do Login
    #=======================================

    public function do_login()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $this->bamboo->input['username'] || ! $this->bamboo->input['password'] ) $this->error( 'fill_form_completely', 1 );

        #=============================
        # Select User
        #=============================
        $data = [];
        $table =  $this->db->_dbPrefix."users m";
        $join_table = $this->db->_dbPrefix."groups g";
        $data['username'] = strtolower( $this->bamboo->input['username'] );

        $sql = "SELECT m.id, m.name, m.email, m.pass_hash, m.pass_salt, m.ugroup, m.ugroup_sub, m.title, m.joined, 
                    m.signature, m.sig_html, m.sig_auto, m.lang, m.skin, m.time_zone, m.time_dst, m.rte_enable, 
                    m.email_enable, m.email_ticket, m.email_action, m.email_news, m.email_type, m.tickets_total, 
                    m.tickets_open, m.val_email, m.val_admin, g.* FROM $table
                    LEFT JOIN $join_table ON g.g_id = m.ugroup
                    WHERE LOWER(m.name) = :username";

        $mem = $this->db->runSql($sql, $data)->fetch();

        if ( ! $mem )
        {
            $this->error('login_no_user', 1);

            # TD LOG: name not found
        }


        #=============================
        # Compare Password
        #=============================

        if ( hash( 'whirlpool', $mem['pass_salt'] . $this->bamboo->input['password'] . $this->bamboo->config['pass_key'] ) == $mem['pass_hash'] )
        {
            #=============================
            # Validation Check
            #=============================

            if ( ! $mem['val_email'] ) $this->error('login_must_val'); # TD LOG: no email val

            if ( ! $mem['val_admin'] ) $this->error('login_must_val_admin'); # TD LOG: no admin val

            #=============================
            # Delete Old Session
            #=============================

            if ( $this->user['s_id'] )
            {
                $data = [];
                $data['table'] = $this->db->_dbPrefix."sessions";
                $data['session_id'] = $this->user['s_id'];

                $sql = "DELETE FROM :table WHERE s_id = :session_id";
                $this->db->runSql($sql, $data);
            }

            #=============================
            # Create Session
            #=============================

            $session_hash = 's'. $this->user['id'] . uniqid( rand(), true );

           $new_session = hash( 'ripemd160', $session_hash . $this->bamboo->config['session_key'] );


            $data = [];
            $table = $this->db->_dbPrefix."sessions";
            $data['id'] = $new_session;
            $data['uid'] = $mem['id'];
            $data['uname'] = $mem['name'];
            $data['email'] = $mem['email'];
            $data['ipadd'] = $this->bamboo->input['ip_address'];
            $data['location'] = $this->bamboo->input['act'];
            $data['time'] = time();

            $sql = "INSERT INTO $table (s_id, s_uid, s_uname, s_email, s_ipadd, s_location, s_time)
                    VALUES(:id, :uid, :uname, :email, :ipadd, :location, :time)";

            $this->db->runSql($sql, $data);

            $this->set_cookie( 'tdsid', $session_hash, time() + ( $this->trellis->cache->data['settings']['security']['session_timeout'] * 60 ) );

            #=============================
            # Remember Me?
            #=============================

            if ( $this->bamboo->input['remember'] )
            {
                #=============================
                # New Login Key
                #=============================

                $rmsalt = '';

                while( strlen( $rmsalt ) < 8 ) $rmsalt .= chr(rand( 32,126 ) );

                $rmsalt .= uniqid( rand(), true );

                $rmhash = str_replace( "=", "", base64_encode( hash('ripemd160', $rmsalt . $mem['id'] ) ) );

                $lk_hash = hash( 'ripemd160', $rmhash . $this->bamboo->config['cookie_key'] );

                $data = [];
                $table = $this->db->_dbPrefix."users";
                $data['login_key'] = $lk_hash;
                $data['id'] = $mem['id'];

                $sql = "UPDATE $table SET login_key = :login_key WHERE id = :id";
                $this->db->runSql($sql, $data);

                $this->set_cookie( 'tduid', $mem['id'] );
                $this->set_cookie( 'tdrmhash', $rmhash );
            }

            #=============================
            # Return
            #=============================

            // Play It Safe
            $mem['pass_hash'] = $mem['pass_salt'] = "";

            //$mem = array_merge( $mem, $db_array );

            $this->user = $mem;

            // Permissions
            $this->user['g_depart_perm'] = unserialize( $this->user['g_depart_perm'] );
            $this->user['g_kb_perm'] = unserialize( $this->user['g_kb_perm'] );

            // Sub-Groups
            //$this->user['ugroup_sub'] = unserialize( $this->user['ugroup_sub'] );

//            if ( is_array( $this->user['ugroup_sub'] ) && ! empty( $this->user['ugroup_sub'] ) )
//            {
//                $this->users = array_merge( $this->user, $this->merge_groups( $this->user, $this->user['ugroup_sub'] ) );
//            }

            return $this->user;
        }
        else
        {
            $this->error('login_no_pass', 1); # TD Log: incorrect pass
        }
    }

    #=======================================
    # @ Error
    #=======================================

    private function error($msg, $login=0)
    {
        $this->bamboo->user = $this->user; # TODO: I don't like this =/

        $this->bamboo->load_skin();

        $this->bamboo->skin->error($msg, $login);
    }

    private function set_cookie($name, $value, $time='')
    {
        if ( ! $time )
        {
            $time = time() + 60*60*24*365; // Sec*Min*Hrs*Days
        }

        if ( $this->cache->data['settings']['general']['cookie_prefix'] ) $name = $this->cache->data['settings']['general']['cookie_prefix'] . $name;

        @setcookie( $name, $value, $time, $this->cache->data['settings']['general']['cookie_path'], $this->cache->data['settings']['general']['cookie_domain'] );
    }

}