<?php 

/** login success */
add_action( 'set_logged_in_cookie', 'log_successful_login', 10, 4 );
function log_successful_login( $cookie, $expire, $expiration, $user_id ) {

    global $wpdb;
    $table = $wpdb->prefix . 'event_db';

    $wpdb->insert(
        $table,
        [
            'userid'        => $user_id,
            'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? '',
            'event_time'    => date("Y/m/d"),
            'object_type'   => 'User',
            'event_type'    => 'Login',
            'warning_level' => 'low',
            'message'       => 'Login successful',
        ]
    );
}

/** wordpress failed login */
add_action( 'wp_login_failed', 'sdw_plugin_handle_failed_login' );
function sdw_plugin_handle_failed_login( $username ) {
    $user = get_user_by('login', $username);
    if($user){
         global $wpdb;
    $table = $wpdb->prefix . 'event_db';
    $wpdb->insert(
            $table,
            [
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'userid'     => $user->ID,
                'event_time' => date("Y/m/d"),
                'object_type' => 'User',
                'warning_level' => 'Login Failed' ,
                'event_type' => 'created',
                'message'    => 'User login attempt failed',
            ]
        );
    }
    
     return;
}