<?php

/**
 * Trellis Desk
 *
 * @copyright  Copyright (C) 2009-2012 ACCORD5. All rights reserved.
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 */

class td_class_db_mysqli {

    var $cid;
    var $query_id            = "";
    var $query_last;
    var $query_count        = 0;
    var $query_s_count        = 0;
    var $is_shutdown        = 0;
    var $all_tablesow_shutdown        = 0;
    var $shutdown_queries    = array();
    var $database_name        = "";
    var $db_prefix            = "";

    private $allow_shutdown = false;

    #=======================================
    # @ Constructor
    #=======================================

    function __construct($config)
    {
        #=============================
        # Connect to Database
        #=============================

        $this->allow_shutdown = $config['shutdown_queries'];

        define( 'TDDB_PRE', $config['prefix'] );

        if ( ! $this->cid = mysqli_connect( $config['host'] . ( ( $config['port'] ) ? ( ':'. $config['port'] ) : null ), $config['user'], $config['pass'] ) ) trigger_error( "mysqli - Unable to connect to database. The following error was returned:<br />\n". mysqli_errno() .": ". mysqli_error(), E_USER_ERROR );

        if ( ! mysqli_select_db($this->cid, $config['name'] ) ) trigger_error( "mysqli - Unable to select database", E_USER_ERROR );

        $this->database_name = $config['name'];
        $this->db_prefix = $config['prefix'];
        mysqli_set_charset($this->cid, 'utf8');


        return true;
    }

    #=======================================
    # @ Next Shutdown
    # Tells the module that the next query
    # should be scheduled for shutdown.
    #=======================================

    function next_shutdown()
    {
        if ( $this->allow_shutdown )
        {
            $this->is_shutdown = 1;

            $this->query_s_count ++;
        }
    }

    #=======================================
    # @ Run Query
    # Runs a mysqli query.
    #=======================================

    function query($query="")
    {
        if ( ! $query )
        {
            $query = $this->query_id;
        }

        if ( ! $this->query_last = mysqli_query($this->cid, $query ) ) trigger_error( "mysqli - Unable to process query:<br />\n". $query ."<br />\nThe following error was returned:<br />\n". mysqli_errno($this->cid) .": ". mysqli_error($this->cid), E_USER_ERROR );

        $this->queries_ran .= $query .'<br /><br />';

        $this->query_count ++;

        #echo $query .'<br />';
        #echo 'Memory: '. ( memory_get_usage() / 1000000 ) .'<br /><br />';

        return $this->query_last;
    }

    #=======================================
    # @ Construct Query
    # Constructs our query for mysqli.
    #=======================================

