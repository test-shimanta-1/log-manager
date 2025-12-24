<?php
/*
 * Plugin Name:       Log Manager
 * Description:       This plugin is designed to provide detailed insights into system activity logs. It allows administrators to track user actions such as creating, editing, and deleting posts, pages, and custom post types. Additionally, it records user login activity, including both successful and failed login attempts, for better monitoring and security.
 * Version:           1.10.3
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Sundew team
 * Author URI:        https://sundewsolutions.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       log-manager
 */

function sdw_event_manager_scripts()
{   
    // wp_enqueue_script( 'my_custom_script', plugins_url('js/jquery.repeatable.js', __FILE__ ), '1.0.0', false );
    wp_enqueue_style( 'bootstra-css', "https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css", '5.0.0', false );
    wp_enqueue_script('bootstrap-js', "https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js", '5.0.0', true);
}
add_action('admin_enqueue_scripts', 'sdw_event_manager_scripts');

function sdw_add_event_db()
{      
    global $wpdb; 
    $db_table_name = $wpdb->prefix . 'event_db';
    $charset_collate = $wpdb->get_charset_collate();
    if($wpdb->get_var( "show tables like '$db_table_name'" ) != $db_table_name ) 
    {
        $sql = "CREATE TABLE $db_table_name (
                    id int(11) NOT NULL auto_increment,
                    ip_address varchar(15) NOT NULL,
                    userid varchar(200) NOT NULL,
                    event_time varchar(10) NOT NULL,
                    object_type ENUM('Post', 'User', 'Media') NOT NULL,
                    severity ENUM('low', 'medium', 'high') NOT NULL,
                    event_type ENUM( 'created','modified','trashed','restored','deleted','Login','Login Failed','Logout','error','bug','notice','published') NOT NULL,
                    message varchar(1000) NOT NULL,
                    UNIQUE KEY id (id)
            ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
} 
register_activation_hook( __FILE__, 'sdw_add_event_db' );

function register_options_page()
{
    $plugin_slug = "log_manager";

    add_menu_page( 'Event Log', 'Event Log', 'edit', $plugin_slug, null,  plugins_url('/assets/icon/icon.png', __FILE__), '58',);

    add_submenu_page(
        $plugin_slug,
        'Dashboard',
        'Dashboard',
        'manage_options',
        'log_manager_dashboard',
        'log_manager_func'
    );
}
add_action('admin_menu', 'register_options_page');

function log_manager_func()
{
    require (plugin_dir_path(__FILE__) . '/includes/admin/dashboard.php');
}

/** invoking diff. events into plugin */
require_once plugin_dir_path( __FILE__ ).'/includes/events/post-log-events.php';
require_once plugin_dir_path( __FILE__ ).'/includes/events/user-log-events.php';

?>