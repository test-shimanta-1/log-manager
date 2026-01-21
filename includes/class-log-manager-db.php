<?php
/**
 * Plugin's Main Table Class File
 * 
 * @since 1.0.1
 * @package Log_Manager
 */

class Log_Manager_DB
{
    /**
     * initializes plugin table
     * 
     * @return void
     */
    public static function init_db()
    {
        global $wpdb;
        $db_table_name = $wpdb->prefix . 'log_db';
        $charset_collate = $wpdb->get_charset_collate();
        if ($wpdb->get_var("show tables like '$db_table_name'") != $db_table_name) {
            $sql = "CREATE TABLE $db_table_name (
                    id int(11) NOT NULL auto_increment,
                    userid varchar(200) NOT NULL,
                    severity ENUM('emergency','alert','critical','error','warning','notice','info','debug') NOT NULL,
                    ip_address varchar(15) NOT NULL,
                    event_type ENUM( 'created','modified','trashed','restored','deleted','logged-in','login-failed','logout','published', 'assigned') NOT NULL,
                    event_time varchar(20) NOT NULL,
                    object_type ENUM('Post', 'User', 'Media', 'Taxonomy', 'Settings') NOT NULL,
                    message varchar(1000) NOT NULL,
                    UNIQUE KEY id (id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}