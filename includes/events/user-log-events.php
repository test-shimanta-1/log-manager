<?php 

/** login success */
add_action( 'set_logged_in_cookie', 'log_successful_login', 10, 4 );
function log_successful_login( $cookie, $expire, $expiration, $user_id ) {

    global $wpdb;
    $table = $wpdb->prefix . 'event_db';

    $user_info = get_userdata($user_id);
    $user_role = implode(', ', $user_info->roles);

    $wpdb->insert(
        $table,
        [
            'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid'        => $user_id,
            'event_time'    => date("Y/m/d"),
            'object_type'   => 'User',
            'severity' => 'low',
            'event_type'    => 'Login',
            'message'       => 'Login successful'.'<br/>User Id: <b>'.$user_id.'</b><br/> User Role: <b>'.$user_role.'</b> <br/>User Email: <b>'.$user_info->user_email.'</b> <br/>Full Name: <b>'.$user_info->user_firstname.' '.$user_info->user_lastname.'</b>',
        ]
    );
}

/** wordpress failed login */
add_action( 'wp_login_failed', 'sdw_plugin_handle_failed_login' );
function sdw_plugin_handle_failed_login( $username ) {
    $user = get_user_by('login', $username);

    if($user){
         $user_info = get_userdata($user->ID);
         $user_role = implode(', ', $user_info->roles);

         global $wpdb;
         $table = $wpdb->prefix . 'event_db';
         $wpdb->insert(
                $table,
                [
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'userid'     => $user->ID,
                    'event_time' => date("Y/m/d"),
                    'object_type' => 'User',
                    'severity' => 'high' ,
                    'event_type' => 'Login Failed',
                    'message'    => 'User login attempt failed'.'<br/>User Id: <b>'.$user->ID.'</b><br/> User Role: <b>'.$user_role.'</b> <br/>User Email: <b>'.$user_info->user_email.'</b> <br/>Full Name: <b>'.$user_info->user_firstname.' '.$user_info->user_lastname.'</b>',
                ]
            );
    }
    
     return;
}

/** user logged out */
function redirect_after_logout( $user_id ) {
    if($user_id){
         global $wpdb;
        $table = $wpdb->prefix . 'event_db';
        $user_info = get_userdata($user_id);
        $user_role = implode(', ', $user_info->roles);
        $wpdb->insert(
                $table,
                [
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'userid'     => $user_id,
                    'event_time' => date("Y/m/d"),
                    'object_type' => 'User',
                    'severity' => 'medium' ,
                    'event_type' => 'Logout',
                    'message'    => 'User has been logged-out. '.'<br/>User Id: <b>'.$user_id.'</b><br/> User Role: <b>'.$user_role.'</b> <br/>User Email: <b>'.$user_info->user_email.'</b> <br/>Full Name: <b>'.$user_info->user_firstname.' '.$user_info->user_lastname.'</b>',
                ]
            );  
    }
    return;
}
add_action( 'wp_logout', 'redirect_after_logout'  );