    function construct($do)
    {
        $this->query_id = ""; // Initialize for Security

        #=============================
        # Standard
        #=============================

        # SELECT
        if ( $do['select'] )
        {
            $this->query_id = 'SELECT';

            if ( is_array( $do['select'] ) )
            {
                if ( is_array( $do['join'] ) )
                {
                    //while( list( $id, $fields ) = each( $do['select'] ) )
                        foreach($do['select'] as $id => $fields)
                    {
                        if ( $fields == 'all')
                        {
                            $this->query_id .= ' '. $id .'.*,';
                        }
                        else
                        {
                            //while( list( , $field ) = each( $fields ) )
                                foreach($fields as $field)
                            {
                                if ( is_array( $field ) )
                                {
                                    $this->query_id .= ' '. $id .'.`'. mysqli_real_escape_string($this->cid, key( $field ) ) .'` AS `'. mysqli_real_escape_string($this->cid, current( $field ) ) .'`,';
                                }
                                else
                                {
                                    $this->query_id .= ' '. $id .'.`'. mysqli_real_escape_string($this->cid,  $field ) .'`,';
                                }
                            }
                        }
                    }
                }
                else
                {
                    //while( list( , $field ) = each( $do['select'] ) )
                        foreach($do['select'] as $field)
                    {
                        if ( is_array( $field ) )
                        {
                            $this->query_id .= ' `'. mysqli_real_escape_string($this->cid, key( $field ) ) .'` AS `'. mysqli_real_escape_string($this->cid,  current( $field ) ) .'`,';
                        }
                        else
                        {
                            $this->query_id .= ' `'. mysqli_real_escape_string($this->cid,  $field ) .'`,';
                        }
                    }
                }

                $this->query_id = substr( $this->query_id, 0, -1 );
            }
            elseif ( $do['select'] == 'all' )
            {
                $this->query_id .= ' *';
            }

            if ( is_array( $do['join'] ) )
            {
                //while( list( $id, $table ) = each( $do['from'] ) )
                    foreach($do['from'] as $id => $table)
                {
                    $this->query_id .= ' FROM `'. TDDB_PRE . mysqli_real_escape_string($this->cid,  $table ) .'` '. $id ;
                }
            }
            else
            {
                $this->query_id .= ' FROM `'. TDDB_PRE . mysqli_real_escape_string($this->cid,  $do['from'] ) .'`';
            }
        }

        # INSERT / UPDATE
        if ( $do['insert'] || $do['update'] )
        {
            if ( $do['insert'] )
            {
                $this->query_id = 'INSERT INTO `'. TDDB_PRE . mysqli_real_escape_string($this->cid, $do['insert'] ) .'`';
            }
            else
            {
                $this->query_id = 'UPDATE `'. TDDB_PRE . mysqli_real_escape_string($this->cid,  $do['update'] ) .'`';
            }

            $this->query_id .= ' SET';

            //while( list( $field, $value ) = each( $do['set'] ) )
                foreach($do['set']  as $field => $value)
            {
                if ( is_array( $value ) )
                {
                    $this->query_id .= ' `'. mysqli_real_escape_string($this->cid,  $field ) .'` = ';

                    if ( $value[0] == '=' )
                    {
                        $this->query_id .= mysqli_real_escape_string($this->cid, $value );
                    }
                    elseif ( $value[0] == '-' )
                    {
                        $this->query_id .= '`'. mysqli_real_escape_string($this->cid,  $field ) .'`-'. intval( $value[1] );
                    }
                    elseif ( $value[0] == '+' )
                    {
                        $this->query_id .= '`'. mysqli_real_escape_string($this->cid, $field ) .'`+'. intval( $value[1] );
                    }

                    $this->query_id .= ',';
                }
                else
                {
                    $this->query_id .= ' `'. mysqli_real_escape_string($this->cid, $field ) .'` = \''. mysqli_real_escape_string($this->cid, $value ) .'\',';
                }
            }

            $this->query_id = substr( $this->query_id, 0, -1 );
        }

        # DELETE
        if ( $do['delete'] )
        {
            $this->query_id = 'DELETE FROM `'. TDDB_PRE . mysqli_real_escape_string($this->cid, $do['delete'] ) .'`';
        }

        #=============================
        # Extras
        #=============================

        # JOIN
        if ( is_array( $do['join'] ) )
        {
            //while( list( , $join ) = each( $do['join'] ) )
                foreach($do['join'] as $join)
            {
                //list( $final_fid, $final_table ) = each( $join['from'] );
                foreach($join['from'] as $final_fid => $final_table) {
                    //list( $final_fid, $final_table ) = $item;
                    $this->query_id .= ' LEFT JOIN `'. TDDB_PRE . mysqli_real_escape_string($this->cid, $final_table ) .'` '. $final_fid .' ON ( ';
                }



                if ( is_array( $join['where'][0] ) )
                {
                    $placedfirst = 0;

                    foreach($join['where'] as $jcw )
                    {
                        if ( $placedfirst ) $this->query_id .= 'AND ';

                        $i = 0;

                        //while( list( $wid, $wfield ) = each( $jcw ) )
                            foreach($jcw as $wid => $wfield)
                        {
                            $i ++;

                            if ( $i == 1 )
                            {
                                if( is_numeric( $wid ) )
                                {
                                    $this->query_id .= "'". mysqli_real_escape_string($this->cid, $wfield ) ."' ";
                                }
                                else
                                {
                                    $this->query_id .= $wid .'.';
                                    $this->query_id .= '`'. mysqli_real_escape_string($this->cid, $wfield ) .'` ';
                                }
                            }
                            elseif ( $i == 2 )
                            {
                                $this->query_id .= $wfield .' ';
                            }
                            elseif ( $i == 3 )
                            {
                                if( is_numeric( $wid ) )
                                {
                                    $this->query_id .= "'". mysqli_real_escape_string($this->cid, $wfield ) ."' ";
                                }
                                else
                                {
                                    $this->query_id .= $wid .'.';
                                    $this->query_id .= '`'. mysqli_real_escape_string($this->cid, $wfield ) .'` ';
                                }
                            }
                        }

                        if ( ! $placedfirst ) $placedfirst = 1;
                    }
                }
                else
                {
                    $i = 0;

                    //while( list( $wid, $wfield ) = each( $join['where'] ) )
                        foreach($join['where'] as $wid => $wfield)
                    {
                        $i ++;

                        if ( $i == 1 )
                        {
                            if( is_numeric( $wid ) )
                            {
                                $this->query_id .= "'". mysqli_real_escape_string($this->cid, $wfield ) ."' ";
                            }
                            else
                            {
                                $this->query_id .= $wid .'.';
                                $this->query_id .= '`'. mysqli_real_escape_string($this->cid, $wfield ) .'` ';
                            }
                        }
                        elseif ( $i == 2 )
                        {
                            $this->query_id .= $wfield .' ';
                        }
                        elseif ( $i == 3 )
                        {
                            if( is_numeric( $wid ) )
                            {
                                $this->query_id .= "'". mysqli_real_escape_string($this->cid, $wfield ) ."' ";
                            }
                            else
                            {
                                $this->query_id .= $wid .'.';
                                $this->query_id .= '`'. mysqli_real_escape_string($this->cid, $wfield ) .'` ';
                            }
                        }
                    }
                }

                $this->query_id .= ')';
            }
        }

        # WHERE
        if ( is_array( $do['where'] ) && ! empty( $do['where'] ) )
        {
            $this->query_id .= ' WHERE';

            if ( is_array( $do['where'][0][0] ) )
            {
                if ( is_array( $do['join'] ) )
                {
                    if ( is_array( $do['where'][0] ) )
                    {
                        //while( list( , $where ) = each( $do['where'] ) )
                            foreach($do['where']  as $where)
                        {
                            if ( is_array( $where[0] ) )  # FIXME: This whole file is a mess. Let's use functions and really clean this up. This slight modification is due to group where conditions. Don't break anything. Check with ticket list (should also show tickets that you're assigned to even if they are outside your department)
                            {
                                $this->query_id .= $this->add_logic( end( $where ) );

                                $this->query_id .= ' ( ';

                                $whereb = $where;

                                //foreach( $whereb as $where )
                                //{
                                    //if ( ! is_array( $where ) ) continue;


                                    //list( $final_id, $final_field ) = each( $where[0] );
                                    $testArray = $whereb[0];

                                    foreach($testArray as $final_id => $final_field ){
                                        $this->add_logic( $whereb[3] );
                                        if (is_array($final_field)){
                                            foreach($final_field as $key => $value)
                                            {
                                                $final_id = $key;
                                                $final_field = $value;
                                            }

                                        }
                                        if ( strpos( $final_field, '|' ) )
                                        {
                                            $wdata = explode( '|', $whereb[0] );

                                            $this->query_id .= $this->get_function( $final_id .'.`'. $wdata[0] .'`', $wdata[1] );
                                        }
                                        else
                                        {
                                            $this->query_id .= ' '. $final_id .'.`'. mysqli_real_escape_string($this->cid, $final_field ) .'`';
                                        }

                                        if ( $whereb[1] == 'in' )
                                        {
                                            $this->add_where_in( $whereb[2] );
                                        }
                                        elseif ( $whereb[1] == 'like' )
                                        {
                                            $this->add_where_like( $whereb[2] );
                                        }
                                        elseif ( $where[1] == 'is' )
                                        {
                                            $this->add_where_is( $whereb[2] );
                                        }
                                        else
                                        {
                                            $this->query_id .= ' '. $whereb[1].' \''. mysqli_real_escape_string($this->cid,  $whereb[2] ) .'\'';
                                        }
                                    }


                                //}

                                $this->query_id .= ' )';
                            }
                            else
                            {
                                //list( $final_id, $final_field ) = each( $where[0] );
                                foreach($where[0]  as $item){
                                    list( $final_id, $final_field ) = $item;
                                }

                                $this->add_logic( $where[3] );

                                if ( strpos( $final_field, '|' ) )
                                {
                                    $wdata = explode( '|', $where[0] );

                                    $this->query_id .= $this->get_function( $final_id .'.`'. $wdata[0] .'`', $wdata[1] );
                                }
                                else
                                {
                                    $this->query_id .= ' '. $final_id .'.`'. mysqli_real_escape_string($this->cid, $final_field ) .'`';
                                }

                                if ( $where[1] == 'in' )
                                {
                                    $this->add_where_in( $where[2] );
                                }
                                elseif ( $where[1] == 'like' )
                                {
                                    $this->add_where_like( $where[2] );
                                }
                                elseif ( $where[1] == 'is' )
                                {
                                    $this->add_where_is( $where[2] );
                                }
                                else
                                {
                                    $this->query_id .= ' '. $where[1].' \''. mysqli_real_escape_string($this->cid, $where[2] ) .'\'';
                                }
                            }
                        }
                    }
                    else
                    {
                        //list( $final_id, $final_field ) = each( $do['where'][0] );
                        foreach($do['where'][0] as $item){
                            list( $final_id, $final_field ) = $item;
                        }

                        $this->add_logic( $do['where'][3] );

                        if ( strpos( $final_field, '|' ) )
                        {
                            $wdata = explode( '|', $final_field );

                            $this->query_id .= ' '. $this->get_function( $final_id .'.`'. $wdata[0] .'`', $wdata[1] );
                        }
                        else
                        {
                            $this->query_id .= ' '. $final_id .'.`'. mysqli_real_escape_string($this->cid, $final_field ) .'`';
                        }

                        if ( $do['where'][1] == 'in' )
                        {
                            $this->add_where_in( $do['where'][2] );
                        }
                        elseif ( $do['where'][1] == 'like' )
                        {
                            $this->add_where_like( $do['where'][2] );
                        }
                        elseif ( $do['where'][1] == 'is' )
                        {
                            $this->add_where_is( $do['where'][2] );
                        }
                        else
                        {
                            $this->query_id .= ' '. $do['where'][1].' \''. mysqli_real_escape_string($this->cid, $do['where'][2] ) .'\'';
                        }
                    }
                }
                else
                {
                       //while( list( , $where ) = each( $do['where'] ) )
                           foreach($do['where'] as $where)
                    {
                        $this->add_logic( $where[3] );

                        if ( strpos( $where[0], '|' ) )
                        {
                            $wdata = explode( '|', $where[0] );

                            $this->query_id .= ' '. $this->get_function( '`'. $wdata[0] .'`', $wdata[1] );
                        }
                        else
                        {
                            $this->query_id .= ' `'. mysqli_real_escape_string($this->cid, $where[0] ) .'`';
                        }

                        if ( $where[1] == 'in' )
                        {
                            $this->add_where_in( $where[2] );
                        }
                        elseif ( $where[1] == 'like' )
                        {
                            $this->add_where_like( $where[2] );
                        }
                        elseif ( $where[1] == 'is' )
                        {
                            $this->add_where_is( $where[2] );
                        }
                        else
                        {
                            $this->query_id .= ' '. $where[1].' \''. mysqli_real_escape_string($this->cid, $where[2] ) .'\'';
                        }
                    }
                }
            }
            else
            {
                if (is_array($do['where'][0])){
                    $mystring = implode(" ", $do['where'][0]);
                } else {
                    $mystring = $do['where'][0];
                }

                $myarray = $do['where'][0];
                $final_id = '';
                $final_field = '';


                if (is_array($myarray)) {
                    foreach($myarray as $id => $where){
                        $final_id =  $id;
                        $final_field = $where;

                    }

                }



                if ( strpos( $mystring, '|' ) )
                {
                    $wdata = explode( '|', $do['where'][0] );

                    $this->query_id .= ' '. $this->get_function( '`'. $wdata[0] .'`', $wdata[1] );
                }
                else
                {
                    if(is_array($myarray)){
                        $this->query_id .= ' '. $final_id .'.`'. mysqli_real_escape_string($this->cid, $final_field ) .'`';

                    } else {
                        $this->query_id .= ' `'. mysqli_real_escape_string($this->cid, $do['where'][0] ) .'` ';
                    }

                }

                if ( $do['where'][1] == 'in' )
                {
                    $this->add_where_in( $do['where'][2] );
                }
                elseif ( $do['where'][1] == 'like' )
                {
                    $this->add_where_like( $do['where'][2] );
                }
                elseif ( $do['where'][1] == 'is' )
                {
                    $this->add_where_is( $do['where'][2] );
                }
                else
                {
                    $this->query_id .= $do['where'][1].' \''. mysqli_real_escape_string($this->cid,  $do['where'][2] ) .'\'';
                }
            }
        }

        # GROUP
        if ( $do['group'] )
        {
            if ( is_array( $do['group'] ) )
            {
                $this->query_id .= ' GROUP BY '. key( $do['group'] ) .'.`'. mysqli_real_escape_string($this->cid, current( $do['group'] ) ) .'`';
            }
            else
            {
                $this->query_id .= ' GROUP BY `'. mysqli_real_escape_string($this->cid, $do['group'] ) .'`';
            }
        }

        # ORDER
        if ( is_array( $do['order'] ) )
        {
            $this->query_id .= ' ORDER BY';

            //while( list( $field, $order ) = each( $do['order'] ) )
                foreach($do['order']  as $field => $order)
            {
                if ( is_array( $do['join'] ) )
                {
                    list( $id => $real_order ) = $order; //each( $order );
//                    foreach($order as $item){
//                        list( $id, $real_order ) = $item;
//                    }

                    $this->query_id .= ' '. $id .'.`'. mysqli_real_escape_string($this->cid, $field ) .'` '. mysqli_real_escape_string($this->cid, $real_order ) .',';
                }
                else
                {
                    $this->query_id .= ' `'. mysqli_real_escape_string($this->cid, $field ) .'` '. mysqli_real_escape_string($this->cid, $order ) .',';
                }
            }

            $this->query_id = substr( $this->query_id, 0, -1 );
        }

        # LIMIT
        if ( is_array( $do['limit'] ) )
        {
            if ( isset( $do['limit'][1] ) )
            {
                $this->query_id .= ' LIMIT '. mysqli_real_escape_string($this->cid, intval( $do['limit'][0] ) ) .','. mysqli_real_escape_string($this->cid, intval( $do['limit'][1] ) );
            }
            else
            {
                $this->query_id .= ' LIMIT '. mysqli_real_escape_string($this->cid, intval( $do['limit'][0] ) );
            }
        }

        return $this->query_id;
    }

