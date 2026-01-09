<?php
/**
 * media related event hooks class file
 * 
 * @since 1.0.3
 * @package Log_Manager
 */

class Log_Manager_Media_Hooks
{
    /**
     * Register media-related hooks, such upload, modify, delete files
     *
     * @since 1.0.3
     * @return void
     */
    public function __construct()
    {
        add_action( 'add_attachment', [ $this, 'sdw_media_uploaded'], 10, 1 );
        add_action( 'attachment_updated', [ $this, 'sdw_media_updated' ], 10, 3 );
        add_action( 'wp_trash_post', [ $this, 'sdw_media_trashed'], 10, 1);
        add_action( 'untrash_post', [ $this, 'sdw_media_restored'], 10, 1);
        add_action( 'delete_attachment', [ $this, 'sdw_media_deleted'], 10, 1);
    }

    /**
     * Log media upload
     */
    public function sdw_media_uploaded( $attachment_id )
    {
        $attachment = get_post( $attachment_id );
        $file       = get_attached_file( $attachment_id );

        Log_Manager_Logger::insert([
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid'      => get_current_user_id(),
            'event_time'  => date('Y/m/d H:i:s'),
            'object_type' => 'Media',
            'severity'    => 'info',
            'event_type'  => 'published',
            'message'     => 'Media uploaded'
                . '<br/>ID: <b>' . $attachment_id . '</b>'
                . '<br/>File: <b>' . basename( $file ) . '</b>'
                . '<br/>Type: <b>' . $attachment->post_mime_type . '</b>',
        ]);
    }

    /**
     * Log media update (title, caption, description)
     */
    public function sdw_media_updated( $post_ID, $post_after, $post_before )
    {
        Log_Manager_Logger::insert([
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid'      => get_current_user_id(),
            'event_time'  => date('Y/m/d H:i:s'),
            'object_type' => 'Media',
            'severity'    => 'info',
            'event_type'  => 'modified',
            'message'     => 'Media details updated'
                . '<br/>ID: <b>' . $post_ID . '</b>'
                . '<br/>Old Title: <b>' . $post_before->post_title . '</b>'
                . '<br/>New Title: <b>' . $post_after->post_title . '</b>',
        ]);
    }

    /**
     * Log media trash
     */
    public function sdw_media_trashed( $post_id )
    {
        if ( get_post_type( $post_id ) !== 'attachment' ) {
            return;
        }

        Log_Manager_Logger::insert([
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid'      => get_current_user_id(),
            'event_time'  => date('Y/m/d H:i:s'),
            'object_type' => 'Media',
            'severity'    => 'warning',
            'event_type'  => 'trashed',
            'message'     => 'Media moved to trash'
                . '<br/>Media ID: <b>' . $post_id . '</b>',
        ]);
    }

    /**
     * Log media restore
     */
    public function sdw_media_restored( $post_id )
    {
        if ( get_post_type( $post_id ) !== 'attachment' ) {
            return;
        }

        Log_Manager_Logger::insert([
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid'      => get_current_user_id(),
            'event_time'  => date('Y/m/d H:i:s'),
            'object_type' => 'Media',
            'severity'    => 'info',
            'event_type'  => 'restored',
            'message'     => 'Media restored from trash'
                . '<br/>Media ID: <b>' . $post_id . '</b>',
        ]);
    }

    /**
     * Log media permanent delete
     */
    public function sdw_media_deleted( $attachment_id )
    {
        Log_Manager_Logger::insert([
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid'      => get_current_user_id(),
            'event_time'  => date('Y/m/d H:i:s'),
            'object_type' => 'Media',
            'severity'    => 'critical',
            'event_type'  => 'deleted',
            'message'     => 'Media permanently deleted' . '<br/>Media ID: <b>' . $attachment_id . '</b>',
        ]);
    }

}