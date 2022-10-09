<?php

use BambooDesk\Attachment;
use BambooDesk\Department;
use BambooDesk\Email;
use BambooDesk\Log;
use BambooDesk\Ticket;
use BambooDesk\User;

/**
 * Trellis Desk
 *
 * @copyright  Copyright (C) 2009-2012 ACCORD5. All rights reserved.
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 */
define( 'TD_TICKETS', TD_PATH ."admin/manage/ticket" );
class td_ad_tickets {



    private $output = "";
    private $assigned_override = array();
    private $parsed_sigs;
    private Attachment $attachment;
    private Log $log;
    private Ticket $ticket ;
    private Department $department;
    private Email $email;
    private User $bamboo_user;
    private $sqlOrderBy = "";
    private $sqlWhere = "";







    #=======================================
    # @ Auto Run
    #=======================================

    public function auto_run()
    {

        //Instantiate Needed Classes
        $this->attachement = new Attachment($this->trellis->database, $this->trellis);
        $this->log = new Log($this->trellis->database, $this->trellis->settings, $this->trellis->lang, $this->trellis->user);
        $this->ticket = new Ticket($this->trellis->database, $this->trellis->user);
        $this->department = new Department($this->trellis->database);
        $this->email = new Email($this->trellis->config,$this->trellis->database, $this->trellis);
        $this->bamboo_user = new User($this->trellis->database);

        $this->trellis->load_functions('tickets');
        $this->trellis->load_lang('tickets');

        $this->trellis->skin->set_active_link( 2 );

        switch( $this->trellis->input['act'] )
        {
            case 'list':
                $this->list_tickets();
            break;
            case 'view':
                $this->view_ticket();
            break;
            case 'add':
                $this->add_ticket();
            break;
            case 'edit':
                $this->edit_ticket();
            break;

            case 'doaddassign':
                $this->ajax_add_assign();
            break;
            case 'doaddflag':
                $this->ajax_add_flag();
            break;
            case 'dodelassign':
                $this->ajax_delete_assign();
            break;
            case 'dodelflag':
                $this->ajax_delete_flag();
            break;
            case 'donotes':
                $this->ajax_save_notes();
            break;
            case 'dodefaults':
                $this->ajax_save_defaults();
            break;

            case 'getstatus':
                $this->ajax_get_status();
            break;
            case 'getrt':
                $this->ajax_get_reply_template();
            break;

            case 'doaccept':
                $this->do_accept();
            break;
            case 'doescalate':
                $this->do_escalate();
            break;
            case 'dormvescalate':
                $this->do_rmvescalate();
            break;
            case 'dohold':
                $this->do_hold();
            break;
            case 'dormvhold':
                $this->do_rmvhold();
            break;
            case 'domove':
                $this->do_move();
            break;
            case 'doclose':
                $this->do_close();
            break;
            case 'doreopen':
                $this->do_reopen();
            break;

            case 'dopriority':
                $this->do_priority();
            break;
            case 'dostatus':
                $this->do_status();
            break;

            case 'dountrack':
                $this->do_untrack();
            break;
            case 'dotrackall':
                $this->do_track_all();
            break;

            case 'doadd':
                $this->do_add();
            break;
            case 'doedit':
                $this->do_edit();
            break;
            case 'dodel':
                $this->do_delete();
            break;

            case 'doaddreply':
                $this->do_add_reply();
            break;
            case 'doeditreply':
                $this->do_edit_reply();
            break;
            case 'dodelreply':
                $this->do_delete_reply();
            break;

            case 'getreply':
                $this->ajax_get_reply();
            break;
            case 'doupload':
                $this->do_upload();
            break;
            case 'dodelupload':
                $this->do_delete_upload();
            break;
            case 'attachment':
                $this->do_attachment();
            break;

            default:
                $this->list_tickets();
            break;
        }
    }

    #=======================================
    # @ List Tickets
    #=======================================

    private function list_tickets()
    {
       include TD_TICKETS.'/list_tickets.php';
    }

    #=======================================
    # @ Build SQL Order By
    #=======================================
    private function build_sql_order_by($orderBy)
    {
        $this->sqlOrderBy = $orderBy;
    }

    #=======================================
    # @ Build SQL Where
    #=======================================
    private function build_sql_where($filter)
    {

    }
    #=======================================
    # @ View Ticket
    #=======================================

    private function view_ticket($params=array())
    {
        include TD_TICKETS.'/view_ticket.php';
    }

    #=======================================
    # @ Add Ticket
    #=======================================

    private function add_ticket()
    {
        if ( ! $this->trellis->user['g_ticket_create'] && $this->trellis->user['id'] != 1 )
        {
            $this->trellis->skin->error('no_perm');
        }

        switch( $this->trellis->input['step'] )
        {
            case 1:
                $this->add_ticket_step_1();
            break;
            case 2:
                $this->add_ticket_step_2();
            break;

            default:
                $this->add_ticket_step_1();
            break;
        }
    }

    #=======================================
    # @ Add Ticket Step 1
    #=======================================

    private function add_ticket_step_1()
    {
        #=============================
        # Do Output
        #=============================

        if ( $this->trellis->input['uid'] )
        {
            $this->trellis->load_functions('users');

            if ( ! $u = $this->trellis->users->fetch_user_by_id($this->trellis->input['uid']))
            {
                $this->trellis->skin->error('no_user');
            }
        }

        $perms = &$this->trellis->user['g_depart_perm'];
        if ( $this->trellis->user['id'] != 1 )
        {
            if ( is_array( $this->trellis->user['g_acp_depart_perm'] ) )
            {
                foreach( $this->trellis->user['g_acp_depart_perm'] as $did => $dperm )
                {
                    if ( $dperm['v'] ) $perms[ $did ] = 1;
                }
            }
        }

        $depart_list = "<table width='100%' cellpadding='0' cellspacing='0' class='departlist'>";

        foreach( $this->trellis->cache->data['departs'] as $id => &$d )
        {
            if ( $this->trellis->user['id'] == 1 || $perms[ $d['id'] ] ) $depart_list .= "<tr><td width='1%' valign='top'><input type='radio' name='did' id='d_{$id}' value='{$id}'". ( ( $this->trellis->input['did'] == $id ) ? " checked='checked'" : "" ) ." /></td><td width='99%'><label for='d_{$id}'>{$d['name']}<br /><em>{$d['description']}</em></label></td></tr>";
        }

        $depart_list .= '</table>';

        $this->output .= "<div id='ticketroll'>
                        ". $this->trellis->skin->start_form( "<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=add&amp;step=2", 'add_ticket', 'post' ) ."
                        <input name='uid' id='uid' type='hidden' value='{$this->trellis->input['uid']}' />
                        ". $this->trellis->skin->start_group_table( '{lang.submit_a_ticket}', 'a' ) ."
                        <tr>
                            <th class='bluecellthin-th' align='left' colspan='2'>{lang.submit_ticket_user_msg}</th>
                        </tr>
                        ". $this->trellis->skin->group_table_row( '{lang.behalf_of_user}', $this->trellis->skin->textfield( 'uname', $u['name'] ), 'a', '20%', '80%' ) ."
                        ". $this->trellis->skin->end_group_table() ."
                        ". $this->trellis->skin->group_sub( '{lang.select_department}' ) ."
                        ". $this->trellis->skin->group_row( $depart_list, 'a' ) ."
                        ". $this->trellis->skin->end_form( $this->trellis->skin->submit_button( 'add', '{lang.button_step_2}' ) ) ."
                        </div>";

        $this->trellis->skin->end_group( 'a' );

        $this->output .= "<script type='text/javascript'>
                        //<![CDATA[
                        $('#uname').autocomplete('<! TD_URL !>/admin.php?act=lookup&type=user', {
                            dataType: 'json',
                            parse: function(data) {
                                return $.map(data, function(row) {
                                    return {
                                        data: row,
                                        result: row.caption
                                    }
                                });
                            },
                            formatItem: function(row, i, max) {
                                return row.caption;
                            },
                            matchSubset: false
                        });
                        $('#uname').result(function(event, data, formatted) {
                            $('#uid').val(data['value']);
                        });
                        //]]>
                        </script>";

        $this->output .= $this->trellis->skin->focus_js('uname');

        $menu_items = array(
                            array( 'arrow_back', '{lang.menu_back}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets' ),
                            array( 'settings', '{lang.menu_settings}', '<! TD_URL !>/admin.php?section=tools&amp;page=settings&amp;act=edit&amp;group=ticket' ),
                            );

        $this->trellis->skin->add_sidebar_menu( '{lang.menu_tickets_options}', $menu_items );
        $this->trellis->skin->add_sidebar_help( '{lang.random_title}', '{lang.random_text}' );

        $this->trellis->skin->add_skin_javascript( 'autocomplete.js' );

        $this->trellis->skin->add_output( $this->output );

        $this->trellis->skin->do_output();
    }

    #=======================================
    # @ Add Ticket Step 2
    #=======================================