    #=======================================
    # @ Get Function
    # Returns the appropriate SQL syntax for
    # the requested function.
    #=======================================

    function get_function($field, $func)
    {
        if ( $func == 'lower' )
        {
            $return = 'LOWER('. mysqli_real_escape_string($this->cid, $field ) .')';
        }

        return $return;
    }

    #=======================================
    # @ Add Logic
    # Adds the appropriate SQL syntax for
    # the requested logic operator to the
    # current query.
    #=======================================

    function add_logic($alias)
    {
        if ( $alias )
        {
            if ( $alias == 'and' )
            {
                $this->query_id .= ' AND';
            }
            elseif ( $alias == 'or' )
            {
                $this->query_id .= ' OR';
            }
            elseif ( $alias == 'xor' )
            {
                $this->query_id .= ' XOR';
            }
        }
    }

    #=======================================
    # @ Add Where IN
    # Adds the appropriate SQL syntax for
    # the WHERE IN clause to the current
    # query.
    #=======================================

    function add_where_in($values)
    {
        $this->query_id .= ' IN ';
        $this->query_id .= '( ';

        //while( list( , $in ) = each( $values ) )
            foreach($values as $in)
        {
            $this->query_id .= '\''. mysqli_real_escape_string($this->cid, $in ) .'\', ';
        }

        $this->query_id = substr( $this->query_id, 0, -2 );

        $this->query_id .= ' )';
    }

