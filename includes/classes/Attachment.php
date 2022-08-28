<?php

namespace BambooDesk;

class Attachment
{
    private $db;
    private $bamboo;

    public function __construct(Database $db, object $bamboo)
    {
        $this->db = $db;
        $this->bamboo = $bamboo;
    }

    public function get_attachments($filter, $filterColumn)
    {
        $data = [];
        $data['filter'] = $this->db->buildFilterString($filter, "IN");
        $data['filter_column'] = $filterColumn;
        $data['table'] = $this->db->_dbPrefix."attachments";


        $sql = "SELECT id, original_name, size FROM :table WHERE :filter_column = :filter";

        $rows = $this->trellis->database->runSql($sql, $data)->fetchAll();
        if ( ! $rows)
        {
            return false;
        }

        foreach($rows as $a)
        {
            if ( $a['id'] )
            {
                $return[ $a['id'] ] = $a;
            }
            else
            {
                $return[] = $a;
            }
        }

        return $return;
    }

    public function assign($ids, $cid)
    {
        if ( ! $cid = intval( $cid ) ) return false;

        if ( ! is_array( $ids ) && intval( $ids ) )
        {
            $ids = array( $ids );
        }

        $data = [];
        $data['table'] = $this->db->_dbPrefix."attachments";
        $data['cid'] = $cid;
        $data['ids'] = $this->db->buildFilterString($ids, "IN");

        $sql = "UPDATE :table SET content_id = :cid WHERE id :ids";
        return $this->db->runSql($sql, $data)->rowCount();

    }

    public function upload(string $ticketID, array $files, string $response='')
    {
        if ( ! $files )
        {
            if ( $response == 'ajax' )
            {
                $this->bamboo->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => 'no data received' ) ) );
            } else {
                return false;
            };
        }

        if ( $this->bamboo->user['g_upload_max_size'] && ( $files['size'][0] > $this->bamboo->user['g_upload_max_size'] ) )
        {
            if ( $response == 'ajax' )
            {
                $this->bamboo->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => $this->bamboo->lang['error_upload_size'] ) ) );
            } else {
                return false;
            };
        }

        $newFunc = function($a){return trim( $a );};
        $allowed_exts = array_map($newFunc, explode( ',', $this->bamboo->user['g_upload_exts'] ) );

        $file_ext = pathinfo($files['name'][0], PATHINFO_EXTENSION);//strtolower(strrchr( $files['name'], "." ));

        if ( ! in_array( $file_ext, $allowed_exts ) )
        {
            if ( $response == 'ajax' ) {
                $this->bamboo->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => $this->bamboo->lang['error_upload_filetype'] ) ) );
            } else {
                return false;
            };
        }

        $file_name = md5( $files['name'][0] . microtime() );
        $upload_location = $this->bamboo->settings['general']['upload_dir'] . $file_name . '.'.$file_ext;

        if ( ! is_writeable( $this->bamboo->settings['general']['upload_dir'] ) )
        {
            $this->bamboo->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => 'directory' ) ) );
        }

        if ( ! @move_uploaded_file( $files['tmp_name'][0], $upload_location ) )
        {
            $this->bamboo->skin->ajax_output( json_encode( array( 'error' => true, 'errormsg' => $this->bamboo->lang['error_upload_move'] ) ) );
        }

        # TODO: only run chmod if web user is 'nobody' (just have a setting)
        //@chmod( $upload_location, 0666 );

        $data['content_type'] = 'N/A';
        $data['content_id'] = -1;
        $data['uid'] = $this->bamboo->user['id'];
        $data['real_name'] = $file_name;
        $data['original_name'] = $this->bamboo->sanitize_data( $files['name'][0] );
        $data['extension'] = $this->bamboo->sanitize_data( $file_ext );

        if ( function_exists( 'finfo_file' ) )
        {
            $finfo = finfo_open( FILEINFO_MIME_TYPE );

            $data['mime'] = finfo_file( $finfo, $upload_location );

            finfo_close( $finfo );
        }

        $data['size'] = $files['size'][0];
        $data['date'] = time();
        $data['ipadd'] = $this->bamboo->input['ip_address'];
        $data['ticket_id'] = $ticketID;

        //Database Insert
        $table = $this->db->_dbPrefix."attachments";
        $sql = "INSERT INTO $table (content_type, content_id, uid, real_name, original_name, extension, mime, size, date, ipadd, ticket_id)
                VALUES(:content_type, :content_id, :uid, :real_name, :original_name, :extension, :mime, :size, :date, :ipadd, :ticket_id)";

        $this->db->runSql($sql, $data);
        $insertedId = $this->db->lastInsertedId;


        if ( $response == 'ajax' )
        {
            return array( 'id' => $insertedId, 'name' => $data['original_name'] );
        }

        return $insertedId;
    }

    public function get_attachments_assigned_to_ticket($ticketId)
    {
        $table = $this->db->_dbPrefix."attachments";
        $data['ticketId'] = $ticketId;
        $sql = "SELECT id, original_name, size FROM $table WHERE ticket_id = :ticketId";
        return $this->db->runSql($sql, $data)->fetchAll();

    }


    public function fetch_attachments_assigned_to_ticket_reply($replyId)
    {
        $table = $this->db->_dbPrefix."attachments";
        $data['ticket_reply_Id'] = $replyId;
        $sql = "SELECT id, original_name, size FROM $table WHERE ticket_reply_id = :ticket_reply_Id";
        return $this->db->runSql($sql, $data)->fetchAll();
    }
}