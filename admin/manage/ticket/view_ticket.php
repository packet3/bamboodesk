<?php

    $this->output = "";
    $t = $this->ticket->fetch_ticket_by_ticket_id($this->trellis->user['id'], $this->trellis->input['id']);


    if ( ! $t ) $this->trellis->skin->error('no_ticket');

    #=============================
    # Permissions
    #=============================

    if ( ! $this->check_perm( $t['id'], $t['did'], 'v' ) )
    {
        if ( $params['new'] )
        {
            $this->trellis->skin->error('no_perm');
        }
        else
        {
            $this->trellis->skin->error('no_ticket');
        }
    }

    #=============================
    # Grab Replies
    #=============================

    $replies = $this->ticket->fetch_ticket_replies($t['id']);

    //admin.php?section=manage&page=tickets&act=view&id=TT7044ce09


    #=============================
    # Prepare Output
    #=============================

    $custom_fields = array();
    $custom_fields_html = '';

    #=============================
    # Menu Items
    #=============================

    $reply_action_list = '';

    $menu_items = array( array( 'arrow_back', '{lang.menu_back}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets' ) );

    if ( $t['closed'] )
    {
        if ( $this->check_perm( $t['id'], $t['did'], 'et' ) ) $menu_items[] = array( 'status', '{lang.menu_status}', '#', 'return confirmStatus('. $t['id'] .')' );
        if ( $this->check_perm( $t['id'], $t['did'], 'ro' ) ) $menu_items[] = array( 'arrow_step_over', '{lang.menu_reopen}', '#', 'return confirmReopen('. $t['id'] .')' );
    }
    else
    {
        if ( $this->check_perm( $t['id'], $t['did'], 'r' ) && ! $t['accepted'] ) $menu_items = array( array( 'tick', '{lang.menu_accept}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=doaccept&amp;id='. $t['id'] ) );
        if ( $this->check_perm( $t['id'], $t['did'], 'et' ) )
        {
            $menu_items[] = array( 'edit', '{lang.menu_edit}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=edit&amp;id='. $t['id'] );
            $menu_items[] = array( 'priority', '{lang.menu_priority}', '#', 'return confirmPriority('. $t['id'] .')' );
            if ( $t['accepted'] ) $menu_items[] = array( 'status', '{lang.menu_status}', '#', 'return confirmStatus('. $t['id'] .')' );
        }

        if ( $this->check_perm( $t['id'], $t['did'], 'es' ) )
        {
            if ( $this->trellis->cache->data['settings']['ticket']['escalate'] && $this->trellis->cache->data['departs'][ $t['did'] ]['escalate_enable'] && ! $t['escalated'] ) $menu_items[] = array( 'escalate', '{lang.menu_escalate}', '#', 'return confirmEscalate('. $t['id'] .')' );

            if ( $t['escalated'] ) $menu_items[] = array( 'rmvescalate', '{lang.menu_rmvescalate}', '#', 'return confirmRmvescalate('. $t['id'] .')' );

            $reply_action_list .= "<li><a href='#' id='reply_escalate'>{lang.reply_escalate}</a></li>";
        }

        if ( $this->check_perm( $t['id'], $t['did'], 'r' ) )
        {
            if ( $t['onhold'] )
            {
                $menu_items[] = array( 'hold_minus', '{lang.menu_rmvhold}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=dormvhold&amp;id='. $t['id'] );
            }
            else
            {
                $menu_items[] = array( 'hold', '{lang.menu_hold}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=dohold&amp;id='. $t['id'] );

                $reply_action_list .= "<li><a href='#' id='reply_hold'>{lang.reply_hold}</a></li>";
            }
        }
    }

    if ( $this->check_perm( $t['id'], $t['did'], 'mv' ) )
    {
        $menu_items[] = array( 'move', '{lang.menu_move}', '#', 'return confirmMove('. $t['id'] .')' );

        $reply_action_list .= "<li><a href='#' id='reply_move'>{lang.reply_move}</a></li>";
    }

    if ( ! $t['closed'] && $this->check_perm( $t['id'], $t['did'], 'c' ) )
    {
        $menu_items[] = array( 'frame_tick', '{lang.menu_close}', '#', 'return confirmClose('. $t['id'] .')' );

        $reply_action_list .= "<li><a href='#' id='reply_close'>{lang.reply_close}</a></li>";
    }

    if ( $this->check_perm( $t['id'], $t['did'], 'dt' ) ) $menu_items[] = array( 'circle_delete', '{lang.menu_delete}', '#', 'return confirmDeleteTicket('. $t['id'] .')' );

    if ( $this->trellis->cache->data['settings']['ticket']['track'] ) $menu_items[] = array( 'balloon', '{lang.menu_mark_unread}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=dountrack&amp;tid='. $t['id'] );

    $menu_items[] = array( 'print', '{lang.menu_print}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=print&amp;id='. $t['id'] );
    $menu_items[] = array( 'arrow_circle_refresh', '{lang.menu_refresh}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=view&amp;id='. $t['id'] );

    $this->trellis->skin->add_sidebar_menu( '{lang.menu_tickets_options}', $menu_items );

    #=============================
    # Custom Profile Fields
    #=============================

    $this->trellis->load_functions('cpfields');

    if ( $cpfields = $this->trellis->func->cpfields->grab( $t['ugroup'], 1, 1 ) )
    {
        $fdata = $this->trellis->func->cpfields->get_data( $t['uid'] );

        foreach( $cpfields as $fid => $f )
        {
            if ( $f['type'] == 'checkbox' )
            {
                $f['extra'] = unserialize( $f['extra'] );

                $checkbox_html = '';

                foreach( $f['extra'] as $key => $name )
                {
                    $checkbox_html .= $this->trellis->skin->checkbox( array( 'name' => 'cpf_'. $f['id'] .'_'. $key, 'title' => $name, 'value' => $fdata[ $f['id'] ][ $key ], 'disabled' => 1 ) ) .'&nbsp;&nbsp;&nbsp;';
                }

                $custom_fields[] = array( 'name' => $f['name'], 'data' => $checkbox_html );
            }
            elseif ( $f['type'] == 'dropdown' || $f['type'] == 'radio' )
            {
                $f['extra'] = unserialize( $f['extra'] );

                $custom_fields[] = array( 'name' => $f['name'], 'data' => $f['extra'][ $fdata[ $f['id'] ] ] );
            }
            else
            {
                $custom_fields[] = array( 'name' => $f['name'], 'data' => $fdata[ $f['id'] ] );
            }
        }
    }

    #=============================
    # Custom Department Fields
    #=============================

    $this->trellis->load_functions('cdfields');

    if ( $cdfields = $this->department->get_custom_department_fields_by_id($t['did']))
    {
        $fdata = $this->ticket->get_custom_fields_by_ticket_id( $t['id'] );

        foreach( $cdfields as $fid => $f )
        {
            if ( $f['type'] == 'checkbox' )
            {
                $f['extra'] = unserialize( $f['extra'] );

                $checkbox_html = '';

                foreach( $f['extra'] as $key => $name )
                {
                    $checkbox_html .= $this->trellis->skin->checkbox( array( 'name' => 'cdf_'. $f['id'] .'_'. $key, 'title' => $name, 'value' => $fdata[ $f['id'] ][ $key ], 'disabled' => 1 ) ) .'&nbsp;&nbsp;&nbsp;';
                }

                $custom_fields[] = array( 'name' => $f['name'], 'data' => $checkbox_html );
            }
            elseif ( $f['type'] == 'dropdown' || $f['type'] == 'radio' )
            {
                $f['extra'] = unserialize( $f['extra'] );

                $custom_fields[] = array( 'name' => $f['name'], 'data' => $f['extra'][ $fdata[ $f['id'] ] ] );
            }
            else
            {
                $custom_fields[] = array( 'name' => $f['name'], 'data' => $fdata[ $f['id'] ] );
            }
        }
    }

    #=============================
    # Combine Custom Fields
    #=============================

    $fields_current = 0;

    foreach( $custom_fields as $f )
    {
        $fields_current ++;

        if ( $fields_current & 1 ) $custom_fields_html .= "<tr>";

        if ( ! $f['data'] ) $f['data'] = '--';

        $custom_fields_html .= "<td class='cardcell-light'>{$f['name']}</td>
                                <td class='cardcell-dark'>{$f['data']}</td>";

        if ( ! $fields_current & 1 ) $custom_fields_html .= "</tr>";
    }

    if ( count( $custom_fields ) & 1 )
    {
        $custom_fields_html .= "<td class='cardcell-light'>&nbsp;</td>
                                <td class='cardcell-dark'>&nbsp;</td>
                            </tr>";
    }

    #=============================
    # Ticket Attachments
    #=============================

    if ( $t['attachments'] )
    {
        $this->trellis->load_functions('attachments');


        $ticketId = $t['id'];
        if ( $attachments =  $this->attachment->get_attachments_assigned_to_ticket($ticketId))
        {
            $attach_links = array();

            foreach ( $attachments as &$a )
            {
                $attach_links[] = "<a href='<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=attachment&amp;id={$a['id']}'>{$a['original_name']} (". $this->trellis->format_size( $a['size'] ) .")</a>";
            }
        }
    }

    #=============================
    # Ticket
    #=============================

    $this->trellis->load_functions('drop_downs');
    $this->trellis->load_functions('users');

    // Convert For Humans
    if ( $this->ticket->check_assignment( $this->trellis->user['id'], $t['id'] ) )
    {
        $t['priority_human'] = "<img src='<! TD_URL !>/images/priorities/{$this->trellis->cache->data['priorities'][ $t['priority'] ]['icon_assigned']}' alt='{$this->trellis->cache->data['priorities'][ $t['priority'] ]['name']}' class='prioritybox' style='vertical-align:middle' />&nbsp;&nbsp;<a href='". $this->generate_url( array( 'fpriority' => array( $t['priority'] ) ) ) ."'>{$this->trellis->cache->data['priorities'][ $t['priority'] ]['name']}</a>";
    }
    else
    {
        $t['priority_human'] = "<img src='<! TD_URL !>/images/priorities/{$this->trellis->cache->data['priorities'][ $t['priority'] ]['icon_regular']}' alt='{$this->trellis->cache->data['priorities'][ $t['priority'] ]['name']}' class='prioritybox' style='vertical-align:middle' />&nbsp;&nbsp;<a href='". $this->generate_url( array( 'fpriority' => array( $t['priority'] ) ) ) ."'>{$this->trellis->cache->data['priorities'][ $t['priority'] ]['name']}</a>";
    }

    $t['status_human'] = "<a href='". $this->generate_url( array( 'fstatus' => array( $t['status'] ) ) ) ."'>". $this->trellis->cache->data['statuses'][ $t['status'] ]['name'] ."</a>";

    $t['dname'] = "<a href='". $this->generate_url( array( 'fdepart' => array( $t['did'] ) ) ) ."'>". $this->trellis->cache->data['departs'][ $t['did'] ]['name'] ."</a>";

    $t['date_human'] = $this->trellis->td_timestamp( array( 'time' => $t['date'], 'format' => 'long' ) );

    $t['last_reply'] = $this->trellis->td_timestamp( array( 'time' => $t['last_reply'], 'format' => 'long' ) );

    if ( $t['escalated'] ) $t['escalated_icon'] = "<img src='<! IMG_DIR !>/icons/escalate.png' alt='E' style='vertical-align:middle;margin-bottom:2px' />&nbsp;";

    $toutput_params = array( 'linkify' => 1 );

    if ( $t['html'] )
    {
        $toutput_params['html'] = 1;
    }
    else
    {
        $toutput_params['paragraphs'] = 1;
        $toutput_params['nl2br'] = 1;
    }

    $t['message'] = $this->trellis->prepare_output( $t['message'], $toutput_params );

    if ( ! empty( $attach_links ) ) $t['message'] .= "<p class='attachments'>{lang.attachments}: ". implode( ', ', $attach_links ). "<p>";

    $this->output .= "<script type='text/javascript'>
                        //<![CDATA[
                        reply_first = false;
                        function confirmPriority(tid) {
                            dialogConfirm({
                                title: '{lang.dialog_priority_title}',
                                message: \"{lang.dialog_priority_msg} <select name='tpriority' id='tpriority'>". $this->trellis->func->drop_downs->priority_drop( array( 'select' => $t['priority'] ) ) ."</select>\",
                                yesButton: '{lang.dialog_priority_button}',
                                yesAction: function() { goToUrl('<! TD_URL !>/admin.php?section=manage&page=tickets&act=dopriority&id='+tid+'&pid='+new_priority) },
                                noButton: '{lang.cancel}',
                                beforeclose: function() { new_priority = $('#tpriority').val(); },
                                width: 350
                            }); return false;
                        }
                        function confirmStatus(tid) {
                            $.get('admin.php?section=manage&page=tickets&act=getstatus',
                                { id: tid },
                                function(data) {
                                    if (data != 0) {
                                        dialogConfirm({
                                            title: '{lang.dialog_status_title}',
                                            message: \"<p>{lang.dialog_status_msg_a}</p><p>{lang.dialog_status_msg_b} <select name='tstatus' id='tstatus'>\"+data+\"</select></p>\",
                                            yesButton: '{lang.dialog_status_button}',
                                            yesAction: function() { goToUrl('<! TD_URL !>/admin.php?section=manage&page=tickets&act=dostatus&id='+tid+'&sid='+new_status) },
                                            noButton: '{lang.cancel}',
                                            beforeclose: function() { new_status = $('#tstatus').val(); },
                                            width: 350
                                        });
                                    }
                                }); return false;
                        }
                        function confirmEscalate(tid) {
                            dialogConfirm({
                                title: '{lang.dialog_escalate_title}',
                                message: \"<p>{lang.dialog_escalate_msg}</p><p><input type='checkbox' id='tesclrassign' name='tesclrassign' value='1' checked='checked' /> <label for='tesclrassign'>{lang.dialog_clear_assignments}</label></p>\",
                                yesButton: '{lang.dialog_escalate_button}',
                                yesAction: function() {
                                    if ( reply_first ) {
                                        if ( ! addReply() ) return false;
                                    }
                                    goToUrl('<! TD_URL !>/admin.php?section=manage&page=tickets&act=doescalate&id='+tid+'&clrassign='+es_clear_assign);
                                },
                                noButton: '{lang.cancel}',
                                beforeclose: function() { es_clear_assign = ( $('#tesclrassign').is(':checked') ) ? 1 : 0; },
                                width: 350
                            }); return false;
                        }
                        function confirmRmvescalate(tid) {
                            dialogConfirm({
                                title: '{lang.dialog_rmvescalate_title}',
                                message: '{lang.dialog_rmvescalate_msg}',
                                yesButton: '{lang.dialog_rmvescalate_button}',
                                yesAction: function() { goToUrl('<! TD_URL !>/admin.php?section=manage&page=tickets&act=dormvescalate&id='+tid) },
                                noButton: '{lang.cancel}',
                                width: 350
                            }); return false;
                        }
                        function confirmMove(tid) {
                            dialogConfirm({
                                title: '{lang.dialog_move_title}',
                                message: \"<p>{lang.dialog_move_msg_a}</p><p>{lang.dialog_move_msg_b} <select name='tmvdid' id='tmvdid'>". $this->trellis->func->drop_downs->dprt_drop( 0, $t['did'], 1 ) ."</select></p><p><input type='checkbox' id='tmvclrassign' name='tmvclrassign' value='1' checked='checked' /> <label for='tmvclrassign'>{lang.dialog_clear_assignments}</label></p>\",
                                yesButton: '{lang.dialog_move_button}',
                                yesAction: function() {
                                    if ( reply_first ) {
                                        if ( ! addReply() ) return false;
                                    }
                                    goToUrl('<! TD_URL !>/admin.php?section=manage&page=tickets&act=domove&id='+tid+'&did='+new_did+'&clrassign='+mv_clear_assign);
                                },
                                noButton: '{lang.cancel}',
                                beforeclose: function() { new_did = $('#tmvdid').val(); mv_clear_assign = ( $('#tmvclrassign').is(':checked') ) ? 1 : 0; },
                                width: 350
                            }); return false;
                        }
                        function confirmClose(tid) {
                            dialogConfirm({
                                title: '{lang.dialog_close_title}',
                                message: \"<p>{lang.dialog_close_msg}</p><p><input type='checkbox' name='reopen' id='reopen' value='1' checked='checked' /> <label for='reopen'>{lang.dialog_allow_reopen_msg_a}</label></p><p>{lang.dialog_allow_reopen_msg_b}</p>\",
                                yesButton: '{lang.dialog_close_button}',
                                yesAction: function() {
                                    if ( reply_first ) {
                                        if ( ! addReply() ) return false;
                                    }
                                    goToUrl('<! TD_URL !>/admin.php?section=manage&page=tickets&act=doclose&id='+tid+'&reopen='+allow_reopen);
                                },
                                beforeclose: function() { allow_reopen = ( $('#reopen').is(':checked') ) ? 1 : 0; },
                                noButton: '{lang.cancel}',
                                width: 380
                            }); return false;
                        }
                        function confirmReopen(tid) {
                            dialogConfirm({
                                title: '{lang.dialog_reopen_title}',
                                message: '{lang.dialog_reopen_msg}',
                                yesButton: '{lang.dialog_reopen_button}',
                                yesAction: function() { goToUrl('<! TD_URL !>/admin.php?section=manage&page=tickets&act=doreopen&id='+tid) },
                                noButton: '{lang.cancel}'
                            }); return false;
                        }
                        function confirmDeleteTicket(tid) {
                            dialogConfirm({
                                title: '{lang.dialog_delete_ticket_title}',
                                message: '{lang.dialog_delete_ticket_msg}',
                                yesButton: '{lang.dialog_delete_ticket_button}',
                                yesAction: function() { goToUrl('<! TD_URL !>/admin.php?section=manage&page=tickets&act=dodel&id='+tid) },
                                noButton: '{lang.cancel}'
                            }); return false;
                        }
                        function confirmDeleteReply(rid) {
                            dialogConfirm({
                                title: '{lang.dialog_delete_reply_title}',
                                message: '{lang.dialog_delete_reply_msg}',
                                yesButton: '{lang.dialog_delete_reply_button}',
                                yesAction: function() { inlineReplyDelete(rid); },
                                noButton: '{lang.cancel}'
                            }); return false;
                        }
                        function askKeepHold() {
                            dialogConfirm({
                                title: '{lang.dialog_keep_hold_title}',
                                message: '{lang.dialog_keep_hold_msg}',
                                yesButton: '{lang.dialog_keep_hold_button_yes}',
                                yesAction: function() {
                                    $('#keep_hold').val(2);
                                    $('#add_reply').trigger('submit');
                                },
                                noButton: '{lang.dialog_keep_hold_button_no}',
                                noAction: function() {
                                    $('#keep_hold').val(1);
                                    $('#add_reply').trigger('submit');
                                },
                            }); return false;
                        }";

    if ( $t['onhold'] )
    {
        $this->output .= "
                        $(function() {
                            $('#add_reply').submit(function() {
                                if ( $('#keep_hold').val() == 0 ) {
                                    return askKeepHold();
                                }
                                else {
                                    return true;
                                }
                            });
                        });";
    }

    if ( $this->trellis->settings['ticket']['track'] )
    {
        $unread_found = false;

        if ( $t['track_date'] < $t['date'] )
        {
            $unread_found = true;
            $unread = true;
        }
        else
        {
            $unread = false;
        }
    }

    $this->output .= "
                        //]]>
                        </script>
                        <input type='hidden' id='tid' name='tid' value='{$t['id']}' />
                        ". $this->trellis->skin->start_ticket_details( '{lang.ticket_num}'. $t['id'] .': '. $t['subject'] ) ."
                        <table width='100%' border='0' cellspacing='0' cellpadding='0'>
                            <tr>
                                <td class='cardcell-light' width='20%'>{lang.ticket_id}</td>
                                <td class='cardcell-dark' width='30%'>{$t['escalated_icon']}{$t['id']}</td>
                                <td class='cardcell-light' width='20%'>{lang.ticket_mask}</td>
                                <td class='cardcell-dark' width='30%'>{$t['mask']}</td>
                            </tr>
                            <tr>
                                <td class='cardcell-light'>{lang.subject}</td>
                                <td class='cardcell-dark'>{$t['subject']}</td>
                                <td class='cardcell-light'>{lang.replies}</td>
                                <td class='cardcell-dark'>{$t['replies']}</td>
                            </tr>
                            <tr>
                                <td class='cardcell-light'>{lang.priority}</td>
                                <td class='cardcell-dark'>{$t['priority_human']}</td>
                                <td class='cardcell-light'>{lang.last_reply}</td>
                                <td class='cardcell-dark'>{$t['last_reply']}</td>
                            </tr>
                            <tr>
                                <td class='cardcell-light'>{lang.department}</td>
                                <td class='cardcell-dark'>{$t['dname']}</td>
                                <td class='cardcell-light'>{lang.last_replier}</td>
                                <td class='cardcell-dark'>". ( ( $t['last_uid'] ) ? "<a href='<! TD_URL !>/admin.php?section=manage&amp;page=users&amp;act=view&amp;id={$t['last_uid']}'>{$t['last_uname']}</a>" : "<a href='<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;search=". urlencode( $t['email'] ) ."&amp;field=email' title='{$t['ipadd']}'>{$t['gname']} ({lang.guest})</a>" ) ."</td>
                            </tr>
                            <tr>
                                <td class='cardcell-light'>{lang.submitted_on}</td>
                                <td class='cardcell-dark'>{$t['date_human']}</td>
                                <td class='cardcell-light'>{lang.status}</td>
                                <td class='cardcell-dark'>{$t['status_human']}</td>
                            </tr>
                            <tr>
                                <td class='cardcell-light'>{lang.submitted_by}</td>
                                <td class='cardcell-dark'>". ( ( $t['uid'] ) ? "<a href='<! TD_URL !>/admin.php?section=manage&amp;page=users&amp;act=view&amp;id={$t['uid']}' title='{$t['ipadd']}'>{$t['uname']}</a>" : "<a href='<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;search=". urlencode( $t['email'] ) ."&amp;field=email' title='{$t['ipadd']}'>{$t['gname']} ({lang.guest})</a>" ) ."</td>
                                <td class='cardcell-light'>{lang.satisfaction}</td>
                                <td class='cardcell-dark'><img src='<! IMG_DIR !>/star_full.gif' width='12' height='12' alt='Star' /><img src='<! IMG_DIR !>/star_full.gif' width='12' height='12' alt='Star' /><img src='<! IMG_DIR !>/star_full.gif' width='12' height='12' alt='Star' /><img src='<! IMG_DIR !>/star_half.gif' width='12' height='12' alt='Star' /><img src='<! IMG_DIR !>/star_empty.gif' width='12' height='12' alt='Star' /></td>
                            </tr>
                            <tr>
                                <td class='cardcell-light'>{lang.ticket_email}</td>
                                <td class='cardcell-dark'><a href='". $this->generate_url( array( 'search' => $t['email'], 'field' => 'email' ) ) ."'>{$t['email']}</a></td>
                                <td class='cardcell-light'>{lang.user_email}</td>
                                <td class='cardcell-dark'>". ( ( $t['uid'] ) ? "<a href='". $this->generate_url( array( 'search' => $t['uemail'], 'field' => 'uemail' ) ) ."'>{$t['uemail']}</a>" : "<a href='". $this->generate_url( array( 'search' => $t['email'], 'field' => 'email' ) ) ."'>{$t['email']}</a>" ) ."</td>
                            </tr>
                            ". $custom_fields_html ."
                        </table>
                        ". $this->trellis->skin->end_ticket_details() ."
                        ". ( ( $unread ) ? "<a id='unread'></a>" : '' ) ."
                        <div id='ticketroll'>
                            ". $this->trellis->skin->group_title( ( ( $t['track_date'] < $t['date'] ) ? "<img src='<! IMG_DIR !>/icons/balloon.png' alt='*' title='{lang.unread}' style='vertical-align:top;' />&nbsp;" : '' ). '{lang.ticket_content}' ) ."
                            <div class='rollstart'>
                                {$t['message']}
                            </div>";

    #=============================
    # Replies
    #=============================

    $reply_untrack = ( $this->trellis->settings['ticket']['track'] ) ? 1 : 0;

    if ( ! empty( $replies ) )
    {
        foreach( $replies as &$r )
        {
            if ( $r['secret'] )
            {
                $rclass = 'staffonly';
            }
            elseif ( $r['staff'] )
            {
                $rclass = 'staff';
            }
            else
            {
                $rclass = 'customer';
            }

            $r['date_human'] = $this->trellis->td_timestamp( array( 'time' => $r['date'], 'format' => 'long' ) );

            $routput_params = array( 'linkify' => 1 );

            if ( $r['html'] )
            {
                $routput_params['html'] = 1;
            }
            else
            {
                $routput_params['paragraphs'] = 1;
                $routput_params['nl2br'] = 1;
            }

            $r['message'] = $this->trellis->prepare_output( $r['message'], $routput_params );

            if ( $r['signature'] )
            {
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

                if ( ! $this->parsed_sigs[ $r['uid'] ] ) $this->parsed_sigs[ $r['uid'] ] = $this->trellis->prepare_output( $r['usignature'], $soutput_params ); # CHECK: Let's not parse the signature over and over again

                $r['message'] .= '<p>'. $this->parsed_sigs[ $r['uid'] ] .'</p>';
            }

            // Tracking
            if ( $this->trellis->cache->data['settings']['ticket']['track'] )
            {
                if ( ( ! $unread_found ) && ( $t['track_date'] < $r['date'] ) )
                {
                    $unread_found = true;
                    $unread = true;
                }
                else
                {
                    $unread = false;
                }
            }

            if ( $unread ) $this->output .= "<a id='unread'></a>";

            $this->output .= "<div id='r{$r['id']}' class='reply'>
                                <div class='bar{$rclass}'>";

            $reply_edit = 0;
            $reply_delete = 0;
            $reply_javascript_html = '';

            if ( $r['html'] ) $reply_javascript_html = 'Html';

            if ( $this->check_perm( $t['id'], $t['did'], 'er' ) || ( $this->trellis->user['g_reply_edit'] && $r['uid'] == $this->trellis->user['id'] ) ) $reply_edit = 1;
            if ( $this->check_perm( $t['id'], $t['did'], 'dr' ) || ( $this->trellis->user['g_reply_delete'] && $r['uid'] == $this->trellis->user['id'] ) ) $reply_delete = 1;

            if ( $reply_untrack || $reply_edit || $reply_delete ) $this->output .= "<div class='barright'>";

            if ( $reply_untrack ) $this->output .= "<span id='rmark_". $r['id'] ."'><a href='<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=dountrack&amp;rid={$r['id']}'><img src='<! IMG_DIR !>/icons/balloon.png' alt='{lang.mark_unread}' />{lang.mark_unread}</a></span>";

            if ( $reply_edit ) $this->output .= "<span id='redit_". $r['id'] ."' style='cursor:pointer' onclick='inlineReplyEdit{$reply_javascript_html}(". $r['id'] .")'><img src='<! IMG_DIR !>/icons/page_edit.png' alt='{lang.edit}' />{lang.edit}</span><span id='rsave_". $r['id'] ."' style='display:none;cursor:pointer' onclick='inlineReplySave{$reply_javascript_html}(". $r['id'] .")'><img src='<! IMG_DIR !>/icons/page_edit.png' alt='{lang.save_edit}' />{lang.save_edit}</span>";

            if ( $reply_delete )
            {
                $this->output .= "<span id='rdelete_". $r['id'] ."' style='cursor:pointer' onclick='return confirmDeleteReply(". $r['id'] .")'><img src='<! IMG_DIR !>/icons/page_delete.png' alt='{lang.delete}' />{lang.delete}</span>";
            }

            if ( $reply_edit || $reply_delete ) $this->output .= "</div>";

            #=============================
            # Reply Attachments
            #=============================

            if ( $r['attachments'] )
            {
                $this->trellis->load_functions('attachments');

                if ( $attachments = $this->attachment->fetch_attachments_assigned_to_ticket_reply($r['id']))
                {
                    $r['message'] .= "<p class='attachments'>{lang.attachments}: ";

                    $attach_links = array();

                    foreach ( $attachments as &$a )
                    {
                        $attach_links[] = "<a href='<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=attachment&amp;id={$a['id']}'>{$a['original_name']} (". $this->trellis->format_size( $a['size'] ) .")</a>";
                    }

                    $r['message'] .= implode( ', ', $attach_links ). "<p>";
                }
            }

            if ( $this->trellis->settings['ticket']['track'] && ( $t['track_date'] < $r['date'] ) ) $this->output .= "<img src='<! IMG_DIR !>/icons/balloon.png' alt='*' title='{lang.unread}' style='vertical-align:top;' />&nbsp;";

            $this->output .= "<a href='<! TD_URL !>/admin.php?section=manage&amp;page=users&amp;act=view&amp;id={$r['uid']}' title='{$r['ipadd']}'><strong>{$r['uname']}</strong></a> -- {$r['date_human']}</div>
                                <div class='roll{$rclass}' id='rm{$r['id']}'>
                                    {$r['message']}
                                </div>
                        </div>";
        }
    }

    #=============================
    # Form
    #=============================

    if ( ! $t['closed'] && $this->check_perm( $t['id'], $t['did'], 'r') )
    {
        if ( $this->trellis->user['rte_enable'] && $this->trellis->cache->data['settings']['ticket']['rte'] )
        {
            $this->output .= $this->trellis->skin->tinymce_js( 'message' );

            $html = 1;
        }
        else
        {
            $html = 0;
        }

        if ( $params['reply_error'] )
        {
            $this->output .= $this->trellis->skin->error_wrap( '{lang.error_'. $params['reply_error'] .'}' );
        }

        ( $this->trellis->user['sig_auto'] ) ? $sig_checked = " checked='checked'" : $sig_checked = '';

        $reply_action_button = '';

        if ( $reply_action_list ) $reply_action_list = "<div id='reply_action_list' class='fdrop' style='display: none;'><ul>". $reply_action_list ."</ul></div>";

        $rt_list = '';

        foreach( $this->trellis->cache->data['rtemplates'] as $rt )
        {
            $rt_list .= "<li id='rt{$rt['id']}' onclick='addRT({$rt['id']})'>{$rt['name']}</li>";
        }

        if ( ! $t['aid'] && ( $this->check_perm( $t['id'], $t['did'], 'aa' ) || $this->check_perm( $t['id'], $t['did'], 'as' ) ) )
        {
            $assign_checkbox = "<input type='checkbox' name='assign_to_me' id='assign_to_me' value='1' style='margin-bottom:2px;'";

            if ( $this->trellis->user['auto_assign'] ) $assign_checkbox .= " checked='checked'";

            $assign_checkbox .= " />&nbsp;<label for='assign_to_me'>{lang.assign_to_me}</label>&nbsp;&nbsp;";
        }

        $this->output .= "<form action='<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=doaddreply&amp;id={$t['id']}' id='add_reply' method='post'>
                                <input type='hidden' id='html' name='html' value='{$html}' />
                                <input type='hidden' id='keep_hold' name='keep_hold' value='0' />
                                <div class='slatebox'>{lang.post_a_reply}</div>
                                <div class='rollpost'>
                                ". $this->trellis->skin->textarea( array( 'name' => 'message', 'cols' => 80, 'rows' => 8, 'width' => '98%', 'height' => '200px' ) ) ."
                                </div>
                                ";

        if ( $this->trellis->settings['ticket']['attachments'] && $this->trellis->user['g_ticket_attach'] && $this->trellis->cache->data['departs'][ $t['did'] ]['allow_attach'] ) {
            $this->output .= "<div class='option1'>
                                    ". $this->trellis->skin->uploadify_js( 'upload_file', array( 'section' => 'manage', 'page' => 'tickets', 'act' => 'doupload', 'type' => 'reply', 'id' => $t['id'] ), array( 'multi' => true, 'list' => true ) ) ."
                                </div>";
        }


        $this->output .= "<div class='formtail' style='text-align:left'><span>". $this->trellis->skin->submit_button( 'reply', '{lang.button_add_reply}' ) . $this->trellis->skin->button( 'reply_action', '{lang.select_action}' ) ."</span>" . $reply_action_list ."&nbsp;{$assign_checkbox}<input type='checkbox' name='signature' id='signature' value='1' style='margin-bottom:2px;'{$sig_checked} />&nbsp;<label for='signature'>{lang.append_signature}</label>&nbsp;&nbsp;<input type='checkbox' name='secret' id='secret' value='1' style='margin-bottom:2px;' />&nbsp;<label for='secret'>{lang.staff_only_reply}</label><div style='float:right;'><button id='add_rt' name='add_rt' type='button' class='buttontinydrop'>{lang.reply_templates}&nbsp;</button><div id='add_rt_block' class='fakedrop ui-corner-all'><ul>{$rt_list}</ul></div></div></div>
                                </form>";
    }

    #=============================
    # History
    #=============================

    $this->output .= "<div class='slatebox'><a href='<! TD_URL !>/admin.php?section=tools&amp;page=logs&amp;type=ticket&amp;content_type=ticket&amp;content_id={$t['id']}'>{lang.recent_ticket_history}</a></div>
                            <div class='rollhistory'>
                                <table width='100%' cellpadding='0' cellspacing='0'>";


    $arguments['id'] = $t['id'];
    $sql_where = "";
    if ( empty( $replies ) )
    {

        $sql_where = "WHERE l.type = 'ticket' AND l.content_type = 'ticket' AND l.content_type = :id";
        //$sql_where = array( array( array( 'l' => 'type' ), '=', 'ticket' ), array( array( 'l' => 'content_type' ), '=', 'ticket', 'and' ), array( array( 'l' => 'content_id' ), '=', $t['id'], 'and' ) );
    }
    else
    {
        $inFilter = $this->trellis->database->buildFilterString(array_keys( $replies ), "IN");
        $sql_where = "WHERE l.type = 'ticket AND l.content_type = 'ticket' AND l.content_type = :id AND l.type = 'ticket'
                          AND l.content_type = 'reply' AND l.content_id ".$inFilter;
        //$sql_where = array( array( array( array( 'l' => 'type' ), '=', 'ticket' ),
        // array( array( 'l' => 'content_type' ), '=', 'ticket', 'and' ), array( array( 'l' => 'content_id' ),
        // '=', $t['id'], 'and' ) ), array( array( array( 'l' => 'type' ), '=', 'ticket' ),
        // array( array( 'l' => 'content_type' ), '=', 'reply', 'and' ),
        // array( array( 'l' => 'content_id' ), 'in', array_keys( $replies ), 'and' ), 'or' ) );
    }

    $sql = "SELECT l.*, u.name AS uname FROM td_logs l ".$sql_where." ORDER BY l.date DESC, l.id DESC LIMIT 0, 15";