    #=======================================
    # @ Add Where LIKE
    # Adds the appropriate SQL syntax for
    # the WHERE LIKE clause to the current
    # query.
    #=======================================

    function add_where_like($value)
    {
        $this->query_id .= ' LIKE \''. mysqli_real_escape_string($this->cid, $value ) .'\'';
    }

    #=======================================
    # @ Add Where IS
    #=======================================

    function add_where_is($value)
    {
        $value = strtoupper( $value );

        if ( $value != 'NULL' && $value != 'NOT NULL' ) return false;

        $this->query_id .= ' IS '. $value;
    }

    #=======================================
    # @ Execute Query
    # Executes our cute litte query.
    #=======================================

    function execute($to_exe="")
    {
        if ( ! $to_exe )
        {
            $to_exe = $this->query_id;
        }

        if ( $this->is_shutdown )
        {
            $this->shutdown_queries[] = $to_exe;

            $this->is_shutdown = 0;
            $this->query_id = "";

            return TRUE;
        }

        $eq = $this->query($to_exe);

        $this->query_id = "";

        return $eq;
    }

    #=======================================
    # @ Clear Memory
    # Removes the last run query from cache.
    #=======================================

    function clear_memory()
    {
        $this->query_last = "";
    }

    #=======================================
    # @ Fetch Row
    # Fetches row information from query.
    #=======================================

