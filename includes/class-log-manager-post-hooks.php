<?php
/**
 * Post/Post-Type related event hooks class file
 * 
 * 
 * @since 1.0.0
 * @package Log_Manager
 */

class Log_Manager_Post_Hooks
{
    /**
     * Register post-related hooks for logging post lifecycle events.
     *
     * Hooks into:
     * - transition_post_status: Logs post creation, update, restore, publish, and trash events.
     * - before_delete_post: Logs permanent post deletion.
     *
     * @return void
     */
    public function __construct()
    {
        add_action('transition_post_status', [$this, 'sdw_post_changes_logs'], 10, 3);
        add_action('before_delete_post', [$this, 'sdw_post_delete_log'], 10, 1);
    }

    /**
     * Log post status transitions and content updates.
     *
     * This method captures and logs various post lifecycle events such as:
     * - Post creation (auto-draft to any valid status)
     * - Post publishing (draft to publish)
     * - Post updates (publish to publish)
     * - Post trashing
     * - Post restoration from trash
     *
     * The log entry includes user ID, IP address, post details,
     * severity level, event type, and a descriptive message.
     *
     *
     * @param string  $new_status New post status.
     * @param string  $old_status Old post status.
     * @param WP_Post $post       Post object.
     *
     * @return void
     */
    public function sdw_post_changes_logs($new_status, $old_status, $post)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'event_db';

        if ($old_status === 'trash' && $new_status === 'draft') {
            /** trash -> draft post status change */
            $wpdb->insert(
                $table,
                [
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'userid' => get_current_user_id(),
                    'event_time' => date("Y/m/d"),
                    'object_type' => 'Post',
                    'severity' => 'notice',
                    'event_type' => 'restored',
                    'message' => 'Post has been restored. ' . '<br/>Post Title: <b>' . get_the_title($post->ID) . '</b><br> Post ID: <b>' . $post->ID . '</b> <br/>Post Type: <b>' . get_post_type($post->ID) . '</b>',
                ]
            );
        }
        else if ($new_status === 'trash') {
            /** direct move into trash */ 
            $wpdb->insert(
                $table,
                [
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'userid' => get_current_user_id(),
                    'event_time' => date("Y/m/d"),
                    'object_type' => 'Post',
                    'severity' => 'notice',
                    'event_type' => 'trashed',
                    'message' => 'Post has been trashed. ' . '<br/>Post Title: <b>' . get_the_title($post->ID) . '</b><br> Post ID: <b>' . $post->ID . '</b> <br/>Post Type: <b>' . get_post_type($post->ID) . '</b>',
                ]
            );
        }
        else if (wp_get_post_revisions($post->ID)) {
            /** 'draft' to 'publish' status change */ 
            if ($old_status === 'draft' && $new_status === 'publish') {
                $wpdb->insert(
                    $table,
                    [
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'userid' => get_current_user_id(),
                        'event_time' => date("Y/m/d"),
                        'object_type' => 'Post',
                        'severity' => 'notice',
                        'event_type' => 'modified',
                        'message' => 'Post status has been changed from draft to publish. ' . '<br/>Post Title: <b>' . get_the_title($post->ID) . '</b><br> Post ID: <b>' . $post->ID . '</b> <br/>Post Type: <b>' . get_post_type($post->ID) . '</b>',
                    ]
                );
            } else if ($old_status !== 'draft' && $new_status === 'publish') {
                /** post update */
                $revisions_url = wp_get_post_revisions_url($post->ID);
                $wpdb->insert(
                    $table,
                    [
                        'ip_address' => $_SERVER['REMOTE_ADDR'],
                        'userid' => get_current_user_id(),
                        'event_time' => date("Y/m/d"),
                        'object_type' => 'Post',
                        'severity' => 'notice',
                        'event_type' => 'modified',
                        'message' => 'Post has been updated. ' . '<br/>Post Title: <b>' . get_the_title($post->ID) . '</b><br> Post ID: <b>' . $post->ID . '</b> <br/>Post Type: <b>' . get_post_type($post->ID) . '</b>' . '<br/> Post revisions url:  <b><a href="' . $revisions_url . '" target="_blank">post revisions url</a></b>',
                    ]
                );
            }
        } else if ($old_status === 'auto-draft' && $new_status !== 'auto-draft') {
            $wpdb->insert(
                $table,
                [
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'userid' => get_current_user_id(),
                    'event_time' => date("Y/m/d"),
                    'object_type' => 'Post',
                    'severity' => 'notice',
                    'event_type' => 'created',
                    'message' => 'New post has been created. ' . '<br/>Post Title: <b>' . get_the_title($post->ID) . '</b><br> Post ID: <b>' . $post->ID . '</b> <br/>Post Type: <b>' . get_post_type($post->ID) . '</b>',
                ]
            );
        }
    }

    /**
     * This method logs an event when a post is permanently deleted
     *
     * @param int $post_id ID of the post being deleted.
     *
     * @return void
     */
    function sdw_post_delete_log($post_id)
    {
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
                'userid' => get_current_user_id(),
                'event_time' => date("Y/m/d"),
                'object_type' => 'Post',
                'severity' => 'notice',
                'event_type' => 'deleted',
                'message' => 'Permanently deleted the post. ' . '<br/>Post Title: <b>' . get_the_title($post->ID) . '</b><br> Post ID: <b>' . $post->ID . '</b> <br/>Post Type: <b>' . get_post_type($post->ID) . '</b>',
            ]
        );
    }

}