<?php

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
