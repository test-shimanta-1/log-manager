<?php
/*
 * Plugin Name:       IITK Log Manager
 * Description:       Handle the log access info for this iitk sub wevsite.
 * Version:           1.10.3
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Sundew team
 * Author URI:        https://sundewsolutions.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       iitk-log-manager
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
                    warning_level ENUM('low', 'medium', 'high') NOT NULL,
                    event_type ENUM('created', 'modified', 'trashed', 'deleted') NOT NULL,
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
    require (plugin_dir_path(__FILE__) . 'admin/dashboard.php');
}

/** post events */
add_action('before_delete_post', 'sdw_post_delete_log', 10, 1);
function sdw_post_delete_log($post_id){
    if (wp_is_post_revision($post_id)) {
        return;
    }

    $post = get_post($post_id);
    if (!$post) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'event_db';
    $wpdb->insert(
            $table,
            [
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'userid'     => get_current_user_id(),
                'event_time' => date("Y/m/d"),
                'object_type' => 'Post',
                'warning_level' => 'high' ,
                'event_type' => 'deleted',
                'message'    => 'POST DELETED. '.'| Post ID: '.$post->ID.' |POST TYPE '.get_post_type($post->ID).' |POST TITLE '.get_the_title($post->ID),
            ]
        );
}

add_action( 'transition_post_status', 'sdw_post_logs', 10, 3 );
function sdw_post_logs( $new_status, $old_status, $post ) {
    global $wpdb;
    $table = $wpdb->prefix . 'event_db';

    if($old_status === 'trash' && $new_status === 'draft'){
        $wpdb->insert(
            $table,
            [
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'userid'     => get_current_user_id(),
                'event_time' => date("Y/m/d"),
                'object_type' => 'Post',
                'warning_level' => 'high' ,
                'event_type' => 'modified',
                'message'    => 'POST RESTORED. '.'| Post ID: '.$post->ID.' |POST TYPE '.get_post_type($post->ID).' |POST TITLE '.get_the_title($post->ID),
            ]
        );
    }
    else if($new_status === 'trash'){
        $wpdb->insert(
            $table,
            [
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'userid'     => get_current_user_id(),
                'event_time' => date("Y/m/d"),
                'object_type' => 'Post',
                'warning_level' => 'high' ,
                'event_type' => 'trashed',
                'message'    => 'POST HAS BEEN TRASHED. '.'| Post ID: '.$post->ID.' |POST TYPE '.get_post_type($post->ID).' |POST TITLE '.get_the_title($post->ID),
            ]
        );
    }
    else if(wp_get_post_revisions($post->ID)){
        if($old_status === 'draft' && $new_status === 'publish'){
            $wpdb->insert(
                $table,
                [
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'userid'     => get_current_user_id(),
                    'event_time' => date("Y/m/d"),
                    'object_type' => 'Post',
                    'warning_level' => 'medium' ,
                    'event_type' => 'modified',
                    'message'    => 'FROM DRAFT TO PUBLISH. '.'| Post ID: '.$post->ID.' |POST TYPE '.get_post_type($post->ID).' |POST TITLE '.get_the_title($post->ID),
                ]
            );
        }else if($old_status !== 'draft' && $new_status === 'publish'){
            $wpdb->insert(
                $table,
                [
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'userid'     => get_current_user_id(),
                    'event_time' => date("Y/m/d"),
                    'object_type' => 'Post',
                    'warning_level' => 'medium' ,
                    'event_type' => 'modified',
                    'message'    => 'POST HAS BEEN UPDATED. '.'| Post ID: '.$post->ID.' |POST TYPE '.get_post_type($post->ID).' |POST TITLE '.get_the_title($post->ID),
                ]
            );
        }
    }
    else if ( $old_status === 'auto-draft' && $new_status !== 'auto-draft' ) {
        $wpdb->insert(
            $table,
            [
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'userid'     => get_current_user_id(),
                'event_time' => date("Y/m/d"),
                'object_type' => 'Post',
                'warning_level' => 'low' ,
                'event_type' => 'created',
                'message'    => 'NEW POST CREATED. '.'| Post ID: '.$post->ID.' |POST TYPE '.get_post_type($post->ID).' |POST TITLE '.get_the_title($post->ID),
            ]
        );

    }
}




?>