    private function add_ticket_step_2()
    {
        #=============================
        # Security Checks
        #=============================

        $this->trellis->load_functions('users');

        $validate = true;
        if ( $this->trellis->input['uid'] )
        {
            //if ( ! $u = $this->trellis->func->users->get_single_by_id( array( 'id', 'name' ), $this->trellis->input['uid'] ) )
            if(! $u = $this->trellis->users->fetch_user_by_id($this->trellis->input['uid']))
            {
                $this->trellis->send_message( 'error', $this->trellis->lang['error_no_user'] );
                $validate = false;
            }
        }
        else
        {
            if ( $this->trellis->validate_email( $this->trellis->input['uname'] ) )
            {
                //if ( ! $u = $this->trellis->func->users->get_single_by_email( array( 'id', 'name' ), $this->trellis->input['uname'] ) )
                if(! $u = $this->trellis->users->fetch_user_by_email( $this->trellis->input['uname']))
                {
                    $u = array( 'id' => 0 );
                }
            }
            else
            {
                $this->trellis->send_message( 'error', $this->trellis->lang['error_no_user'] );
                $validate = false;
            }
        }
        if ( ! $this->trellis->input['did'] )
        {
            $this->trellis->send_message( 'error', $this->trellis->lang['error_no_depart'] );
            $validate = false;
        }
        if ( ! $validate )
        {
            $this->trellis->skin->preserve_input = 1;
            $this->add_ticket_step_1();
        }

        $perms = &$this->trellis->user['g_depart_perm'];

        if ( $this->trellis->user['id'] != 1 && ! $perms[ $this->trellis->input['did'] ])
        {
            $this->trellis->skin->error('no_perm');
        }

        //we need to generate a Ticket ID at this stage, this is so we can associate the attachments
        // when a ticket is created
        $bytes = random_bytes(4);
        $ticketId = 'T'.bin2hex($bytes);


        #=============================
        # Do Output
        #=============================

        $this->trellis->load_functions('drop_downs');
        $this->trellis->load_functions('cdfields');

        $this->output .= "<div id='ticketroll'>
                        ". $this->trellis->skin->start_form( "<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=doadd", 'add_ticket', 'post' ) ."
                        <input name='uid' id='uid' type='hidden' value='{$this->trellis->input['uid']}' />
                        <input name='tid_mask' id='tid_mask' type='hidden' value='T{$ticketId}' />
                        ". ( ( ! $u['id'] ) ? "<input name='email' id='email' type='hidden' value='{$this->trellis->input['uname']}' />" : "" ) ."
                        <input name='did' id='did' type='hidden' value='{$this->trellis->input['did']}' />
                        ". $this->trellis->skin->start_group_table( '{lang.submit_a_ticket}', 'a' ) ."
                        ". $this->trellis->skin->group_table_row( ( ( $u['id'] ) ? '{lang.user}' : '{lang.guest_email}' ), ( ( $u['id'] ) ? $u['name'] : $this->trellis->input['uname'] ), 'a', '20%', '80%' ) ."
                        ". ( ( ! $u['id'] ) ? $this->trellis->skin->group_table_row( '{lang.guest_name}', $this->trellis->skin->textfield( 'name' ), 'a' ) : "" ) ."
                        ". ( ( ! $u['id'] ) ? $this->trellis->skin->group_table_row( '{lang.guest_preferences}', "<select name='lang'>". $this->trellis->func->drop_downs->lang_drop( $this->trellis->input['lang'] ) ."</select>&nbsp;&nbsp;&nbsp;". $this->trellis->skin->checkbox( array( 'name' => 'notify', 'title' => '{lang.email_notifications}', 'value' => ( ( isset( $this->trellis->input['notify'] ) ) ? $this->trellis->input['notify'] : 1 ) ) ), 'a' ) : "" ) ."
                        ". $this->trellis->skin->group_table_row( '{lang.subject}', $this->trellis->skin->textfield( 'subject' ), 'a' ) ."
                        ". $this->trellis->skin->group_table_row( '{lang.priority}', "<select name='priority'>". $this->trellis->func->drop_downs->priority_drop( array( 'select' => $this->trellis->input['priority'] ) ) ."</select>", 'a' );

        if ( $cfields = $this->trellis->departments->get_custom_department_fields_by_id( $this->trellis->input['did'] ) )
        {
            foreach( $cfields as $fid => $f )
            {
                $f['extra'] = unserialize( $f['extra'] );

                if ( $f['type'] == 'textfield' )
                {
                    $this->output .= $this->trellis->skin->group_table_row( $f['name'], $this->trellis->skin->textfield( array( 'name' => 'cdf_'. $f['id'], 'length' => $f['extra']['size'] ) ), 'a' );
                }
                elseif ( $f['type'] == 'textarea' )
                {
                    $this->output .= $this->trellis->skin->group_table_row( $f['name'], $this->trellis->skin->textarea( array( 'name' => 'cdf_'. $f['id'], 'cols' => $f['extra']['cols'], 'rows' => $f['extra']['rows'] ) ), 'a' );
                }
                elseif ( $f['type'] == 'dropdown' )
                {
                    $this->output .= $this->trellis->skin->group_table_row( $f['name'], $this->trellis->skin->drop_down( array( 'name' => 'cdf_'. $f['id'], 'options' => $f['extra'] ) ), 'a' );
                }
                elseif ( $f['type'] == 'checkbox' )
                {
                    $checkbox_html = '';

                    foreach( $f['extra'] as $key => $name )
                    {
                        $checkbox_html .= $this->trellis->skin->checkbox( array( 'name' => 'cdf_'. $f['id'] .'_'. $key, 'title' => $name ) ) .'&nbsp;&nbsp;&nbsp;';
                    }

                    $this->output .= $this->trellis->skin->group_table_row( $f['name'], $checkbox_html, 'a' );
                }
                elseif ( $f['type'] == 'radio' )
                {
                    $this->output .= $this->trellis->skin->group_table_row( $f['name'], $this->trellis->skin->custom_radio( array( 'name' => 'cdf_'. $f['id'], 'options' => $f['extra'] ) ), 'a' );
                }
            }
        }

        $this->output .= $this->trellis->skin->end_group_table() ."
                        ". $this->trellis->skin->group_sub( '{lang.message}' ) ."
                        ". $this->trellis->skin->group_row( $this->trellis->skin->textarea( array( 'name' => 'message', 'cols' => '80', 'rows' => '10', 'width' => '98%', 'height' => '180px' ) ), 'a' ) ."
                        ";

        if ( $this->trellis->settings['ticket']['attachments'] && $this->trellis->user['g_ticket_attach'] && $this->trellis->cache->data['departs'][ $this->trellis->input['did'] ]['allow_attach'] )
        {
            //$this->output .= $this->trellis->skin->group_row( $this->trellis->skin->uploadify_js( 'upload_file', array( 'section' => 'manage', 'page' => 'tickets', 'act' => 'doupload', 'type' => 'ticket', 'id' => $this->trellis->input['did'] ), array( 'multi' => true, 'list' => true ) ), 'a' ) ."
            $this->output .= $this->trellis->skin->group_row( $this->trellis->skin->uploadify_js(array( 'id' => $ticketId)));
        }

        $this->output .= $this->trellis->skin->end_form( $this->trellis->skin->submit_button( 'add', '{lang.button_add_ticket}' ) ) ."
                        </div>";

        $this->trellis->skin->end_group( 'a' );

        $validate_fields = array(
                                 'subject'    => array( array( 'type' => 'presence', 'params' => array( 'fail_msg' => '{lang.lv_no_subject}' ) ) ),
                                 );

        $this->output .= $this->trellis->skin->live_validation_js( $validate_fields );
        $this->output .= ( ( $u['id'] ) ? $this->trellis->skin->focus_js('subject') : $this->trellis->skin->focus_js('name') );

        $menu_items = array(
                            array( 'arrow_back', '{lang.menu_back}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets' ),
                            array( 'settings', '{lang.menu_settings}', '<! TD_URL !>/admin.php?section=tools&amp;page=settings&amp;act=edit&amp;group=ticket' ),
                            );

        $this->trellis->skin->add_sidebar_menu( '{lang.menu_tickets_options}', $menu_items );
        $this->trellis->skin->add_sidebar_help( '{lang.random_title}', '{lang.random_text}' );

        $this->trellis->skin->add_output( $this->output );

        $this->trellis->skin->do_output();
    }

    #=======================================
    # @ Edit Ticket
    #=======================================

    private function edit_ticket()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did', 'subject', 'priority', 'message', 'closed' ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'et' ) ) $this->trellis->skin->error('no_perm');

        if ( $t['closed'] ) $this->trellis->skin->error('ticket_closed');

        #=============================
        # Do Output
        #=============================

        $priority = ( $this->trellis->input['priority'] ) ? $this->trellis->input['priority'] : $t['priority'];

        $this->trellis->load_functions('drop_downs');
        $this->trellis->load_functions('cdfields');

        $this->output .= "<div id='ticketroll'>
                        ". $this->trellis->skin->start_form( "<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=doedit&amp;id={$t['id']}", 'edit_ticket', 'post' ) ."
                        ". $this->trellis->skin->start_group_table( '{lang.editing_ticket} '. $t['subject'], 'a' ) ."
                        ". $this->trellis->skin->group_table_row( '{lang.subject}', $this->trellis->skin->textfield( array( 'name' => 'subject', 'value' => $t['subject'] ) ), 'a', '20%', '80%' ) ."
                        ". $this->trellis->skin->group_table_row( '{lang.priority}', "<select name='priority'>". $this->trellis->func->drop_downs->priority_drop( array( 'select' => $priority ) ) ."</select>", 'a' );

