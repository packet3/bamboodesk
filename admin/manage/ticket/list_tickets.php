<?php

    $this->output = "";

    #=============================
    # Table Columns
    #=============================
    $columns = array( 'id' => '3%', 'mask' => '6%', 'subject' => '30%', 'priority' => '13%', 'department' => '18%', 'date' => '17%',
        'reply' => '17%', 'submitter' => '13%','status' => '13%' );


    $lang_columns = array(
        'id'            => '{lang.id}',
        'mask'	        => '{lang.mask}',
        'subject'	    => '{lang.subject}',
        'priority'	    => '{lang.priority}',
        'department'	=> '{lang.department}',
        'date'	        => '{lang.submitted}',
        'reply'	        => '{lang.last_reply}',
        'replystaff'	=> '{lang.last_staff_reply}',
        'lastuname'	    => '{lang.last_replier}',
        'submitter'	    => '{lang.submitter}',
        'email'	        => '{lang.ticket_email}',
        'uemail'	    => '{lang.user_email}',
        'replies'	    => '{lang.replies}',
        'status'        => '{lang.status}',
    );

    $dark_columns = array( 'subject', 'date', 'last_reply' );
    $normal_columns = array( 'dname', 'date', 'last_reply' );

    $sql_select = array();
    $sql_columns = array();
    $sql_join = array();

    #=============================
    # Default Sort
    #=============================

    if ( ! $this->trellis->input['sort'] )
    {
        if ( isset( $columns[ $this->trellis->user['sort_tm'] ] ) ) $this->trellis->input['sort'] = $this->trellis->user['sort_tm'];
    }

    $column_sort = array( 'reply', 'date', 'mask', 'replystaff', 'subject', 'priority', 'department', 'mname', 'lastuname', 'email', 'status', 'replies' );

    for ( $i = 0; ! $this->trellis->input['sort'] && $i < count( $column_sort ); $i++ )
    {
        if ( $columns[ $column_sort[ $i ] ] ) $this->trellis->input['sort'] = $column_sort[ $i ];
    }

    if ( ! $this->trellis->input['sort'] ) $this->trellis->input['sort'] = 'id';

    if ( ! $this->trellis->input['order'] )
    {
        ( $this->trellis->user['order_tm'] ) ? $this->trellis->input['order'] = 'desc' : $this->trellis->input['order'] = 'asc';
    }

    #=============================
    # Prepare Output
    #=============================

    $this->output .= "<div id='ticketroll'>
                        ". $this->trellis->skin->start_group_table( '{lang.tickets_list}' ) ."
                        <tr>";

    #=============================
    # Sort
    #=============================

    $user_table_join = 0;

    foreach( $columns as $name => $width )
    {
        if ( $name == $this->trellis->input['sort'] )
        {
            if ( $this->trellis->input['order'] == 'desc' )
            {
                $link_order = 'asc';
                $img_order = '&nbsp;<img src="<! IMG_DIR !>/arrow_down.gif" alt="{lang.down}" />';
                $sql_order = 'desc';

            }
            else
            {
                $link_order = 'desc';
                $img_order = '&nbsp;<img src="<! IMG_DIR !>/arrow_up.gif" alt="{lang.up}" />';
                $sql_order = 'asc';
            }

            if ( $name == 'department' )
            {
                $this->build_sql_order_by("d.name $sql_order");
            }
            elseif ( $name == 'priority' )
            {
                $this->build_sql_order_by("p.position $sql_order");
            }
            elseif ( $name == 'reply' )
            {
                $this->build_sql_order_by("t.last_reply $sql_order");
            }
            elseif ( $name == 'replystaff' )
            {
                $this->build_sql_order_by("t.last_reply_staff $sql_order");
            }
            elseif ( $name == 'lastuname' )
            {
                $this->build_sql_order_by("ulr.name $sql_order");
            }
            elseif ( $name == 'status' )
            {
                $this->build_sql_order_by("s.name $sql_order");
            }
            elseif ( $name == 'submitter' )
            {
                $this->build_sql_order_by("u.name $sql_order");
            }
            elseif ( strpos( $name, 'cfd' ) === 0 || strpos( $name, 'cfp' ) === 0 )
            {
                $this->build_sql_order_by("$name.data $sql_order");
            }
            else
            {
                $this->build_sql_order_by("t.$name $sql_order");
            }
        }
        else
        {
            $link_order = 'asc';
            $img_order = '';
        }

        if ( strpos( $name, 'cfd' ) === 0 )
        {
            $lang_columns[ $name ] = $this->trellis->cache->data['dfields'][ substr( $name, 3) ]['name'];
        }
        elseif ( strpos( $name, 'cfp' ) === 0 )
        {
            $lang_columns[ $name ] = $this->trellis->cache->data['pfields'][ substr( $name, 3) ]['name'];
        }

        $this->output .= "<th class='bluecellthin-th' width='{$width}%' align='left'><a href='". $this->generate_url( array( 'sort' => $name, 'order' => $link_order ) ) ."'>{$lang_columns[ $name ]}{$img_order}</a></th>";


    }

    $this->output .= "<th class='bluecellthin-th' width='1%' align='center'><input name='checkall' id='checkall' type='checkbox' value='1' /></th>
                        </tr>";

    #=============================
    # Filter
    #=============================

    if ( ! $this->trellis->input['cf'] )
    {
        if ( ! is_array( $this->trellis->input['fstatus'] ) )
        {
            $this->trellis->input['fstatus'] = unserialize( $this->trellis->user['dfilters_status'] );
        }

        if ( ! is_array( $this->trellis->input['fdepart'] ) )
        {
            $this->trellis->input['fdepart'] = unserialize( $this->trellis->user['dfilters_depart'] );
        }

        if ( ! is_array( $this->trellis->input['fpriority'] ) )
        {
            $this->trellis->input['fpriority'] = unserialize( $this->trellis->user['dfilters_priority'] );
        }

        if ( ! is_array( $this->trellis->input['fflag'] ) )
        {
            $this->trellis->input['fflag'] = unserialize( $this->trellis->user['dfilters_flag'] );
        }

        if ( ! $this->trellis->input['assigned'] && $this->trellis->user['dfilters_assigned'] ) $this->trellis->input['assigned'] = $this->trellis->user['id'];
    }

    if ( $this->trellis->input['go_all'] )
    {
        unset( $this->trellis->input['fstatus'] );
        unset( $this->trellis->input['fdepart'] );
        unset( $this->trellis->input['fpriority'] );
        unset( $this->trellis->input['fflag'] );
    }

    $filters = array();
    $sql_select[] = 'a.uid AS auid'; // Get Assigned


    if ( $this->trellis->input['noguest'] )
    {
        $filters[] = 't.uid != 0';

    }

    if ( $this->trellis->input['assigned'] )
    {
        //$this->trellis->user['id'];
        $filters[] = 't.uid = '.$this->trellis->input['assigned'];

    }

    if ( $this->trellis->input['unassigned'] )
    {

        $filters[] = 't.uid is null';

    }

    if ( $this->trellis->input['escalated'] )
    {
        $filters[] = 't.escalated = 1';

    }

    if ( $this->trellis->input['field'] )
    {
        $strict_fields = array( 'id', 'mask', 'uid' );

        $user_fields = array ( 'uname' => 'name', 'uemail' => 'email' );

        if ( in_array( $this->trellis->input['field'], $strict_fields ) && ! $this->trellis->input['loose'] )
        {
            $filters[] = "t.".$this->trellis->input['field']." = ".$this->trellis->input['search'];

        }
        elseif ( $user_fields[ $this->trellis->input['field'] ] )
        {
            $filters[] = "u.".$user_fields[ $this->trellis->input['field'] ]." LIKE %".addcslashes( $this->trellis->input['search'], '%_' )."%";

        }
        else
        {
            $filters[] = "t.".$this->trellis->input['field']." LIKE %".addcslashes( $this->trellis->input['search'], '%_' )."%";

        }
    }

    if ( is_array( $this->trellis->input['fstatus'] ) )
    {
        $filterString = $this->trellis->database->buildFilterString($this->trellis->input['fstatus'], "IN");
        $filters[] = "t.status " .$filterString;

    }

    if ( is_array( $this->trellis->input['fdepart'] ) )
    {
        $filterString = $this->trellis->database->buildFilterString($this->trellis->input['fdepart'], "IN");
        $filters[] = "t.did " .$filterString;

    }

    if ( is_array( $this->trellis->input['fpriority'] ) )
    {
        $filterString = $this->trellis->database->buildFilterString($this->trellis->input['fpriority'], "IN");
        $filters[] = "t.priority " .$filterString;


    }

    if ( is_array( $this->trellis->input['fflag'] ) )
    {
        foreach ( $this->trellis->input['fflag'] as $fid => $ff )
        {
            $filters[] = "f$fid.fid = $ff";

        }
    }

    #=============================
    # Permissions
    #=============================

    if ( $this->trellis->user['id'] != 1 )
    {
        $perms = array();

        if ( is_array( $this->trellis->user['g_acp_depart_perm'] ) )
        {
            foreach( $this->trellis->user['g_acp_depart_perm'] as $did => $dperm )
            {
                if ( $dperm['v'] ) $perms[] = $did;
            }
        }

        if ( empty( $perms ) ) $perms[] = 0;

        $filters[] = "t.did IN ('".$perms."') OR a.uid = ".$this->trellis->user['id'];

    }

    #=============================
    # Grab Tickets
    #=============================
    $totalFilters = count($filters);
    $counter = 0;
    foreach( $filters as $fdata )
    {
        $counter ++;
        if($totalFilters > 1)
        {
            $this->sqlWhere.= "$fdata AND ";
            if($counter == $totalFilters)
            {
                //remove AND from end of last string.
                $this->sqlWhere = substr($this->sqlWhere, 0, -4);
            }
        } else {
            $this->sqlWhere .= $fdata;
        }




    }

    $sql_select[] = 't.escalated';

    if ( ! in_array( 't.id', $sql_select ) )
    {
        $sql_select[] = 't.id';
    }

    $ticket_rows = '';



    $sql = "SELECT T.id, mask, subject, TP.name AS pname,TP.icon_regular,  TD.name AS dname, T.date, last_reply, TS.name AS status
                FROM bamboodesk.td_tickets T
                INNER JOIN bamboodesk.td_priorities TP
                ON priority = TP.id
                INNER JOIN bamboodesk.td_departments TD
                ON T.did = TD.id
                INNER JOIN bamboodesk.td_statuses TS
                ON T.status = TS.id
                WHERE 1 = 1
                AND ".$this->sqlWhere;



    $tickets = $this->trellis->database->runSql($sql)->fetchAll();


    if ( ! $tickets )
    {
        $ticket_rows .= "<tr><td class='bluecell-light' colspan='". ( count( $columns ) + 1 ) ."'><strong><a href='<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=add'>{lang.no_tickets}</a></strong></td></tr>";
    }
    else
    {
        foreach( $tickets as $t )
        {
            if ( $t['date'] ) $t['date'] = $this->trellis->td_timestamp( array( 'time' => $t['date'], 'format' => 'short' ) );
            if ( $t['last_reply'] ) $t['last_reply'] = $this->trellis->td_timestamp( array( 'time' => $t['last_reply'], 'format' => 'short' ) );
            ( $t['last_reply_staff'] ) ? $t['last_reply_staff'] = $this->trellis->td_timestamp( array( 'time' => $t['last_reply_staff'], 'format' => 'short' ) ) : $t['last_reply_staff'] = '';

            if ( $t['uname'] ) $t['uname'] = "<a href='<! TD_URL !>/admin.php?section=manage&amp;page=users&amp;act=view&amp;id={$t['uid']}'>{$t['uname']}</a>";
            if ( $t['gname'] ) $t['uname'] = "<a href='". $this->generate_url( array( 'search' => $t['email'], 'field' => 'email', 'fstatus' => '', 'fdepart' => '', 'fpriority' => '', 'fflag' => '', 'cf' => 0 ) ) ."'>{$t['gname']} ({lang.guest})</a>";
            if ( $t['last_uname'] ) $t['last_uname'] = "<a href='<! TD_URL !>/admin.php?section=manage&amp;page=users&amp;act=view&amp;id={$t['last_uid']}'>{$t['last_uname']}</a>";
            if ( isset( $t['last_uid'] ) && ! $t['last_uid'] ) $t['last_uname'] = "<a href='". $this->generate_url( array( 'search' => $t['email'], 'field' => 'email', 'fstatus' => '', 'fdepart' => '', 'fpriority' => '', 'fflag' => '', 'cf' => 0 ) ) ."'>{$t['gname']} ({lang.guest})</a>";

            if ( $t['email'] ) $t['email'] = "<a href='". $this->generate_url( array( 'search' => $t['email'], 'field' => 'email', 'fstatus' => '', 'fdepart' => '', 'fpriority' => '', 'fflag' => '', 'cf' => 0 ) ) ."'>{$t['email']}</a>";
            if ( $t['uemail'] ) $t['uemail'] = "<a href='". $this->generate_url( array( 'search' => $t['uemail'], 'field' => 'uemail', 'fstatus' => '', 'fdepart' => '', 'fpriority' => '', 'fflag' => '', 'cf' => 0 ) ) ."'>{$t['uemail']}</a>";

            if ( $t['dname'] ) $t['dname'] = "<a href='". $this->generate_url( array( 'fdepart' => array( $t['did'] ), 'fstatus' => '', 'fpriority' => '', 'fflag' => '', 'cf' => 0 ) ) ."'>{$t['dname']}</a>";

            $ticket_rows .= "<tr>";

            foreach( $columns as $name => $width )
            {
                if ( $name == 'department' )
                {
                    $name = 'dname';
                }
                elseif ( $name == 'priority' )
                {
                    $name = 'pname';
                }
                elseif ( $name == 'reply' )
                {
                    $name = 'last_reply';
                }
                elseif ( $name == 'replystaff' )
                {
                    $name = 'last_reply_staff';
                }
                elseif ( $name == 'submitter' )
                {
                    $name = 'uname';
                }
                elseif ( $name == 'lastuname' )
                {
                    $name = 'last_uname';
                }
                elseif ( $name == 'status' )
                {
                    ( $t['status'] ) ? $name = 'status' : $name = 'name';
                }

                ( in_array( $name, $dark_columns ) ) ? $dark = 1 : $dark = 0;
                ( in_array( $name, $normal_columns ) ) ? $normal = 1 : $normal = 0;

                $ticket_rows .= "<td class='bluecellthin-";

                if ( $dark )
                {
                    $ticket_rows .= "dark";
                }
                else
                {
                    $ticket_rows .= "light";
                }

                $ticket_rows .= "'";

                if ( $normal ) $ticket_rows .= " style='font-weight: normal'";

                $ticket_rows .= ">";

                if ( $name == 'id' || $name == 'mask' ) $ticket_rows .= "<a href='<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=view&amp;id={$t['mask']}'>";

                if ( $name == 'id' ) $ticket_rows .= "<strong>";

                if ( $name == 'subject' )
                {
                    if ( $this->trellis->cache->data['settings']['ticket']['track'] && ( $t['track_date'] < $t['last_reply_all'] ) ) $ticket_rows .= "<a href='<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=view&amp;id={$t['id']}#unread'><img src='<! IMG_DIR !>/icons/balloon_small.png' alt='*' title='{lang.unread}' style='vertical-align:middle;margin-bottom:2px' /></a>&nbsp;"; // NULL < 0
                    $ticket_rows .= "<a href='<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=view&amp;id={$t['id']}'>";
                    if ( $t['escalated'] ) $ticket_rows .= "<img src='<! IMG_DIR !>/icons/escalate.png' alt='E' style='vertical-align:middle;margin-bottom:2px' />&nbsp;";
                }

                if ( $name == 'pname' )
                {
                    if ( $t['auid'] == $this->trellis->user['id'] )
                    {
                        $ticket_rows .= "<img src='<! TD_URL !>/images/priorities/{$t['icon_assigned']}' alt='{$t['pname']}' class='prioritybox' />&nbsp;&nbsp;";
                    }
                    else
                    {
                        $ticket_rows .= "<img src='<! TD_URL !>/images/priorities/{$t['icon_regular']}' alt='{$t['pname']}' class='prioritybox' />&nbsp;&nbsp;";
                    }

                    $ticket_rows .= "<a href='". $this->generate_url( array( 'fpriority' => array( $t['priority'] ), 'fstatus' => '', 'fdepart' => '', 'fflag' => '', 'cf' => 0 ) ) ."'>";
                }

                if ( $name == 'abbr' || $name == 'name' ) $ticket_rows .= "<a href='". $this->generate_url( array( 'fstatus' => array( $t['status'] ), 'fdepart' => '', 'fpriority' => '', 'fflag' => '', 'cf' => 0 ) ) ."'>";

                if ( strpos( $name, 'cfd' ) === 0 || strpos( $name, 'cfp' ) === 0 )
                {
                    if ( strpos( $name, 'cfd' ) === 0 )
                    {
                        $f = $this->trellis->cache->data['dfields'][ substr( $name, 3 ) ];
                    }
                    elseif ( strpos( $name, 'cfp' ) === 0 )
                    {
                        $f = $this->trellis->cache->data['pfields'][ substr( $name, 3 ) ];
                    }

                    if ( $f['type'] == 'dropdown' || $f['type'] == 'radio' )
                    {
                        $f['extra'] = unserialize( $f['extra'] );

                        $t[ $name ] = $f['extra'][ $t[ $name ] ];
                    }
                }

                $ticket_rows .= $t[ $name ];

                if ( $name == 'id' ) $ticket_rows .= "</strong>";

                if ( $name == 'id' || $name == 'subject' || $name == 'pname' || $name == 'abbr' || $name == 'name' ) $ticket_rows .= "</a>";

                $ticket_rows .= "</td>";
            }

            $ticket_rows .= "<td class='bluecellthin-light'><input name='mat[]' id='mat_{$t['id']}' type='checkbox' value='{$t['id']}' class='matcb' /></td>
                                </tr>";
        }
    }

    #=============================
    # Do Output
    #=============================

    $page_links = $this->trellis->page_links( array(
        'total'        => count( $tickets ),
        'per_page'    => 15,
        'url'        => $this->generate_url( array( 'st' => 0 ) ),
    ) );

    $this->output .= $ticket_rows ."
                        <tr>
                            <td class='bluecellthin-th-pages' colspan='3' align='left'>". $page_links ."</td>
                            <td class='bluecellthin-th' colspan='". ( count( $columns ) - 2 ) ."' align='right'>Mass-Action</td>
                        </tr>
                        ". $this->trellis->skin->end_group_table() ."
                        </div>";

    $menu_items = array(
        array( 'circle_plus', '{lang.menu_add}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=add' ),
        array( 'mail_pencil', '{lang.menu_etemplates}', '<! TD_URL !>/admin.php?section=look&amp;page=emails' ),
        array( 'balloon', '{lang.menu_mark_all_read}', '<! TD_URL !>/admin.php?section=manage&amp;page=tickets&amp;act=dotrackall' ),
        array( 'settings', '{lang.menu_settings}', '<! TD_URL !>/admin.php?section=tools&amp;page=settings&amp;act=edit&amp;group=ticket' ),
    );

    $status_items = array();

    foreach( $this->trellis->cache->data['statuses'] as $s )
    {
        $status_items[ $s['id'] ] = "<input name='fstatus[]' id='fs_". $s['id'] ."' type='checkbox' class='fstatus' value='". $s['id'] ."'";

        if ( is_array( $this->trellis->input['fstatus'] ) )
        {
            if ( in_array( $s['id'], $this->trellis->input['fstatus'] ) ) $status_items[ $s['id'] ] .= " checked='checked'";
        }

        $status_items[ $s['id'] ] .= " />&nbsp;&nbsp;<label for='fs_". $s['id'] ."'>". $s['name'] ."</label>";
    }

    $status_items[] = "<input name='save_status' id='save_status' type='submit' value='{lang.button_save_default}' class='buttontiny' /> <span id='save_status_status' class='ajax_update_button'>{lang.saved}</span>";

    $depart_items = array();

    foreach( $this->trellis->cache->data['departs'] as $d )
    {
        if ( is_array( $perms ) && ( ! in_array( $d['id'], $perms ) ) ) continue;

        $depart_items[ $d['id'] ] = "<input name='fdepart[]' id='fd_". $d['id'] ."' type='checkbox' class='fdepart' value='". $d['id'] ."'";

        if ( is_array( $this->trellis->input['fdepart'] ) )
        {
            if ( in_array( $d['id'], $this->trellis->input['fdepart'] ) ) $depart_items[ $d['id'] ] .= " checked='checked'";
        }

        $depart_items[ $d['id'] ] .= " />&nbsp;&nbsp;<label for='fd_". $d['id'] ."'>". $d['name'] ."</label>";
    }

    $depart_items[] = "<input name='save_depart' id='save_depart' type='submit' value='{lang.button_save_default}' class='buttontiny' /> <span id='save_depart_status' class='ajax_update_button'>{lang.saved}</span>";

    $priority_items = array();

    foreach( $this->trellis->cache->data['priorities'] as $p )
    {
        $priority_items[ $p['id'] ] = "<input name='fpriority[]' id='fp_". $p['id'] ."' type='checkbox' class='fpriority' value='". $p['id'] ."'";

        if ( is_array( $this->trellis->input['fpriority'] ) )
        {
            if ( in_array( $p['id'], $this->trellis->input['fpriority'] ) ) $priority_items[ $p['id'] ] .= " checked='checked'";
        }

        $priority_items[ $p['id'] ] .= " />&nbsp;&nbsp;<label for='fp_". $p['id'] ."'><img src='<! TD_URL !>/images/priorities/{$p['icon_regular']}' alt='{$p['name']}' class='prioritybox' style='margin-right:8px' />". $p['name'] ."</label>";
    }

    $priority_items[] = "<input name='save_priority' id='save_priority' type='submit' value='{lang.button_save_default}' class='buttontiny' /> <span id='save_priority_status' class='ajax_update_button'>{lang.saved}</span>";

    $flag_items = array();

    if ( ! empty( $this->trellis->cache->data['flags'] ) )
    {
        foreach( $this->trellis->cache->data['flags'] as $f )
        {
            $flag_items[ $f['id'] ] = "<input name='fflag[]' id='ff_". $f['id'] ."' type='checkbox' class='fflag' value='". $f['id'] ."'";

            if ( is_array( $this->trellis->input['fflag'] ) )
            {
                if ( in_array( $f['id'], $this->trellis->input['fflag'] ) ) $flag_items[ $f['id'] ] .= " checked='checked'";
            }

            $flag_items[ $f['id'] ] .= " />&nbsp;&nbsp;<label for='ff_". $f['id'] ."'><img src='<! TD_URL !>/images/flags/{$f['icon']}' alt='{$f['name']}' class='flagicon' />". $f['name'] ."</label>";
        }

        $flag_items[] = "<input name='save_flag' id='save_flag' type='submit' value='{lang.button_save_default}' class='buttontiny' /> <span id='save_flag_status' class='ajax_update_button'>{lang.saved}</span>";
    }

    $this->trellis->skin->preserve_input = 1;

    $other_items = array();

    $other_items[0] = "<form action='". $this->generate_url( array( 'search' => '', 'field' => '' ) ) ."' method='post'><input name='search' id='search' type='text' value='". $this->trellis->input['search'] ."' style='width:95%;margin-bottom:5px' /><br />";

    $search_fields = array( 'id' => '{lang.id}', 'mask' => '{lang.mask}', 'subject' => '{lang.subject}', 'uid' => '{lang.user_id}', 'uname' => '{lang.username}', 'email' => '{lang.ticket_email}', 'uemail' => '{lang.user_email}' );

    $other_items[0] .= $this->trellis->skin->drop_down( 'field', $search_fields );

    $other_items[0] .= "<br /><input name='go' id='go' type='submit' value='{lang.search}' class='buttontiny' style='margin-top:5px' />&nbsp;&nbsp;<input name='go_all' id='go_all' type='submit' value='{lang.all}' class='buttontiny' style='margin-top:5px' /></form>";

    $other_items[1] = "<input name='noguest' id='noguest' type='checkbox' value='1'";

    if ( $this->trellis->input['noguest'] ) $other_items[1] .= " checked='checked'";

    $other_items[1] .= " />&nbsp;&nbsp;<label for='noguest'>{lang.noguest_tickets}</label>";

    $other_items[2] = "<input name='assigned' id='assigned' type='checkbox' value='". $this->trellis->user['id'] ."'";

    if ( $this->trellis->input['assigned'] == $this->trellis->user['id'] ) $other_items[2] .= " checked='checked'";

    $other_items[2] .= " />&nbsp;&nbsp;<label for='assigned'>{lang.my_assigned_tickets}</label>";

    $other_items[3] = "<input name='unassigned' id='unassigned' type='checkbox' value='1'";

    if ( $this->trellis->input['unassigned'] ) $other_items[3] .= " checked='checked'";

    $other_items[3] .= " />&nbsp;&nbsp;<label for='unassigned'>{lang.unassigned_tickets}</label>";

    $other_items[4] = "<input name='escalated' id='escalated' type='checkbox' value='1'";

    if ( $this->trellis->input['escalated'] ) $other_items[4] .= " checked='checked'";

    $other_items[4] .= " />&nbsp;&nbsp;<label for='escalated'>{lang.escalated_tickets}</label>";

    $this->trellis->skin->add_sidebar_menu( '{lang.menu_tickets_options}', $menu_items );
    $this->trellis->skin->add_sidebar_list( '{lang.filter_by_status}', $status_items, 'filter_status' );
    $this->trellis->skin->add_sidebar_list( '{lang.filter_by_department}', $depart_items, 'filter_depart' );
    $this->trellis->skin->add_sidebar_list( '{lang.filter_by_priority}', $priority_items, 'filter_priority' );

    if ( ! empty( $flag_items ) ) $this->trellis->skin->add_sidebar_list( '{lang.filter_by_flag}', $flag_items, 'filter_flag' );

    $this->trellis->skin->add_sidebar_list( '{lang.other_filters}', $other_items, 'filter_other' );

    $this->output .= $this->trellis->skin->toggle_js();

    $this->output .= "<script type='text/javascript'>
                        //<![CDATA[
                        $('.fstatus').bind('click', function() {
                            goToUrl('". str_replace( '&amp;', '&', $this->generate_url( array( 'fstatus' => '', 'cf' => 1, 'go_all' => 0 ) ) ) ."&'+ $('.fstatus').serialize() );
                        });
                        $('.fdepart').bind('click', function() {
                            goToUrl('". str_replace( '&amp;', '&', $this->generate_url( array( 'fdepart' => '', 'cf' => 1, 'go_all' => 0 ) ) ) ."&'+ $('.fdepart').serialize() );
                        });
                        $('.fpriority').bind('click', function() {
                            goToUrl('". str_replace( '&amp;', '&', $this->generate_url( array( 'fpriority' => '', 'cf' => 1, 'go_all' => 0 ) ) ) ."&'+ $('.fpriority').serialize() );
                        });
                        $('.fflag').bind('click', function() {
                            goToUrl('". str_replace( '&amp;', '&', $this->generate_url( array( 'fflag' => '', 'cf' => 1, 'go_all' => 0 ) ) ) ."&'+ $('.fflag').serialize() );
                        });
                        $('#noguest').bind('click', function() {
                            goToUrl('". str_replace( '&amp;', '&', $this->generate_url( array( 'noguest' => '', 'cf' => 1 ) ) ) ."&'+ $('#noguest').serialize() );
                        });
                        $('#assigned').bind('click', function() {
                            goToUrl('". str_replace( '&amp;', '&', $this->generate_url( array( 'assigned' => '', 'unassigned' => '', 'cf' => 1 ) ) ) ."&'+ $('#assigned').serialize() );
                        });
                        $('#unassigned').bind('click', function() {
                            goToUrl('". str_replace( '&amp;', '&', $this->generate_url( array( 'assigned' => '', 'unassigned' => '', 'cf' => 1 ) ) ) ."&'+ $('#unassigned').serialize() );
                        });
                        $('#escalated').bind('click', function() {
                            goToUrl('". str_replace( '&amp;', '&', $this->generate_url( array( 'escalated' => '', 'cf' => 1 ) ) ) ."&'+ $('#escalated').serialize() );
                        });
                        $('#checkall').bind('click', function() {
                            $('.matcb').attr('checked', this.checked);
                        });
                        $('#save_status').bind('click', function () {
                            $.post('admin.php?section=manage&page=tickets&act=dodefaults&type=status',
                                { defaults: $('.fstatus').serialize() },
                                function(data) {
                                    if (data == 1) $('#save_status_status').stop(true).fadeIn().animate({opacity: 1.0}, {duration: 2000}).fadeOut('slow');
                                });
                        });
                        $('#save_depart').bind('click', function () {
                            $.post('admin.php?section=manage&page=tickets&act=dodefaults&type=depart',
                                { defaults: $('.fdepart').serialize() },
                                function(data) {
                                    if (data == 1) $('#save_depart_status').stop(true).fadeIn().animate({opacity: 1.0}, {duration: 2000}).fadeOut('slow');
                                });
                        });
                        $('#save_priority').bind('click', function () {
                            $.post('admin.php?section=manage&page=tickets&act=dodefaults&type=priority',
                                { defaults: $('.fpriority').serialize() },
                                function(data) {
                                    if (data == 1) $('#save_priority_status').stop(true).fadeIn().animate({opacity: 1.0}, {duration: 2000}).fadeOut('slow');
                                });
                        });
                        $('#save_flag').bind('click', function () {
                            $.post('admin.php?section=manage&page=tickets&act=dodefaults&type=flag',
                                { defaults: $('.fflag').serialize() },
                                function(data) {
                                    if (data == 1) $('#save_flag_status').stop(true).fadeIn().animate({opacity: 1.0}, {duration: 2000}).fadeOut('slow');
                                });
                        });
                        //]]>
                        </script>";

    $this->trellis->skin->add_output( $this->output );

    $this->trellis->skin->do_output();