    function fetch_row($query="")
    {
        if ( ! $query )
        {
            $query = $this->query_last;
        }

        $record = mysqli_fetch_array($query, MYSQLI_ASSOC);

        return $record;
    }

    #=======================================
    # @ Get Number of Rows
    # Fetches the number of rows selected
    # by a query.
    #=======================================

    function get_num_rows($query="")
    {
        if ( ! $query )
        {
            $query = $this->query_last;
        }

        $rows = @mysqli_num_rows($query);

        return $rows;
    }

    #=======================================
    # @ Get Number of Affected Rows
    # Fetches the number of rows affected
    # by a query.
    #=======================================

    function get_affected_rows()
    {
        return @mysqli_affected_rows( $this->cid );
    }

    #=======================================
    # @ Query Count
    # Returns the number of queries executed.
    #=======================================

    function get_query_count()
    {
        return $this->query_count;
    }

    #=======================================
    # @ Query Shutdown Count
    # Returns the number of shutdown queries
    # scheduled.
    #=======================================

    function get_query_s_count()
    {
        return $this->query_s_count;
    }

    #=======================================
    # @ Query Total Count
    # Returns the total number of queries
    # (to be / already) executed.
    #=======================================

    function get_query_t_count()
    {
        return $this->query_count + $this->query_s_count;
    }

