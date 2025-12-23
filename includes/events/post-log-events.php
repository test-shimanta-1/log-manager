<?php

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

    /** trash -> draft post status change */
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
    /** direct move into trash */
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
        /** 'draft' to 'publish' status change */
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
            $revisions_url = wp_get_post_revisions_url($post);
            $wpdb->insert(
                $table,
                [
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'userid'     => get_current_user_id(),
                    'event_time' => date("Y/m/d"),
                    'object_type' => 'Post',
                    'warning_level' => 'medium' ,
                    'event_type' => 'modified',
                    'message'    => 'Post has been updated. '."\n\n".' Post ID: '.$post->ID."\n\n".' POST TYPE '.get_post_type($post->ID)."\n\n".' POST TITLE '.get_the_title($post->ID)."\n\n".' POST REVISIONS URL:  <a href="'.$revisions_url.'" target="_blank">post revisions url</a>',
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