        if ( $cfields = $this->trellis->func->cdfields->grab( $t['did'] ) )
        {
            $fdata = $this->trellis->func->cdfields->get_data( $t['id'] );

            foreach( $cfields as $fid => $f )
            {
                $f['extra'] = unserialize( $f['extra'] );

                if ( $f['type'] == 'textfield' )
                {
                    $this->output .= $this->trellis->skin->group_table_row( $f['name'], $this->trellis->skin->textfield( array( 'name' => 'cdf_'. $f['id'], 'value' => $fdata[ $f['id'] ], 'length' => $f['extra']['size'] ) ), 'a' );
                }
                elseif ( $f['type'] == 'textarea' )
                {
                    $this->output .= $this->trellis->skin->group_table_row( $f['name'], $this->trellis->skin->textarea( array( 'name' => 'cdf_'. $f['id'], 'value' => $fdata[ $f['id'] ], 'cols' => $f['extra']['cols'], 'rows' => $f['extra']['rows'] ) ), 'a' );
                }
                elseif ( $f['type'] == 'dropdown' )
                {
                    $this->output .= $this->trellis->skin->group_table_row( $f['name'], $this->trellis->skin->drop_down( array( 'name' => 'cdf_'. $f['id'], 'value' => $fdata[ $f['id'] ], 'options' => $f['extra'] ) ), 'a' );
                }
                elseif ( $f['type'] == 'checkbox' )
                {
                    $checkbox_html = '';

                    foreach( $f['extra'] as $key => $name )
                    {
                        $checkbox_html .= $this->trellis->skin->checkbox( array( 'name' => 'cpf_'. $f['id'] .'_'. $key, 'title' => $name, 'value' => $fdata[ $f['id'] ][ $key ] ) ) .'&nbsp;&nbsp;&nbsp;';
                    }

                    $this->output .= $this->trellis->skin->group_table_row( $f['name'], $checkbox_html, 'a' );
                }
                elseif ( $f['type'] == 'radio' )
                {
                    $this->output .= $this->trellis->skin->group_table_row( $f['name'], $this->trellis->skin->custom_radio( array( 'name' => 'cpf_'. $f['id'], 'value' => $fdata[ $f['id'] ], 'options' => $f['extra'] ) ), 'a' );
                }
            }
        }

        $this->output .= $this->trellis->skin->end_group_table() ."
                        ". $this->trellis->skin->group_sub( '{lang.message}' ) ."
                        ". $this->trellis->skin->group_row( $this->trellis->skin->textarea( array( 'name' => 'message', 'value' => $t['message'], 'cols' => '80', 'rows' => '10', 'width' => '98%', 'height' => '180px' ) ), 'a' ) ."
                        ". $this->trellis->skin->end_form( $this->trellis->skin->submit_button( 'edit', '{lang.button_edit_ticket}' ) ) ."
                        </div>";

        $this->trellis->skin->end_group( 'a' );

        $validate_fields = array(
                                 'subject'    => array( array( 'type' => 'presence', 'params' => array( 'fail_msg' => '{lang.lv_no_subject}' ) ) ),
                                 );

        $this->output .= $this->trellis->skin->live_validation_js( $validate_fields );

        $menu_items = array(
                            array( 'arrow_back', '{lang.menu_back}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets' ),
                            array( 'circle_plus', '{lang.menu_add}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=add' ),
                            array( 'settings', '{lang.menu_settings}', '<! TD_URL !>/admin.php?section=tools&amp;page=settings&amp;act=edit&amp;group=ticket' ),
                            );

        $this->trellis->skin->add_sidebar_menu( '{lang.menu_tickets_options}', $menu_items );
        $this->trellis->skin->add_sidebar_help( '{lang.random_title}', '{lang.random_text}' );

        $this->trellis->skin->add_output( $this->output );