    #=======================================
    # @ Get Insert ID
    # Returns the insert id of previous
    # query.
    #=======================================

    function get_insert_id()
    {
        return mysqli_insert_id( $this->cid );
    }

    #=======================================
    # @ Get Tables
    # Returns al list of tables in the
    # specified database.
    #=======================================

    function get_tables()
    {
        return $this->query( "SHOW TABLES FROM ". $this->database_name );
    }

    #=======================================
    # @ Get Backup
    # Generates a backup file by dumping
    # the SQL data and structure.
    # Courtesy of Unreal Ed from Programming Talk.
    # http://www.programmingtalk.com/member.php?userid=141445
    # Modified by someotherguy of ACCORD5
    #=======================================

    function get_backup($p_tables="", $p_drop_table=0, $p_if_not_exists=0)
    {
        $table_status = mysqli_query($this->cid, "SHOW TABLE STATUS");

        while( $all_tables = mysqli_fetch_assoc( $table_status ) )
        {
            $tbl_stat[ $all_tables[Name] ] = $all_tables[Auto_increment];
        }

        $backup = "-- Trellis Desk SQL Dump\n\n-- --------------------------------------------------------\n";

        $tables = $this->get_tables();

        while( $tabs = mysqli_fetch_row( $tables ) )
        {
            $do_backup = 0; // Reset

            if ( is_array( $p_tables ) )
            {
                if ( $p_tables[ $tabs[0] ] )
                {
                    $do_backup = 1;
                }
            }
            else
            {
                $do_backup = 1;
            }

            if ( $do_backup )
            {
                   $backup .= "\n--\n-- Table structure for $tabs[0]\n--\n\n";

                   if ( $p_drop_table )
                   {
                       $backup .= "DROP TABLE IF EXISTS $tabs[0];\n";
                   }

                   if ( $p_if_not_exists )
                   {
                       $backup .= "CREATE TABLE IF NOT EXISTS $tabs[0] (";
                   }
                   else
                   {
                       $backup .= "CREATE TABLE $tabs[0] (";
                   }

                $res = mysqli_query($this->cid, "SHOW CREATE TABLE $tabs[0]");

                while( $all_tables = mysqli_fetch_assoc( $res ) )
                {
                    $str = str_replace("CREATE TABLE $tabs[0] (", "", $all_tables['Create Table']);
                    $str = str_replace(",", ",", $str);
                    $str2 = str_replace(") ) TYPE=MyISAM ", ")\n ) TYPE=MyISAM ", $str);

                    if ( $tbl_stat[$tabs[0]] )
                    {
                        $backup .= $str2 ." AUTO_INCREMENT=". $tbl_stat[$tabs[0]] .";\n\n";
                    }
                    else
                    {
                        $backup .= $str2 .";\n\n";
                    }
                }

                $backup .= "--\n-- Dumping data for table $tabs[0]\n--\n\n";
                   $data = mysqli_query($this->cid, "SELECT * FROM $tabs[0]");

                while( $dt = mysqli_fetch_row( $data ) )
                {
                       $backup .= "INSERT INTO $tabs[0] VALUES('". str_replace( "\r\n", '\r\n', addslashes($dt[0]) ) ."'";

                    for( $i=1; $i < sizeof($dt); $i++ )
                    {
                        #$dt[$i] = str_replace( "\r\n", '\r\n', $dt[$i] );
                        $backup .= ", '". str_replace( "\r\n", '\r\n', addslashes($dt[$i]) ) ."'";
                    }

                    $backup .= ");\n";
                }
            }
        }

        return $backup;
    }

