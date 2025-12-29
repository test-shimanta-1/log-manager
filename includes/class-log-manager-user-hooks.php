<?php
/**
 * user related event hooks class file
 * 
 * Handles logging of user authentication events such as
 * login success, login failure, and logout.
 * 
 * @since 1.0.0
 * @package Log_Manager
 */

class Log_Manager_User_Hooks
{
    /**
     * Register user authentication-related hooks.
     *
     * Hooks into:
     * - set_logged_in_cookie: Logs successful user login events.
     * - wp_login_failed: Logs failed login attempts.
     * - wp_logout: Logs user logout events.
     *
     * @return void
     */
    public function __construct()
    {
        add_action('set_logged_in_cookie', [$this, 'sdw_log_successful_login'], 10, 4);
        add_action('wp_logout', [$this, 'sdw_log_user_logout']);
        add_action('wp_login_failed', [$this, 'sdw_log_failed_login_attempts']);
    }

    /**
     * Log successful user login events.
     *
     * This method is triggered after a user is successfully authenticated
     * and a login cookie is set. It records user details such as role,
     * email, full name, IP address, and login time.
     *
     *
     * @param string $cookie     Authentication cookie.
     * @param int    $expire     Cookie expiration time.
     * @param int    $expiration Cookie expiration timestamp.
     * @param int    $user_id    Logged-in user ID.
     *
     * @return void
     */
    public function sdw_log_successful_login($cookie, $expire, $expiration, $user_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'event_db';

        $user_info = get_userdata($user_id);
        $user_role = implode(', ', $user_info->roles);

        $wpdb->insert(
            $table,
            [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'userid' => $user_id,
                'event_time' => date("Y/m/d"),
                'object_type' => 'User',
                'severity' => 'info',
                'event_type' => 'logged-in',
                'message' => 'Login successful' . '<br/>User Id: <b>' . $user_id . '</b><br/> User Role: <b>' . $user_role . '</b> <br/>User Email: <b>' . $user_info->user_email . '</b> <br/>Full Name: <b>' . $user_info->user_firstname . ' ' . $user_info->user_lastname . '</b>',
            ]
        );
    }

    /**
     * Log failed user login attempts.
     *
     * This method logs authentication failures when a username
     * exists but an incorrect password is provided. It records
     * user details along with severity level set to warning.
     *
     * @since 1.0.0
     *
     * @param string $username Username used during the failed login attempt.
     *
     * @return void
     */
    function sdw_log_failed_login_attempts($username)
    {
        $user = get_user_by('login', $username);
        if ($user) {
            $user_info = get_userdata($user->ID);
            $user_role = implode(', ', $user_info->roles);

            global $wpdb;
            $table = $wpdb->prefix . 'event_db';
            $wpdb->insert(
                $table,
                [
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'userid' => $user->ID,
                    'event_time' => date("Y/m/d"),
                    'object_type' => 'User',
                    'severity' => 'warning',
                    'event_type' => 'login-failed',
                    'message' => 'User login attempt failed' . '<br/>User Id: <b>' . $user->ID . '</b><br/> User Role: <b>' . $user_role . '</b> <br/>User Email: <b>' . $user_info->user_email . '</b> <br/>Full Name: <b>' . $user_info->user_firstname . ' ' . $user_info->user_lastname . '</b>',
                ]
            );
        }

        return;
    }

    /**
     * Log user logout events.
     *
     * This method records an event when a logged-in user logs out
     * of the system. It logs user details, role, email, and IP address.
     *
     * @param int $user_id Logged-out user ID.
     *
     * @return void
     */
    function sdw_log_user_logout($user_id)
    {
        if ($user_id) {
            global $wpdb;
            $table = $wpdb->prefix . 'event_db';
            $user_info = get_userdata($user_id);
            $user_role = implode(', ', $user_info->roles);
            $wpdb->insert(
                $table,
                [
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'userid' => $user_id,
                    'event_time' => date("Y/m/d"),
                    'object_type' => 'User',
                    'severity' => 'info',
                    'event_type' => 'logout',
                    'message' => 'User has been logged-out. ' . '<br/>User Id: <b>' . $user_id . '</b><br/> User Role: <b>' . $user_role . '</b> <br/>User Email: <b>' . $user_info->user_email . '</b> <br/>Full Name: <b>' . $user_info->user_firstname . ' ' . $user_info->user_lastname . '</b>',
                ]
            );
        }
        return;
    }

}