        $this->trellis->skin->do_output();
    }

    #=======================================
    # @ Do Accept
    #=======================================

    private function do_accept()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did', 'subject', 'accepted', 'closed' ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'r' ) ) $this->trellis->skin->error('no_perm');

        if ( $t['closed'] ) $this->trellis->skin->error('ticket_closed');

        if ( $t['accepted'] ) $this->trellis->skin->error('ticket_already_accepted');

        #=============================
        # Accept Ticket
        #=============================

        $db_array = array(
                          'status'        => $this->trellis->cache->data['misc']['default_statuses'][2],
                          'accepted'    => 1,
                         );

        $this->trellis->func->tickets->edit( $db_array, $t['id'] );

        $this->trellis->log( array( 'msg' => array( 'ticket_accepted', $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_ticket_accepted'] );

        $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
    }

    #=======================================
    # @ Do Escalate
    #=======================================

    private function do_escalate()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $this->trellis->cache->data['settings']['ticket']['escalate'] ) $this->trellis->skin->error('no_perm');

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 't' => array( 'id', 'mask', 'did', 'uid', 'email', 'subject', 'priority', 'message', 'accepted', 'escalated', 'closed' ), 'g' => array( 'gname', 'lang', 'notify' ), 'u' => array( array( 'name' => 'uname' ) ) ), 'from' => array( 't' => 'tickets' ), 'join' => array( array( 'from' => array( 'u' => 'users' ), 'where' => array( 'u' => 'id', '=', 't' => 'uid' ) ), array( 'from' => array( 'g' => 'tickets_guests' ), 'where' => array( 'g' => 'id', '=', 't' => 'id' ) ) ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'es' ) ) $this->trellis->skin->error('no_perm');

        if ( $t['closed'] ) $this->trellis->skin->error('ticket_closed');

        if ( $t['escalated'] ) $this->trellis->skin->error('ticket_already_escalated');

        if ( ! $this->trellis->cache->data['departs'][ $t['did'] ]['escalate_enable'] ) $this->trellis->skin->error('no_perm');

        #=============================
        # Escalate Ticket
        #=============================

        if ( ! $t['uid'] ) $t['uname'] = $t['gname'];

        $this->trellis->func->tickets->escalate( $t['id'], array( 'did' => $t['did'], 'accepted' => $t['accepted'], 'staff' => 1, 'data' => $t, 'clear_assigned' => $this->trellis->input['clrassign'] ) );

        if ( $this->trellis->input['clrassign'] )
        {
            $this->trellis->log( array( 'msg' => array( 'ticket_assigncleared', $t['subject'] ), 'type' => 'ticket', 'level' => 2, 'content_type' => 'ticket', 'content_id' => $t['id'] ) );
        }

        $this->trellis->log( array( 'msg' => array( 'ticket_escalateadd', $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

        if ( $assigned = $this->trellis->func->tickets->get_auto_assigned() )
        {
            foreach ( $assigned as $aid => &$aname )
            {
                $this->trellis->log( array( 'msg' => array( 'ticket_assignaddatuo', $aname, $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );
            }
        }

        if ( $moved = $this->trellis->func->tickets->get_auto_moved() )
        {
            $this->trellis->log( array( 'msg' => array( 'ticket_movedauto',  $this->trellis->cache->data['departs'][ $t['did'] ]['name'], $this->trellis->cache->data['departs'][ $moved ]['name'], $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );
        }

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_ticket_escalated'] );

        $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
    }

    #=======================================
    # @ Do Remove Escalate
    #=======================================

    private function do_rmvescalate()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did', 'subject', 'escalated', 'closed' ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'es' ) ) $this->trellis->skin->error('no_perm');

        if ( $t['closed'] ) $this->trellis->skin->error('ticket_closed');

        if ( ! $t['escalated'] ) $this->trellis->skin->error('ticket_not_escalated');

        #=============================
        # Removae Escalated Status
        #=============================

        $this->trellis->func->tickets->rmvescalate( $t['id'] );

        $this->trellis->log( array( 'msg' => array( 'ticket_escalatermv', $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_ticket_rmvescalated'] );

        $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
    }

    #=======================================
    # @ Do Move
    #=======================================

    private function do_move()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $this->trellis->cache->data['departs'][ $this->trellis->input['did'] ] ) $this->trellis->skin->error('no_depart');

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 't' => array( 'id', 'mask', 'did', 'uid', 'email', 'subject', 'priority', 'message' ), 'g' => array( 'gname', 'lang', 'notify' ), 'u' => array( array( 'name' => 'uname' ) ) ), 'from' => array( 't' => 'tickets' ), 'join' => array( array( 'from' => array( 'u' => 'users' ), 'where' => array( 'u' => 'id', '=', 't' => 'uid' ) ), array( 'from' => array( 'g' => 'tickets_guests' ), 'where' => array( 'g' => 'id', '=', 't' => 'id' ) ) ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( $t['did'] == $this->trellis->input['did'] )
        {
            $this->trellis->send_message( 'error', $this->trellis->lang['error_depart_same'] );

            $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
        }

        if ( ! $this->check_perm( $t['id'], $t['did'], 'mv' ) ) $this->trellis->skin->error('no_perm');

        #=============================
        # Move Ticket
        #=============================

        if ( ! $t['uid'] ) $t['uname'] = $t['gname'];

        $this->trellis->func->tickets->move( $this->trellis->input['did'], $t['id'], $t['did'], $t, $this->trellis->input['clrassign'] );

        if ( $this->trellis->input['clrassign'] )
        {
            $this->trellis->log( array( 'msg' => array( 'ticket_assigncleared', $t['subject'] ), 'type' => 'ticket', 'level' => 2, 'content_type' => 'ticket', 'content_id' => $t['id'] ) );
        }

        $this->trellis->log( array( 'msg' => array( 'ticket_moved', $this->trellis->cache->data['departs'][ $t['did'] ]['name'], $this->trellis->cache->data['departs'][ $this->trellis->input['did'] ]['name'], $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

        if ( $assigned = $this->trellis->func->tickets->get_auto_assigned() )
        {
            foreach ( $assigned as $aid => &$aname )
            {
                $this->trellis->log( array( 'msg' => array( 'ticket_assignaddatuo', $aname, $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );
            }
        }

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_ticket_moved'] );

        $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
    }

    #=======================================
    # @ Do Hold
    #=======================================

    private function do_hold()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 't' => array( 'id', 'mask', 'did', 'uid', 'email', 'subject', 'priority', 'message', 'accepted', 'aua', 'onhold', 'closed' ), 'g' => array( 'gname', 'lang', 'notify' ), 'u' => array( array( 'name' => 'uname' ) ) ), 'from' => array( 't' => 'tickets' ), 'join' => array( array( 'from' => array( 'u' => 'users' ), 'where' => array( 'u' => 'id', '=', 't' => 'uid' ) ), array( 'from' => array( 'g' => 'tickets_guests' ), 'where' => array( 'g' => 'id', '=', 't' => 'id' ) ) ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'r' ) ) $this->trellis->skin->error('no_perm');

        if ( $t['closed'] ) $this->trellis->skin->error('ticket_closed');

        if ( $t['onhold'] ) $this->trellis->skin->error('ticket_already_onhold');

        #=============================
        # Hold Ticket
        #=============================

        if ( ! $t['uid'] ) $t['uname'] = $t['gname'];

        $this->trellis->func->tickets->hold( $t['id'], array( 'accepted' => $t['accepted'], 'aua' => $t['aua'], 'data' => $t ) );

        $this->trellis->log( array( 'msg' => array( 'ticket_holdadd', $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_ticket_hold'] );

        $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
    }

    #=======================================
    # @ Do Remove Hold
    #=======================================

    private function do_rmvhold()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did', 'subject', 'aua', 'onhold', 'closed' ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'r' ) ) $this->trellis->skin->error('no_perm');

        if ( $t['closed'] ) $this->trellis->skin->error('ticket_closed');

        if ( ! $t['onhold'] ) $this->trellis->skin->error('ticket_not_onhold'); // TODO: Remove obstructive errors like this? Alert redirect instead?

        #=============================
        # Hold Ticket
        #=============================

        $this->trellis->func->tickets->rmvhold( $t['id'], array( 'aua' => $t['aua'] ) );

        $this->trellis->log( array( 'msg' => array( 'ticket_holdrmv', $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_ticket_rmvhold'] );

        $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
    }

    #=======================================
    # @ Do Close
    #=======================================

    private function do_close()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 't' => array( 'id', 'mask', 'did', 'uid', 'email', 'subject', 'priority', 'message', 'closed' ), 'g' => array( 'gname', 'lang', 'notify' ), 'u' => array( array( 'name' => 'uname' ) ) ), 'from' => array( 't' => 'tickets' ), 'join' => array( array( 'from' => array( 'u' => 'users' ), 'where' => array( 'u' => 'id', '=', 't' => 'uid' ) ), array( 'from' => array( 'g' => 'tickets_guests' ), 'where' => array( 'g' => 'id', '=', 't' => 'id' ) ) ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'c' ) ) $this->trellis->skin->error('no_perm');

        if ( $t['closed'] ) $this->trellis->skin->error('ticket_closed');

        #=============================
        # Close Ticket
        #=============================

        if ( ! $t['uid'] ) $t['uname'] = $t['gname'];

        $this->trellis->func->tickets->close( $t['id'], array( 'uid' => $t['uid'], 'allow_reopen' => $this->trellis->input['reopen'], 'data' => $t ) );

        $this->trellis->log( array( 'msg' => array( 'ticket_closed', $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_ticket_closed'] );

        $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
    }

    #=======================================
    # @ Do Reopen
    #=======================================

    private function do_reopen()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 't' => array( 'id', 'mask', 'did', 'uid', 'email', 'subject', 'priority', 'message', 'accepted', 'aua', 'closed' ), 'g' => array( 'gname', 'lang', 'notify' ), 'u' => array( array( 'name' => 'uname' ) ) ), 'from' => array( 't' => 'tickets' ), 'join' => array( array( 'from' => array( 'u' => 'users' ), 'where' => array( 'u' => 'id', '=', 't' => 'uid' ) ), array( 'from' => array( 'g' => 'tickets_guests' ), 'where' => array( 'g' => 'id', '=', 't' => 'id' ) ) ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'ro' ) ) $this->trellis->skin->error('no_perm');

        if ( ! $t['closed'] ) $this->trellis->skin->error('ticket_not_closed');

        #=============================
        # Reopen Ticket
        #=============================

        if ( ! $t['uid'] ) $t['uname'] = $t['gname'];

        $this->trellis->func->tickets->reopen( $t['id'], array( 'uid' => $t['uid'], 'did' => $t['did'], 'aua' => $t['aua'], 'accepted' => $t['accepted'], 'staff' => 1, 'data' => $t ) );

        $this->trellis->log( array( 'msg' => array( 'ticket_reopened', $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_ticket_reopened'] );

        $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
    }

    #=======================================
    # @ Do Priority
    #=======================================

    private function do_priority()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $this->trellis->input['pid'] ) $this->edit_ticket('no_priority');

        if ( ! $this->trellis->cache->data['priorities'][ $this->trellis->input['pid'] ] ) $this->trellis->skin->error('no_priority');

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did', 'subject', 'priority', 'closed' ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( $t['priority'] == $this->trellis->input['pid'] )
        {
            $this->trellis->send_message( 'error', $this->trellis->lang['error_priority_same'] );

            $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
        }

        if ( ! $this->check_perm( $t['id'], $t['did'], 'et' ) ) $this->trellis->skin->error('no_perm');

        if ( $t['closed'] ) $this->trellis->skin->error('ticket_closed');

        #=============================
        # Change Priority
        #=============================

        $this->trellis->func->tickets->edit( array( 'priority' => $this->trellis->input['pid'] ), $t['id'] );

        $this->trellis->log( array( 'msg' => array( 'ticket_priority', $this->trellis->cache->data['priorities'][ $t['priority'] ]['name'], $this->trellis->cache->data['priorities'][ $this->trellis->input['pid'] ]['name'], $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_priority_updated'] );

        $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
    }

    #=======================================
    # @ Do Status
    #=======================================

    private function do_status()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $this->trellis->input['sid'] ) $this->edit_ticket('no_status');

        if ( ! $this->trellis->cache->data['statuses'][ $this->trellis->input['sid'] ] ) $this->trellis->skin->error('no_status');

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did', 'subject', 'status', 'accepted', 'aua', 'closed' ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'et' ) || ! $t['accepted'] ) $this->trellis->skin->error('no_perm');

        if ( $t['status'] == $this->trellis->input['sid'] )
        {
            $this->trellis->send_message( 'error', $this->trellis->lang['error_status_same'] );

            $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
        }

        $switch_to_aua = 0;

        if ( ! $t['aua'] && ! $t['closed'] )
        {
            if ( $this->trellis->cache->data['statuses'][ $t['status'] ]['type'] != $this->trellis->cache->data['statuses'][ $this->trellis->input['sid'] ]['type'] )
            {
                if ( $this->trellis->cache->data['statuses'][ $this->trellis->input['sid'] ]['type'] != 5 ) $this->trellis->skin->error('no_perm');

                $switch_to_aua = 1;
            }
        }
        else
        {
            if ( $this->trellis->cache->data['statuses'][ $t['status'] ]['type'] != $this->trellis->cache->data['statuses'][ $this->trellis->input['sid'] ]['type'] ) $this->trellis->skin->error('no_perm');
        }

        #=============================
        # Change Status
        #=============================

        if ( $switch_to_aua )
        {
            $db_array = array( 'status' => $this->trellis->input['sid'], 'aua' => 1, 'onhold' => 0 );
        }
        else
        {
            $db_array = array( 'status' => $this->trellis->input['sid'] );
        }

        $this->trellis->func->tickets->edit( $db_array, $t['id'] );

        $this->trellis->log( array( 'msg' => array( 'ticket_status', $this->trellis->cache->data['statuses'][ $t['status'] ]['name'], $this->trellis->cache->data['statuses'][ $this->trellis->input['sid'] ]['name'], $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_status_updated'] );

        $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
    }

    #=======================================
    # @ Do Untrack
    #=======================================

    private function do_untrack()
    {
        if ( $this->trellis->input['tid'] )
        {
            $this->do_untrack_ticket();
        }
        else
        {
            $this->do_untrack_reply();
        }
    }

    #=======================================
    # @ Do Untrack Ticket
    #=======================================

    private function do_untrack_ticket()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did' ) ), $this->trellis->input['tid'] ) ) $this->trellis->skin->error('no_ticket');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'v' ) ) $this->trellis->skin->error('no_ticket');

        #=============================
        # Untrack Ticket
        #=============================

        $this->trellis->func->tickets->untrack( $t['id'] );

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_ticket_untracked'] );

        $this->trellis->skin->redirect( array( 'act' => null ) );
    }

    #=======================================
    # @ Do Untrack Reply
    #=======================================

    private function do_untrack_reply()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $r = $this->trellis->func->tickets->get_single( array( 'select' => array( 'r' => array( 'id', 'tid', 'date' ), 't' => array( 'did' ) ), 'from' => array( 'r' => 'replies' ), 'join' => array( array( 'from' => array( 't' => 'tickets' ), 'where' => array( 't' => 'id', '=', 'r' => 'tid' ) ) ), 'where' => array( array( 'r' => 'id' ), '=', $this->trellis->input['rid'] ) ) ) ) $this->trellis->skin->error('no_reply');

        if ( ! $this->check_perm( $r['tid'], $r['did'], 'v' ) ) $this->trellis->skin->error('no_reply');

        #=============================
        # Untrack Reply
        #=============================

        $this->trellis->func->tickets->untrack_reply( $r['id'], $t['tid'], $r['date'] );

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_reply_untracked'] );

        $this->trellis->skin->redirect( array( 'act' => null ) );
    }

    #=======================================
    # @ Do Track All
    #=======================================

    private function do_track_all()
    {
        #=============================
        # Untrack Ticket
        #=============================

        $this->trellis->func->tickets->track_all();

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_tickets_tracked'] );

        $this->trellis->skin->redirect( array( 'act' => null ) );
    }

    #=======================================
    # @ Do Add
    #=======================================

    private function do_add()
    {
        #=============================
        # Security Checks
        #=============================

        $this->trellis->load_functions('users');

        $validate = true;
        if ( $this->trellis->input['uid'] )
        {
            if ( ! $u = $this->trellis->func->users->get_single_by_id( array( 'id', 'name', 'email' ), $this->trellis->input['uid'] ) )
            {
                $this->trellis->send_message( 'error', $this->trellis->lang['error_no_user'] );
                $validate = false;
            }
        }
        else
        {
            if ( $this->trellis->validate_email( $this->trellis->input['email'] ) )
            {
                if ( ! $u = $this->trellis->func->users->get_single_by_email( array( 'id', 'name', 'email' ), $this->trellis->input['email'] ) )
                {
                    $u = array( 'id' => 0, 'name' => ( ( $this->trellis->input['name'] ) ? $this->trellis->input['name'] : $this->trellis->input['email'] ), 'email' => $this->trellis->input['email'] );
                }
            }
            else
            {
                $this->trellis->send_message( 'error', $this->trellis->lang['error_no_user'] );
                $validate = false;
            }
        }
        if ( ! $this->trellis->input['did'] )
        {
            $this->trellis->send_message( 'error', $this->trellis->lang['error_no_depart'] );
            $validate = false;
        }
        if ( ! $validate )
        {
            $this->trellis->skin->preserve_input = 1;
            $this->add_ticket_step_1();
        }

        $perms = &$this->trellis->user['g_depart_perm'];
        if ( $this->trellis->user['id'] != 1 )
        {
            if ( is_array( $this->trellis->user['g_acp_depart_perm'] ) )
            {
                foreach( $this->trellis->user['g_acp_depart_perm'] as $did => $dperm )
                {
                    if ( $dperm['v'] ) $perms[ $did ] = 1;
                }
            }
        }

        if ( ! $perms[ $this->trellis->input['did'] ] && $this->trellis->user['id'] != 1 )
        {
            $this->trellis->skin->error('no_perm');
        }

        if ( ! $this->trellis->input['subject'] )
        {
            $this->trellis->send_message( 'error', $this->trellis->lang['error_no_subject'] );
            $validate = false;
        }
        if ( ! $this->trellis->input['priority'] )
        {
            $this->trellis->send_message( 'error', $this->trellis->lang['error_no_priority'] );
            $validate = false;
        }
        if ( ! $this->trellis->input['message'] )
        {
            $this->trellis->send_message( 'error', $this->trellis->lang['error_no_message'] );
            $validate = false;
        }
        if ( ! $validate )
        {
            $this->trellis->skin->preserve_input = 1;
            $this->add_ticket_step_2();
        }

        if ( ! $this->trellis->cache->data['departs'][ $this->trellis->input['did'] ] ) $this->trellis->skin->error('no_depart');

        #=============================
        # Add Ticket
        #=============================
        $ticket = [];
        $ticket['did'] = $this->trellis->input['did'];
        $ticket['uid'] = $u['id'];
        $ticket['email'] = $u['email'];
        $ticket['subject'] = $this->trellis->input['subject'];
        $ticket['priority'] = $this->trellis->input['priority'];
        $ticket['message'] = $this->trellis->input['message'];
        $ticket['date'] = time();
        $ticket['last_reply'] = time();
        $ticket['last_uid'] = $u['id'];
        $ticket['ipadd'] = $this->trellis->input['ip_address'];
        $ticket['ipadd'] = $this->trellis->input['ip_address'];
        $ticket['status'] = $this->trellis->cache->data['misc']['default_statuses'][2];
        $ticket['accepted'] = 1;
        $ticket['name'] = $u['name'];
        $ticket['lang'] = $this->trellis->input['lang'];
        $ticket['notify'] = $this->trellis->input['notify'];
        $ticket['mask'] = $this->trellis->input['tid_mask'];

        // Handle Custom Fields

        $customFields = [];
        foreach($this->trellis->input  as $index => $val)
        {

            if ( substr($index, 0, 4) == "cdf_")
            {
                $customFieldId = substr($index, 4);
                $customFields[$customFieldId] = $val;
            }
        }


        $this->ticket->ticketData = $ticket;
        $this->ticket->customTicketFields = $customFields;
        $result  = $this->ticket->create_admin_ticket();

        if($result)
        {

            //Send Comms email if needed
            $message = $this->ticket->prepare_ticket_notification($this->department);


            $messageBody = $this->email->prepare_email($message);

            $emailData['message_subject'] = "Ticket ID: " .$this->ticket->mask;
            $emailData['message_body'] = $messageBody;

            $userId = $this->ticket->ticketData['uid'];
            $result = $this->bamboo_user->fetch_user_email_by_id($userId);

            if($result)
            {
                $emailData['send_to'] = $this->bamboo_user->userEmail; //set from above
            } else {
                $emailData['send_to'] = $this->ticket->ticketData['email']; //this will need to be email provided in form.
            }

            //send the email
            $this->email->send_email($emailData);
        }
        

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_ticket_added'] );

        if ( $this->ticket->check_ticket_permission($this->ticket->mask, $this->trellis->input['did'], 'v'))
        {
            $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $this->ticket->mask  ) );
        }
        else
        {
            $this->trellis->skin->redirect( array( 'act' => null ) );
        }
    }

    #=======================================
    # @ Do Edit
    #=======================================

    private function do_edit()
    {
        #=============================
        # Security Checks
        #=============================

        $validate = true;
        if ( ! $this->trellis->input['subject'] )
        {
            $this->trellis->send_message( 'error', $this->trellis->lang['error_no_subject'] );
            $validate = false;
        }
        if ( ! $this->trellis->input['priority'] )
        {
            $this->trellis->send_message( 'error', $this->trellis->lang['error_no_priority'] );
            $validate = false;
        }
        if ( ! $this->trellis->input['message'] )
        {
            $this->trellis->send_message( 'error', $this->trellis->lang['error_no_message'] );
            $validate = false;
        }

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did', 'closed' ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'et' ) ) $this->trellis->skin->error('no_perm');

        if ( $t['closed'] ) $this->trellis->skin->error('ticket_closed');

        $this->trellis->load_functions('cdfields');

        if( ! $fdata = $this->trellis->func->cdfields->process_input( $t['did'] ) )
        {
            if ( $this->trellis->func->cdfields->required_field )
            {
                $this->trellis->send_message( 'error', $this->trellis->lang['error_no_field'].' '. $this->trellis->func->cdfields->required_field );
                $validate = false;
            }
        }

        if ( ! $validate )
        {
            $this->trellis->skin->preserve_input = 1;
            $this->edit_ticket();
        }

        #=============================
        # Edit Ticket
        #=============================

        $db_array = array(
                          'subject'        => $this->trellis->input['subject'],
                          'priority'    => intval( $this->trellis->input['priority'] ),
                          'message'        => $this->trellis->input['message'],
                         );

        $this->trellis->func->tickets->edit( $db_array, $t['id'] );

        if ( $fdata ) $this->trellis->func->cdfields->set_data( $fdata, $t['id'] );

        $this->trellis->log( array( 'msg' => array( 'ticket_edited', $this->trellis->input['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'alert', $this->trellis->lang['alert_ticket_updated'] );

        $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ) );
    }

    #=======================================
    # @ Do Delete
    #=======================================

    private function do_delete()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did', 'uid', 'subject', 'closed' ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'dt' ) ) $this->trellis->skin->error('no_perm');

        #=============================
        # DELETE Ticket
        #=============================

        $this->trellis->func->tickets->delete( $t['id'], array( 'did' => $t['did'], 'uid' => $t['uid'], 'closed' => $t['closed'] ) );

        $this->trellis->log( array( 'msg' => array( 'ticket_deleted', $t['subject'] ), 'type' => 'ticket', 'level' => 2, 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

        #=============================
        # Redirect
        #=============================

        $this->trellis->send_message( 'error', $this->trellis->lang['error_ticket_deleted'] );

        $this->trellis->skin->redirect( array( 'act' => null ) );
    }

    #=======================================
    # @ Do Add Reply
    #=======================================

    private function do_add_reply()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $this->trellis->input['message'] ) $this->view_ticket( array( 'reply_error' => 'no_message' ) );

        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 't' => array( 'id', 'mask', 'did', 'uid', 'email', 'subject', 'priority', 'message', 'accepted', 'onhold', 'closed' ), 'g' => array( 'gname', 'lang', 'notify' ), 'u' => array( array( 'name' => 'uname' ) ) ), 'from' => array( 't' => 'tickets' ), 'join' => array( array( 'from' => array( 'u' => 'users' ), 'where' => array( 'u' => 'id', '=', 't' => 'uid' ) ), array( 'from' => array( 'g' => 'tickets_guests' ), 'where' => array( 'g' => 'id', '=', 't' => 'id' ) ) ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_ticket');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'r' ) ) $this->trellis->skin->error('no_perm');

        if ( $t['closed'] ) $this->trellis->skin->error('ticket_closed');

        #=============================
        # Add Reply
        #=============================

        ( $this->trellis->input['keep_hold'] == 2 ) ? $keep_hold = 1 : $keep_hold = 0;

        // Auto Assign
        if ( $this->trellis->input['assign_to_me'] && ( $this->check_perm( $t['id'], $t['did'], 'aa' ) || $this->check_perm( $t['id'], $t['did'], 'as' ) ) ) $this->trellis->func->tickets->add_assignment( $this->trellis->user['id'], $t['id'], 0, 0, 1 );

        // RTE Permissions
        ( $this->trellis->input['html'] && $this->trellis->cache->data['settings']['ticket']['rte'] ) ? $html = 1 : $html = 0;

        $db_array = array(
                          'tid'                    => $t['id'],
                          'uid'                    => $this->trellis->user['id'],
                          'message'                => $this->trellis->input['message'],
                          'signature'            => $this->trellis->input['signature'],
                          'staff'                => 1,
                          'html'                => $html,
                          'secret'                => $this->trellis->input['secret'],
                          'date'                => time(),
                          'ipadd'                => $this->trellis->input['ip_address'],
                          'mask'                => $t['mask'], # TODO: move extra data to $params['data']
                          'did'                    => $t['did'],
                          'tuid'                => $t['uid'],
                          'tuname'                => $t['uname'],
                          'subject'                => $t['subject'],
                          'priority'            => $t['priority'],
                          'message_original'    => $t['message'],
                         );

        if ( ! $t['uid'] )
        {
            $db_array['tuname'] = $t['gname'];
            $db_array['email'] = $t['email'];
            $db_array['lang'] = $t['lang'];
            $db_array['notify'] = $t['notify'];
        }

        $reply_id = $this->trellis->func->tickets->add_reply( $db_array, $t['id'], array( 'accepted' => $t['accepted'], 'onhold' => $t['onhold'], 'keep_hold' => $keep_hold ) );

        $this->trellis->log( array( 'msg' => array( 'reply_added', $t['subject'] ), 'type' => 'ticket', 'content_type' => 'reply', 'content_id' => $reply_id ) );

        #=============================
        # Assign Attachments
        #=============================

        if ( is_array( $this->trellis->input['fuploads'] ) )
        {
            $this->trellis->load_functions('attachments');

            if ( $attachments = $this->trellis->func->attachments->get( array( 'select' => array( 'id', 'original_name' ), 'where' => array( 'id', 'in', $this->trellis->input['fuploads'] ) ) ) )
            {
                $to_attach = array();

                foreach ( $attachments as &$a )
                {
                    $to_attach[] = $a['id'];

                    $this->trellis->log( array( 'msg' => array( 'reply_attach', $a['original_name'], $t['subject'] ), 'type' => 'ticket', 'content_type' => 'reply', 'content_id' => $reply_id ) );
                }

                $this->trellis->func->attachments->assign( $to_attach, $reply_id );
            }
        }

        #=============================
        # Redirect
        #=============================

        if ( $this->trellis->input['ajax'] )
        {
            $this->trellis->skin->ajax_output( '1' );
        }
        else
        {
            $this->trellis->skin->redirect( array( 'act' => 'view', 'id' => $t['id'] ), '#r'. $reply_id );
        }
    }

    #=======================================
    # @ Do Edit Reply
    #=======================================

    private function do_edit_reply()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $this->trellis->input['message'] ) $this->trellis->skin->ajax_output( '0' );

        $r = $this->trellis->func->tickets->get_single( array(
                                                        'select'    => array(
                                                                                'r' => 'all',
                                                                                't' => array( 'did', 'subject', 'closed' ),
                                                                                'u' => array( array( 'signature' => 'usignature' ), 'sig_html' ),
                                                                                ),
                                                        'from'        => array( 'r' => 'replies' ),
                                                        'join'        => array( array( 'from' => array( 't' => 'tickets' ), 'where' => array( 'r' => 'tid', '=', 't' => 'id' ) ), array( 'from' => array( 'u' => 'users' ), 'where' => array( 'r' => 'uid', '=', 'u' => 'id' ) ) ),
                                                        'where'        => array( array( 'r' => 'id' ), '=', $this->trellis->input['id'] ),
                                                 )        );

        if ( ! $r ) $this->trellis->skin->ajax_output( '0' );

        if ( ! $this->check_perm( $r['tid'], $r['did'], 'er' ) && ( $r['uid'] != $this->trellis->user['id'] || ! $this->trellis->user['g_reply_edit'] ) ) $this->trellis->skin->ajax_output( '0' );

        if ( $r['closed'] ) $this->trellis->skin->ajax_output( '0' );

        #=============================
        # Edit Reply
        #=============================

        // RTE Permissions
        ( $this->trellis->input['html'] && $this->trellis->cache->data['settings']['ticket']['rte'] ) ? $html = 1 : $html = 0;

        $db_array = array(
                          'message'        => $this->trellis->input['message'],
                          'html'        => $html,
                         );

        $this->trellis->func->tickets->edit_reply( $db_array, $r['id'] );

        $this->trellis->log( array( 'msg' => array( 'reply_edited', $r['subject'] ), 'type' => 'ticket', 'content_type' => 'reply', 'content_id' => $r['id'] ) );

        #=============================
        # Do Output
        #=============================

        $routput_params = array( 'linkify' => 1 );

        if ( $this->trellis->input['html'] )
        {
            $routput_params['html'] = 1;
        }
        else
        {
            $routput_params['paragraphs'] = 1;
            $routput_params['nl2br'] = 1;
        }

        $rmessage = $this->trellis->prepare_output( $this->trellis->input['message'], $routput_params );

        $soutput_params = array( 'linkify' => 1 );

        if ( $r['sig_html'] )
        {
            $soutput_params['html'] = 1;
        }
        else
        {
            $soutput_params['paragraphs'] = 1;
            $soutput_params['nl2br'] = 1;
        }

        if ( $r['signature'] ) $rmessage .= $this->trellis->prepare_output( $r['usignature'], $soutput_params );

        $this->trellis->skin->ajax_output( $rmessage );
    }

    #=======================================
    # @ Do Delete Reply
    #=======================================

    private function do_delete_reply()
    {
        #=============================
        # Security Checks
        #=============================

        $r = $this->trellis->func->tickets->get_single( array(
                                                        'select'    => array(
                                                                                'r' => array( 'id', 'tid', 'uid', 'staff', 'secret', 'date' ),
                                                                                't' => array( 'did', 'subject', 'last_reply', 'last_reply_staff', 'closed' ),
                                                                                ),
                                                        'from'        => array( 'r' => 'replies' ),
                                                        'join'        => array( array( 'from' => array( 't' => 'tickets' ), 'where' => array( 'r' => 'tid', '=', 't' => 'id' ) ) ),
                                                        'where'        => array( array( 'r' => 'id' ), '=', $this->trellis->input['id'] ),
                                                 )        );

        if ( ! $r ) $this->trellis->skin->ajax_output( '0' );

        if ( ! $this->check_perm( $r['tid'], $r['did'], 'dr' ) && ( $r['uid'] != $this->trellis->user['id'] || ! $this->trellis->user['g_reply_delete'] ) ) $this->trellis->skin->ajax_output( '0' );

        if ( $r['closed'] ) $this->trellis->skin->ajax_output( '0' );

        #=============================
        # DELETE Reply
        #=============================

        $this->trellis->func->tickets->delete_reply( $r['id'], array( 'tid' => $r['tid'], 'secret' => $r['secret'], 'date' => $r['date'], 'last_reply' => $r['last_reply'], 'last_reply_staff' => $r['last_reply_staff'], 'staff' => $r['staff'] ) );

        $this->trellis->log( array( 'msg' => array( 'reply_deleted', $r['subject'] ), 'type' => 'ticket', 'level' => 2, 'content_type' => 'ticket', 'content_id' => $r['tid'] ) );

        #=============================
        # Do Output
        #=============================

        $this->trellis->skin->ajax_output( '1' );
    }

    #=======================================
    # @ Generate URL
    #=======================================

    private function generate_url($params=array())
    {
        $url = '<! TD_URL !>/admin.php?section=manage&amp;page=tickets';

        if ( ! isset( $params['sort'] ) ) $params['sort'] = $this->trellis->input['sort'];
        if ( ! isset( $params['order'] ) ) $params['order'] = $this->trellis->input['order'];
        if ( ! isset( $params['search'] ) ) $params['search'] = $this->trellis->input['search'];
        if ( ! isset( $params['field'] ) ) $params['field'] = $this->trellis->input['field'];
        if ( ! isset( $params['go_all'] ) ) $params['go_all'] = $this->trellis->input['go_all'];
        if ( ! isset( $params['noguest'] ) ) $params['noguest'] = $this->trellis->input['noguest'];
        if ( ! isset( $params['assigned'] ) ) $params['assigned'] = $this->trellis->input['assigned'];
        if ( ! isset( $params['unassigned'] ) ) $params['unassigned'] = $this->trellis->input['unassigned'];
        if ( ! isset( $params['escalated'] ) ) $params['escalated'] = $this->trellis->input['escalated'];
        if ( ! isset( $params['fstatus'] ) ) $params['fstatus'] = $this->trellis->input['fstatus'];
        if ( ! isset( $params['fdepart'] ) ) $params['fdepart'] = $this->trellis->input['fdepart'];
        if ( ! isset( $params['fpriority'] ) ) $params['fpriority'] = $this->trellis->input['fpriority'];
        if ( ! isset( $params['fflag'] ) ) $params['fflag'] = $this->trellis->input['fflag'];
        if ( ! isset( $params['cf'] ) ) $params['cf'] = $this->trellis->input['cf'];
        if ( ! isset( $params['st'] ) ) $params['st'] = $this->trellis->input['st'];

        if ( $params['sort'] ) $url .= '&amp;sort='. $params['sort'];

        if ( $params['order'] ) $url .= '&amp;order='. $params['order'];

        if ( $params['field'] == 'email' || $params['field'] == 'uemail' ) $params['search'] = urlencode( $params['search'] );
        if ( $params['search'] ) $url .= '&amp;search='. $params['search'];

        if ( $params['field'] ) $url .= '&amp;field='. $params['field'];

        if ( $params['go_all'] ) $url .= '&amp;go_all=1';

        if ( $params['noguest'] ) $url .= '&amp;noguest='. $params['noguest'];

        if ( $params['assigned'] ) $url .= '&amp;assigned='. $params['assigned'];

        if ( $params['unassigned'] ) $url .= '&amp;unassigned='. $params['unassigned'];

        if ( $params['escalated'] ) $url .= '&amp;escalated='. $params['escalated'];

        if ( is_array( $params['fstatus'] ) )
        {
            foreach( $params['fstatus'] as $sid )
            {
                $url .= '&amp;fstatus'. urlencode( '[]' ) .'='. $sid;
            }
        }

        if ( is_array( $params['fdepart'] ) )
        {
            foreach( $params['fdepart'] as $did )
            {
                $url .= '&amp;fdepart'. urlencode( '[]' ) .'='. $did;
            }
        }

        if ( is_array( $params['fpriority'] ) )
        {
            foreach( $params['fpriority'] as $pid )
            {
                $url .= '&amp;fpriority'. urlencode( '[]' ) .'='. $pid;
            }
        }

        if ( is_array( $params['fflag'] ) )
        {
            foreach( $params['fflag'] as $fid )
            {
                $url .= '&amp;fflag'. urlencode( '[]' ) .'='. $fid;
            }
        }

        if ( $params['cf'] ) $url .= '&amp;cf='. $params['cf'];

        if ( $params['st'] ) $url .= '&amp;st='. $params['st'];

        return $url;
    }

    #=======================================
    # @ Check Permission
    #=======================================

    private function check_perm($tid, $did, $type)
    {
        if ( $this->trellis->user['id'] == 1 ) return true;

        if ( ! $this->trellis->user['g_acp_depart_perm'][ $did ]['v'] )
        {
            if ( ! $this->assigned_override[ $tid ] )
            {
                if ( ! $a = $this->trellis->db->get_single( array( 'select' => array( 'id' ), 'from' => 'assign_map', 'where' => array( array( 'tid', '=', $tid ), array( 'uid', '=', $this->trellis->user['id'], 'and' ) ) ) ) ) return false;

                $this->assigned_override[ $tid ] = $a['id'];
            }
        }

        if ( $type == 'v' ) return true;

        if ( ! $this->trellis->user['g_acp_depart_perm'][ $did ][ $type ] ) return false;

        return true;
    }

    #=======================================
    # @ AJAX Do Add Assignment
    #=======================================

    private function ajax_add_assign()
    {
        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 't' => array( 'id', 'mask', 'did', 'subject', 'priority', 'message' ), 'u' => array( array( 'name' => 'uname' ) ) ), 'from' => array( 't' => 'tickets' ), 'join' => array( array( 'from' => array( 'u' => 'users' ), 'where' => array( 'u' => 'id', '=', 't' => 'uid' ) ) ) ), $this->trellis->input['tid'] ) ) $this->trellis->skin->ajax_output( '0' );

        if ( ! $this->check_perm( $t['id'], $t['did'], 'aa' ) && ( $this->trellis->input['uid'] != $this->trellis->user['id'] || ! $this->check_perm( $t['id'], $t['did'], 'as' ) ) ) $this->trellis->skin->ajax_output( '0' );

        if ( $this->trellis->user['id'] != 1 && ! $this->trellis->user['g_assign_outside'] )
        {
            $perms = unserialize( $this->trellis->cache->data['staff'][ $this->trellis->input['uid'] ]['g_acp_depart_perm'] );

            if ( ! $perms[ $t['did'] ]['v'] ) $this->trellis->skin->ajax_output( '00' ); # TODO: Does this permission check work?
        }

        if ( $uname = $this->trellis->func->tickets->add_assignment( $this->trellis->input['uid'], $t['id'], 0 , 1, 0, $t ) )
        {
            $this->trellis->log( array( 'msg' => array( 'ticket_assignadd', $uname, $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

            $this->trellis->skin->ajax_output( $uname );
        }
        else
        {
            $this->trellis->skin->ajax_output( '0' );
        }
    }

    #=======================================
    # @ AJAX Do Add Flag
    #=======================================

    private function ajax_add_flag()
    {
        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did', 'subject' ) ), $this->trellis->input['tid'] ) ) $this->trellis->skin->ajax_output( '0' );

        if ( ! $this->check_perm( $t['id'], $t['did'], 'v' ) ) $this->trellis->skin->ajax_output( '0' );

        if ( $fid = $this->trellis->func->tickets->add_flag( $this->trellis->input['fid'], $t['id'] ) )
        {
            $this->trellis->log( array( 'msg' => array( 'ticket_flagadd', $this->trellis->cache->data['flags'][ $this->trellis->input['fid'] ]['name'], $t['subject'] ), 'type' => 'ticket', 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

            $this->trellis->skin->ajax_output( '1' );
        }
        else
        {
            $this->trellis->skin->ajax_output( '0' );
        }
    }

    #=======================================
    # @ AJAX Do Delete Assignment
    #=======================================

    private function ajax_delete_assign()
    {
        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did', 'subject' ) ), $this->trellis->input['tid'] ) ) $this->trellis->skin->ajax_output( '0' );

        if ( ! $this->check_perm( $t['id'], $t['did'], 'aa' ) && ( $this->trellis->input['uid'] != $this->trellis->user['id'] || ! $this->check_perm( $t['id'], $t['did'], 'as' ) ) ) $this->trellis->skin->ajax_output( '0' );

        if ( $this->trellis->func->tickets->delete_assignment( $this->trellis->input['uid'], $t['id'] ) )
        {
            $this->trellis->db->construct( array( 'select' => array( 'name' ), 'from' => 'users', 'where' => array( 'id', '=', $this->trellis->input['uid'] ), 'limit'    => array( 0, 1 ) ) );
            $this->trellis->db->execute();

            if ( $this->trellis->db->get_num_rows() ) $u = $this->trellis->db->fetch_row();

            $this->trellis->log( array( 'msg' => array( 'ticket_assignrmv', $u['name'], $t['subject'] ), 'type' => 'ticket', 'level' => 2, 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

            $this->trellis->skin->ajax_output( '1' );
        }
        else
        {
            $this->trellis->skin->ajax_output( '0' );
        }
    }

    #=======================================
    # @ AJAX Do Delete Flag
    #=======================================

    private function ajax_delete_flag()
    {
        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did', 'subject' ) ), $this->trellis->input['tid'] ) ) $this->trellis->skin->ajax_output( '0' );

        if ( ! $this->check_perm( $t['id'], $t['did'], 'v' ) ) $this->trellis->skin->ajax_output( '0' );

        if ( $this->trellis->func->tickets->delete_flag( $this->trellis->input['fid'], $t['id'] ) )
        {
            $this->trellis->log( array( 'msg' => array( 'ticket_flagrmv', $this->trellis->cache->data['flags'][ $this->trellis->input['fid'] ]['name'], $t['subject'] ), 'type' => 'ticket', 'level' => 2, 'content_type' => 'ticket', 'content_id' => $t['id'] ) );

            $this->trellis->skin->ajax_output( '1' );
        }
        else
        {
            $this->trellis->skin->ajax_output( '0' );
        }
    }

    #=======================================
    # @ AJAX Do Save Notes
    #=======================================

    private function ajax_save_notes()
    {
        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did' ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->ajax_output( '0' );

        if ( ! $this->check_perm( $t['id'], $t['did'], 'v' ) ) $this->trellis->skin->ajax_output( '0' );

        if ( $this->trellis->func->tickets->edit( array( 'notes' => $this->trellis->input['notes'] ), $t['id'] ) )
        {
            $this->trellis->skin->ajax_output( '1' );
        }
        else
        {
            $this->trellis->skin->ajax_output( '0' );
        }
    }

    #=======================================
    # @ AJAX Do Save Defaults
    #=======================================

    private function ajax_save_defaults()
    {
        parse_str( str_replace( '&amp;', '&', $this->trellis->input['defaults'] ), $defaults );

        if ( $this->trellis->input['type'] == 'status' )
        {
            $db_array = array( 'dfilters_status' => serialize( $defaults['fstatus'] ) );
        }
        elseif ( $this->trellis->input['type'] == 'depart' )
        {
            $db_array = array( 'dfilters_depart' => serialize( $defaults['fdepart'] ) );
        }
        elseif ( $this->trellis->input['type'] == 'priority' )
        {
            $db_array = array( 'dfilters_priority' => serialize( $defaults['fpriority'] ) );
        }
        elseif ( $this->trellis->input['type'] == 'flag' )
        {
            $db_array = array( 'dfilters_flag' => serialize( $defaults['fflag'] ) );
        }

        $this->trellis->db->construct( array(
                                                   'update'    => 'users_staff',
                                                   'set'    => $db_array,
                                                   'where'    => array( 'uid', '=', $this->trellis->user['id'] ),
                                                   'limit'    => array( 1 ),
                                            )       );

        $this->trellis->db->execute();

        $this->trellis->skin->ajax_output( $this->trellis->db->get_affected_rows() );
    }

    #=======================================
    # @ AJAX Get Status
    #=======================================

    private function ajax_get_status()
    {
        if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'status', 'aua', 'closed' ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->ajax_output( '0' );

        if ( ! $t['aua'] && ! $t['closed'] )
        {
            $type = array( $this->trellis->cache->data['statuses'][ $t['status'] ]['type'], 5 );
        }
        else
        {
            $type = $this->trellis->cache->data['statuses'][ $t['status'] ]['type'];
        }

        $this->trellis->load_functions('drop_downs');

        $this->trellis->skin->ajax_output( $this->trellis->func->drop_downs->status_drop( array( 'type' => $type ) ) );
    }

    #=======================================
    # @ AJAX Get Reply Template
    #=======================================

    private function ajax_get_reply_template()
    {
        $this->trellis->load_functions('rtemplates');

        if ( ! $rt = $this->trellis->func->rtemplates->get_single_by_id( array( 'content_html', 'content_plaintext' ), $this->trellis->input['id'] ) ) $this->trellis->skin->ajax_output( '0' );

        if ( $this->trellis->input['html'] )
        {
            $message = $this->trellis->prepare_output( $rt['content_html'], array( 'html' => 1 ) );
        }
        else
        {
            $message = $rt['content_plaintext'];
        }

        $this->trellis->skin->ajax_output( $message );
    }

    #=======================================
    # @ AJAX Get Reply
    #=======================================

    private function ajax_get_reply()
    {
        $r = $this->trellis->func->tickets->get_single( array(
                                                        'select'    => array(
                                                                                'r' => 'all',
                                                                                't' => array( 'did', 'closed' ),
                                                                                ),
                                                        'from'        => array( 'r' => 'replies' ),
                                                        'join'        => array( array( 'from' => array( 't' => 'tickets' ), 'where' => array( 'r' => 'tid', '=', 't' => 'id' ) ) ),
                                                        'where'        => array( array( 'r' => 'id' ), '=', $this->trellis->input['id'] ),
                                                 )        );

        if ( ! $r ) $this->trellis->skin->ajax_output( '0' );

        if ( ! $this->check_perm( $r['tid'], $r['did'], 'er' ) && ( $r['uid'] != $this->trellis->user['id'] || ! $this->trellis->user['g_reply_edit'] ) ) $this->trellis->skin->ajax_output( '0' );

        if ( $r['closed'] ) $this->trellis->skin->ajax_output( '0' );

        $this->trellis->skin->ajax_output( $this->trellis->prepare_output( $r['message'], array( 'html' => $r['html'] ) ) );
    }

    #=======================================
    # @ Do Upload
    #=======================================

    private function do_upload()
    {
        #=============================
        # Security Checks
        #=============================

        if ( ! $this->trellis->settings['ticket']['attachments'] || ! $this->trellis->user['g_ticket_attach'] )
        {
            $this->trellis->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => $this->trellis->lang['error_upload_perm'] ) ) );
        }

        if ( ! $this->trellis->input['type'] ) $this->trellis->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => $this->trellis->lang['error_upload_perm'] ) ) );

        if ( $this->trellis->input['type'] == 'ticket' )
        {
            if ( ! $this->trellis->cache->data['departs'][ $this->trellis->input['id'] ]['allow_attach'] ) $this->trellis->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => $this->trellis->lang['error_upload_perm'] ) ) );
        }
        elseif ( $this->trellis->input['type'] == 'reply' )
        {
            if ( ! $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did', 'closed' ) ), $this->trellis->input['id'] ) ) $this->trellis->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => $this->trellis->lang['error_upload_perm'] ) ) );

            if ( ! $this->check_perm( $t['id'], $t['did'], 'r' ) ) $this->trellis->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => $this->trellis->lang['error_upload_perm'] ) ) );

            if ( $t['closed'] ) $this->trellis->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => $this->trellis->lang['error_upload_perm'] ) ) );

            if ( ! $this->trellis->cache->data['departs'][ $t['did'] ]['allow_attach'] ) $this->trellis->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => $this->trellis->lang['error_upload_perm'] ) ) );
        }

        #=============================
        # Upload File
        #=============================
        $fileAttachmentObj = new Attachment($this->trellis->database, $this->trellis);
        $this->trellis->load_functions('attachments');
        $ticketId = $this->trellis->input['tid'];
        //$file = $this->trellis->func->attachments->upload( $_FILES['Filedata'], array( 'content_type' => $this->trellis->input['type'] ), 'ajax' );
        $file = $fileAttachmentObj->upload($ticketId, $_FILES['files'], 'ajax');
        #=============================
        # Do Output
        #=============================

        //$this->trellis->skin->ajax_output( json_encode( array( 'success' => true, 'successmsg' => $this->trellis->lang['upload_success'], 'id' => $file['id'], 'name' => $file['name'] ) ) );

        echo json_encode( array( 'success' => true, 'successmsg' => $this->trellis->lang['upload_success'], 'id' => $file['id'], 'name' => $file['name'] ) );

    }

    #=======================================
    # @ Do Delete Upload
    #=======================================

    private function do_delete_upload()
    {
        $this->trellis->load_functions('attachments');

        #=============================
        # Security Checks
        #=============================

        if ( ! $u = $this->trellis->func->attachments->get_single_by_id( array( 'id', 'content_id', 'uid' ), $this->trellis->input['id'] ) ) $this->trellis->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => $this->trellis->lang['error_upload_delete'] ) ) );

        if ( $u['content_id'] || ( $u['uid'] != $this->trellis->user['id'] ) ) $this->trellis->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => $this->trellis->lang['error_upload_delete'] ) ) );

        #=============================
        # DELETE Upload
        #=============================

        if ( ! $this->trellis->func->attachments->delete( $u['id'] ) ) $this->trellis->skin->ajax_output( json_encode( array( 'error' => true ) ) );

        #=============================
        # Do Output
        #=============================

        $this->trellis->skin->ajax_output( json_encode( array( 'success' => true ) ) );

        exit();
    }

    #=======================================
    # @ Download Attachment
    #=======================================

    private function do_attachment()
    {
        $this->trellis->load_functions('attachments');

        #=============================
        # Security Checks
        #=============================

        if ( ! $a = $this->trellis->func->attachments->get_single_by_id( array( 'id', 'content_type', 'content_id' ), $this->trellis->input['id'] ) ) $this->trellis->skin->error('no_attachment');

        if ( $a['content_type'] == 'ticket' )
        {
            $t = $this->trellis->func->tickets->get_single_by_id( array( 'select' => array( 'id', 'did' ) ), $a['content_id'] );
        }
        else
        {
            $t = $this->trellis->func->tickets->get_single( array(
                                                                    'select'    => array( 't' => array( 'id', 'did' ) ),
                                                                    'from'        => array( 'r' => 'replies' ),
                                                                    'join'        => array( array( 'from' => array( 't' => 'tickets' ), 'where' => array( 'r' => 'tid', '=', 't' => 'id' ) ) ),
                                                                    'where'        => array( array( 'r' => 'id' ), '=', $a['content_id'] ),
                                                             )        );
        }

        if ( ! $t ) $this->trellis->skin->error('no_attachment');

        if ( ! $this->check_perm( $t['id'], $t['did'], 'v' ) ) $this->trellis->skin->error('no_perm');

        #=============================
        # Download Attachment
        #=============================

        if ( ! $this->trellis->func->attachments->download( $a['id'] ) ) $this->trellis->skin->error('no_attachment');

        $this->trellis->shut_down();

        exit();
    }

}

?>