//        $this->trellis->db->construct( array(
//                                                   'select'    => array(
//                                                                        'l' => 'all',
//                                                                        'u' => array( array( 'name' => 'uname' ) ),
//                                                                        ),
//                                                   'from'    => array( 'l' => 'logs' ),
//                                                   'join'    => array( array( 'from' => array( 'u' => 'users' ), 'where' => array( 'l' => 'uid', '=', 'u' => 'id' ) ) ),
//                                                   'where'    => $sql_where,
//                                                   'order'    => array( 'date' => array( 'l' => 'desc' ), 'id' => array( 'l' => 'desc' ) ),
//                                                   'limit'    => array( 0, 15 ),
//                                            )       );


    //$this->trellis->db->execute();

//        while( $l = $this->trellis->database->runSql($sql, $arguments)->fetchAll())
//        {
//            $l['date'] = $this->trellis->td_timestamp( array( 'time' => $l['date'], 'format' => 'short' ) );
//
//            if ( $l['level'] == 2 )
//            {
//                $fontcolor_start = "<font color='#790000'>";
//                $fontcolor_end = "<font color='#790000'>";
//            }
//            else
//            {
//                $fontcolor_start = "";
//                $fontcolor_end = "";
//            }
//
//            $this->output .= "<tr>
//                                <td class='slatecell-light' width='38%'>{$fontcolor_start}{$l['action']}{$fontcolor_end}</td>
//                                <td class='slatecell-dark' width='16%' style='font-weight:normal'>{$fontcolor_start}{$l['date']}{$fontcolor_end}</td>
//                                <td class='slatecell-light' width='19%'><a href='<! TD_URL !>/admin.php?section=manage&amp;page=users&amp;act=view&amp;id={$l['uid']}'>{$fontcolor_start}{$l['uname']}{$fontcolor_end}</a></td>
//                                <td class='slatecell-light' width='17%' style='font-weight:normal'>{$fontcolor_start}{$l['ipadd']}{$fontcolor_end}</td>
//                            </tr>";
//        }

    $this->output .= "</table>
                        </div>
                        </div>
                        <script type='text/javascript'>
                        //<![CDATA[
                        $(function() {
                            $('#add_flag').bind('click', function () {
                                $('#add_flag_block').toggle();
                            });
                            $('#add_assign').bind('click', function () {
                                $('#add_assign_block').toggle();
                            });
                            if ( $('#add_reply').length > 0 ) {
                                $('#add_rt').bind('click', function () {
                                    $('#add_rt_block').toggle();
                                });
                                //$('#reply_action').bind('click', function () {
                                //    $('#reply_action_list').toggle();
                                //});
                                $('#reply_action').menu({
                                    content: $('#reply_action_list').html(),
                                    positionOpts: {
                                        posX: 'left',
                                        posY: 'bottom',
                                        offsetX: -($('#reply_action').offset().left - $('#reply').offset().left),
                                        offsetY: 0,
                                        directionH: 'right',
                                        directionV: 'down',
                                        detectH: true,
                                        detectV: true
                                    }
                                });
                            }
                            $('#add_assign_input').autocomplete('<! TD_URL !>/admin.php?act=lookup&type=staff&assign=". $t['did'] ."', {
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
                            $('#add_assign_input').result(function(event, data, formatted) {
                                $('#add_assign_id').val(data['value']);
                            });
                            $('#save_notes').bind('click', function () {
                                $.post('admin.php?section=manage&page=tickets&act=donotes&id='+$('#tid').val(),
                                    { notes: $('#notes').val() },
                                    function(data) {
                                        if (data != 0) $('#save_notes_status').stop(true).fadeIn().animate({opacity: 1.0}, {duration: 2000}).fadeOut('slow');
                                    });
                            });
                            $('#reply_escalate').bind('click', function () {
                                reply_first = true; confirmEscalate($('#tid').val());
                            });
                            $('#reply_hold').bind('click', function () {
                                if( addReply() ) goToUrl('<! TD_URL !>/admin.php?section=manage&page=tickets&act=dohold&id='+$('#tid').val());
                            });
                            $('#reply_move').bind('click', function () {
                                reply_first = true; confirmMove($('#tid').val());
                            });
                            $('#reply_close').bind('click', function () {
                                reply_first = true; confirmClose($('#tid').val());
                            });

                            $('#reply').button().click(function() {
                            })
                            .next()
                            .button({
                                text: false,
                                icons: {
                                    primary: 'ui-icon-triangle-1-s'
                                }
                            })
                            .parent()
                            .buttonset();
                            $('#add_assign').button({ icons: { primary: 'ui-icon-triangle-1-s' } });
                            $('#add_flag').button({ icons: { primary: 'ui-icon-triangle-1-s' } });
                            $('#add_rt').button({ icons: { primary: 'ui-icon-triangle-1-s' } });
                        });
                        //]]>
                        </script>";

    #=============================
    # Assignments
    #=============================

    $assign_items = '';
    $assign_used = 0;

    if ( $assignments = $this->ticket->fetch_ticket_assignments( $t['id'] ) )
    {
        foreach( $assignments as $a )
        {
            $assign_items .= "<li id='a{$a['uid']}'><a href='<! TD_URL !>/admin.php?section=manage&amp;page=users&amp;act=view&amp;id={$a['uid']}'>{$a['uname']}</a>";

            if ( $this->check_perm( $t['id'], $t['did'], 'aa' ) || ( $a['uid'] == $this->trellis->user['id'] && $this->check_perm( $t['id'], $t['did'], 'as' ) ) ) $assign_items .= "<img src='<! IMG_DIR !>/icons/cross.png' alt='X' id='ai{$a['uid']}' class='listdel' onclick='delAssign({$a['uid']},{$t['id']})' />";

            $assign_items .= "</li>";
        }

        $assign_used = 1;
    }

    $assign_items .= "<li id='not_assigned'";

    if ( $assign_used ) $assign_items .= " style='display:none'";

    $assign_items .= "><em>{lang.not_assigned}</em></li>";

    if ( $this->check_perm( $t['id'], $t['did'], 'aa' ) )
    {
        $assign_items .= "<li><button id='add_assign' name='add_assign' type='button' class='buttontinydrop' />{lang.button_add_assign}</button>
                            <div id='add_assign_block' class='fakedrop ui-corner-all'><ul><li style='cursor:default'><input name='add_assign_id' id='add_assign_id' type='hidden' value='0' /><input name='add_assign_input' id='add_assign_input' type='text' value='' size='22' />&nbsp;&nbsp;<input name='add_assign_button' id='add_assign_button' type='submit' value='{lang.button_add}' class='buttonmini' onclick='addAssign({$t['id']})' /></li><li style='cursor:default'><em>{lang.add_assign_instructions}</em></li></ul></div></li>";
    }
    elseif ( $this->check_perm( $t['id'], $t['did'], 'as' ) )
    {
        $assign_items .= "<li><input name='add_assign_id' id='add_assign_id' type='hidden' value='". $this->trellis->user['id'] ."' /><input name='add_assign_button' id='add_assign_button' type='submit' value='{lang.button_assign_myself}' class='buttonmini' onclick='addAssign({$t['id']})' /></li>";
    }

    $this->trellis->skin->add_sidebar_list_custom( '{lang.ticket_assignments}', $assign_items, 'assign_list' );

    #=============================
    # Flags
    #=============================

    if ( ! empty( $this->trellis->cache->data['flags'] ) )
    {
        $flags_used = array();
        $flags_items = '';
        $flags_list = '';
        $flags_add_list = 0;

        if ( $flags = $this->trellis->func->tickets->get_flags( $t['id'] ) )
        {
            foreach( $flags as $f )
            {
                $flags_used[ $f['fid'] ] = 1;

                $flags_items .= "<li id='f{$f['fid']}'><a href='". $this->generate_url( array( 'fflag' => array( $f['fid'] ) ) ) ."'><img src='<! TD_URL !>/images/flags/{$f['icon']}' alt='{$f['name']}' class='flagicon' />{$f['name']}</a><img src='<! IMG_DIR !>/icons/cross.png' alt='X' id='fi{$f['fid']}' class='listdel' onclick='delFlag({$f['fid']},{$t['id']})' /></li>";
            }
        }

        $flags_items .= "<li id='no_flags'";

        if ( ! empty( $flags_used ) ) $flags_items .= " style='display:none'";

        $flags_items .= "><em>{lang.no_flags}</em></li>";

        foreach( $this->trellis->cache->data['flags'] as $f )
        {
            if ( ! $flags_used[ $f['id'] ] )
            {
                if ( ! $flags_add_list ) $flags_add_list = 1;

                $flags_list .= "<li id='af{$f['id']}' onclick='addFlag({$f['id']},{$t['id']})'><img src='<! TD_URL !>/images/flags/{$f['icon']}' alt='{$f['name']}' class='flagicon' />{$f['name']}</li>";
            }
        }

        $flags_list .= "<li id='noaddflags'";

        if ( $flags_add_list ) $flags_list .= " style='display:none'";

        $flags_list .= "><em>{lang.no_flags_to_add}</em></li>";

        $flags_items .= "<li><button id='add_flag' name='add_flag' type='button' class='buttontinydrop' />{lang.button_add_flag}</button><div id='add_flag_block' class='fakedrop ui-corner-all'><ul>{$flags_list}</ul></div></li>";

        $this->trellis->skin->add_sidebar_list_custom( '{lang.ticket_flags}', $flags_items, 'flags_list' );
    }

    #=============================
    # Tracking
    #=============================

    if ( $this->trellis->settings['ticket']['track'] ) $this->ticket->track( $t['id'], $this->trellis->user['id'], $t['track_date'] );

    #=============================
    # Do Output
    #=============================

    $notes_items = array(
        "<textarea id='notes' name='notes' cols='4' rows='6' style='width:99%' class='notesbox'>{$t['notes']}</textarea>",
        "<input name='save_notes' id='save_notes' type='submit' value='{lang.button_save_notes}' class='buttonmini' /> <span id='save_notes_status' class='ajax_update_button'>{lang.saved}</span>",
    );

    $this->trellis->skin->add_sidebar_list( '{lang.ticket_notes}', $notes_items );

    $this->trellis->skin->add_skin_javascript( 'autocomplete.js' );

    $this->trellis->skin->add_output( $this->output );

    $this->trellis->skin->do_output();

