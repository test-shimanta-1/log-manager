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
    wp_enqueue_script( 'bootstra-css', "https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css", '5.0.0', false );
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
    $plugin_slug = "event_manager";

    add_menu_page( 'Event Log', 'Event Log', 'edit', $plugin_slug, null,  plugins_url('/assets/icon/icon.png', __FILE__), '58',);

    add_submenu_page(
        $plugin_slug,
        'Dashboard',
        'Dashboard',
        'manage_options',
        'event_manager_dashboard',
        'event_manager_func'
    );
}
add_action('admin_menu', 'register_options_page');

function event_manager_func()
{
    require (plugin_dir_path(__FILE__) . 'admin/dashboard.php');
}

/** post events */
// add_action('save_post', 'sdw_post_events', 10, 3);
// function sdw_post_events($post_id, $post, $update){
//     if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
//         return;
//     }

//      if ( $post->post_status === 'auto-draft' ) {
//         return;
//     }

//     if($update){
//           global $wpdb; 
//         $db_table_name = $wpdb->prefix . 'event_db';
//         $sql = "INSERT INTO $db_table_name(id,ip_address,userid,event_time,message) VALUES (NULL, '4212', 2, 'dummy 1', 'update $post_id $post->post_status')";
//         require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); 
//         dbDelta( $sql );

//     }
//     else{
//        global $wpdb; 
//         $db_table_name = $wpdb->prefix . 'event_db';
//         $sql = "INSERT INTO $db_table_name(id,ip_address,userid,event_time,message) VALUES (NULL, '4212', 2, 'dummy 1', 'published $post_id $post->post_status')";
//         require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); 
//         dbDelta( $sql );
//     } 
// }

add_action( 'save_post', 'sdw_post_events', 10, 3 );
function sdw_post_events( $post_id, $post, $update ) {

    // 1. Autosave
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    // 2. Revisions
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }

    // 3. Ignore auto-draft (THIS IS THE KEY)
    if ( $post->post_status === 'auto-draft' ) {
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'event_db';

    if ( $update ) {
        // UPDATE
        $wpdb->insert(
            $table,
            [
                'ip_address' => '4212',
                'userid'     => get_current_user_id(),
                'event_time' => '',
                'message'    => 'Post updated: ' . $post_id,
            ]
        );
    } else {
        // REAL INSERT (runs once)
        $wpdb->insert(
            $table,
            [
                'ip_address' => '4212',
                'userid'     => get_current_user_id(),
                'event_time' => '',
                'message'    => 'Post created: ' . $post_id,
            ]
        );
    }
}


?>