    #=======================================
    # @ Get
    #=======================================

    public function get($input, $key)
    {
        $return = array();

        $this->construct( array(
                                'select'    => $input['select'],
                                'from'        => $input['from'],
                                'join'        => $input['join'],
                                'where'        => $input['where'],
                                'order'        => $input['order'],
                                'limit'        => $input['limit'],
                         )        );

        $this->execute();

        if ( ! $this->get_num_rows() ) return false;

        while ( $r = $this->fetch_row() )
        {
            $return[ $r[ $key ] ] = $r;
        }

        return $return;
    }

    #=======================================
    # @ Get Single
    #=======================================

    public function get_single($input)
    {
        $this->construct( array(
                                'select'    => $input['select'],
                                'from'        => $input['from'],
                                'join'        => $input['join'],
                                'where'        => $input['where'],
                                'order'        => $input['order'],
                                'limit'        => array( 0, 1 ),
                         )        );

        $this->execute();

        if ( ! $this->get_num_rows() ) return false;

        return $this->fetch_row();
    }

    #=======================================
    # @ Shutdown
    #=======================================

    public function shut_down()
    {
        if ( $this->allow_shutdown )
        {
            if ( is_array( $this->shutdown_queries ) )
            {
                foreach ( $this->shutdown_queries as &$q )
                {
                    $this->query( $q );
                }
            }
        }
    }
}

?>