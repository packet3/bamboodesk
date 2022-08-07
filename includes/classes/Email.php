<?php

namespace BambooDesk;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
class Email
{
    private $config;
    private $db;
    private $bamboo;

    public function __construct(array $config, Database $db, $bamboo)
    {
        $this->config = $config;
        $this->db = $db;
        $this->bamboo = $bamboo;
    }

    public function notify_staff($params=array())
    {
        //if ( ! $this->trellis->cache->data['settings']['email']['enable'] ) return true;
        //if ( ! $this->trellis->cache->data['settings']['esnotify']['enable'] ) return true;

        if ( $params['type'] ) return true;

        $data = [];
        $data['type'] = $params['type'];

        $sql = "SELECT u.id, u.name, u.email, u.ugroup_sub, u.ugroup_sub_acp, u.lang, u.email_type, us.email_staff_enable,
                us.email_staff_:type, us.esn_unassigned, us.esn_assigned, us.esn_assigned_to_me g.g_acp_access, g.g_acp_depart_perm, 
                a.tid AS AssignedTicketId
                FROM users u LEFT JOIN users_staff us ON u.id = us.uid LEFT JOIN groups g ON u.ugroup = g.g_id 
                LEFT JOIN assign_map a ON a.uid = us.uid
                WHERE g.g_acp_access = 1 OR u.ugroup_sub_acp = 1 ";

        $staff = $this->db->runSql($sql, $data)->fetchAll();

        if ( ! $staff ) return false;

        $sent = array();

        foreach ( $staff as $s )
        {
            if ( $params['exclude'] )
            {
                if ( is_array( $params['exclude'] ) && $params['exclude'][ $s['id'] ] )
                {
                    continue;
                }
                else
                {
                    if ( $params['exclude'] == $s['id'] ) continue;
                }
            }

            # CHECK: sub-group logic

            // Sub-Groups
            $s['ugroup_sub'] = unserialize( $s['ugroup_sub'] );

            if ( ! $s['g_acp_access'] && $s['ugroup_sub_acp'] && is_array( $s['ugroup_sub'] ) && ! empty( $s['ugroup_sub'] ) )
            {
                foreach ( $s['ugroup_sub'] as $g )
                {
                    if ( $this->bamboo->cache->data['groups'][ $g ]['g_acp_access'] ) $s['g_acp_access'] = 1;

                    break;
                }
            }

            if ( ! $s['g_acp_access'] ) continue;

            $perms = unserialize( $s['g_acp_depart_perm'] );

            // Sub-Groups Permissions
            if ( is_array( $s['ugroup_sub'] ) && ! empty( $s['ugroup_sub'] ) )
            {
                foreach ( $s['ugroup_sub'] as $gid )
                {
                    $g = $this->bamboo->cache->data['groups'][ $gid ];

                    $g['g_acp_depart_perm'] = unserialize( $g['g_acp_depart_perm'] );

                    if ( is_array( $g['g_acp_depart_perm'] ) && ! empty( $g['g_acp_depart_perm'] ) )
                    {
                        foreach ( $g['g_acp_depart_perm'] as $d => $types )
                        {
                            if ( ! $perms[ $d ]['v'] && $g['g_acp_depart_perm'][ $d ]['v'] ) $perms[ $d ]['v'] = 1;
                        }
                    }
                }
            }

            if ( ! $perms[ $params['did'] ]['v'] && ! $s['AssignedTicketId'][ $s['id'] ] ) continue;

            if ( ! $s['AssignedTicketId'] )
            {
                if ( ! $s['esn_unassigned'] ) continue;
            }
            else
            {
                if ( $s['AssignedTicketId'][ $s['id'] ] )
                {
                    if ( ! $s['esn_assigned_to_me'] ) continue;
                }
                else
                {
                    if ( ! $s['esn_assigned'] ) continue;
                }
            }

            if ( ! $params['override'] )
            {
                if ( ! $s['email_staff_enable'] ) continue;

                if ( ! $s[ 'email_staff_'. $params['type'] ] ) continue;
            }

            $sent[ $s['id'] ] = 1;

            $this->send_email( array( 'to' => $s['id'], 'name' => $s['name'], 'email' => $s['email'], 'msg' => $params['msg'] .'_staff', 'replace' => $params['replace'], 'lang' => $s['lang'], 'override' => 1, 'format' => $s['email_type'] ) );
        }

        return $sent;
    }

    public function prepare_email(string $message) : string
    {
        $purifier               = new \HTMLPurifier();                     // Create Purifier
        $purifier->config->set('HTML.Allowed', 'p,br,b,i,a[href],img[src|alt]'); // Allowed
        return $purifier->purify($message); // Purify content
    }

    public function send_email(array $params)
    {
        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
            $mail->isSMTP();                                            //Send using SMTP
            $mail->Host       = $this->config['smtp_host'];                     //Set the SMTP server to send through
            //$mail->SMTPAuth   = $this->config['enable_smtp_auth'];                                   //Enable SMTP authentication
            //$mail->Username   = $this->config['smtp_username'];                     //SMTP username
            //$mail->Password   = $this->config['smtp_password'];                               //SMTP password
            //$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
            $mail->Port       = $this->config['smtp_port'];                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

            //Recipients
            $mail->setFrom($this->config['sent_from_email'], 'Mailer');
            //$mail->addAddress('joe@example.net', 'Joe User');     //Add a recipient
            $mail->addAddress($params['send_to']);               //Name is optional
            //$mail->addReplyTo('info@example.com', 'Information');

            //Attachments
            //$mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
            //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

            //Content
            $mail->isHTML(true);                                  //Set email format to HTML
            $mail->Subject = $params['message_subject'];
            $mail->Body    = $params['message_body'];
            //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

            $mail->send();
            echo 'Message has been sent';
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }
    }

}