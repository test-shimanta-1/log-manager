<?php
/**
 * Post/Post-Type related event hooks class file
 * 
 * 
 * @since 1.0.1
 * @package Log_Manager
 */
class Log_Manager_Post_Hooks
{

    /**
     * Store term data before update / delete
     * @since 1.0.1
     */
    protected static $before_term_update = [];

    protected static $featured_image_logged = [];

    /**
     * Register post-related hooks for logging post lifecycle events.
     *
     * Hooks into:
     * - transition_post_status: Logs post creation, update, restore, publish, and trash events.
     * - before_delete_post: Logs permanent post deletion.
     *
     * @since 1.0.1
     * @return void
     */
    public function __construct()
    {
        // Post Type lifecycle hooks
        add_action('transition_post_status', [$this, 'sdw_post_changes_logs'], 10, 3);
        add_action('before_delete_post', [$this, 'sdw_post_delete_log'], 10, 1);

        // Taxonomy lifesycle hooks
        add_action('created_term', [$this, 'sdw_taxonomy_create_log'], 10, 3);
        add_action('edit_term', [$this, 'sdw_capture_term_before_update'], 10, 3);
        add_action('edited_term', [$this, 'sdw_taxonomy_update_log'], 10, 3);
        add_action('pre_delete_term', [$this, 'sdw_capture_term_before_delete'], 10, 2);
        add_action('delete_term', [$this, 'sdw_taxonomy_delete_log'], 10, 4);
        add_action('set_object_terms', [$this, 'sdw_taxonomy_assignment_log'], 10, 6);
        add_action('acf/save_post', [$this, 'sdw_capture_taxonomy_acf_before_save'], 5);
        add_action('acf/save_post', [$this, 'sdw_taxonomy_acf_after_log'], 20);

        // Featured image hooks
        add_action('add_post_meta', [$this, 'sdw_featured_image_added'], 10, 4);
        add_action('update_post_meta', [$this, 'sdw_featured_image_updated'], 10, 4);
        add_action('delete_post_meta', [$this, 'sdw_featured_image_removed'], 10, 4);
    }


    /**
     * Logs when a featured image is added to a post.
     *
     * Triggered when `_thumbnail_id` post meta is added.
     *
     * @param int    $meta_id  
     * @param int    $post_id  
     * @param string $meta_key 
     * @param mixed  $meta_value Meta value (attachment ID).
     *
     * @return void
     */
    public function sdw_featured_image_added($meta_id, $post_id, $meta_key, $meta_value = null)
    {
        if ($meta_key !== '_thumbnail_id') {
            return;
        }

        if (isset(self::$featured_image_logged[$post_id])) {
            return;
        }

        self::$featured_image_logged[$post_id] = true;
        $this->log_featured_image_event($post_id, $meta_value, 'assigned');
    }

    /**
     * Logs when a featured image is updated on a post.
     *
     * @param int    $meta_id   
     * @param int    $post_id   
     * @param string $meta_key  
     * @param mixed  $meta_value New attachment ID.
     *
     * @return void
     */
    public function sdw_featured_image_updated($meta_id, $post_id, $meta_key, $meta_value = null)
    {
        if ($meta_key !== '_thumbnail_id') {
            return;
        }

        if (empty($meta_value)) {
            return; // removal handled separately
        }

        if (isset(self::$featured_image_logged[$post_id])) {
            return;
        }

        self::$featured_image_logged[$post_id] = true;
        $this->log_featured_image_event($post_id, $meta_value, 'modified');
    }

    /**
     * Logs when a featured image is removed from a post.
     *
     * @param array  $meta_ids   
     * @param int    $post_id    
     * @param string $meta_key   
     * @param mixed  $meta_value Meta value.
     *
     * @return void
     */
    public function sdw_featured_image_removed($meta_ids, $post_id, $meta_key, $meta_value = null)
    {
        if ($meta_key !== '_thumbnail_id') {
            return;
        }

        if (isset(self::$featured_image_logged[$post_id])) {
            return;
        }

        self::$featured_image_logged[$post_id] = true;
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        Log_Manager_Logger::insert([
            'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'userid' => get_current_user_id(),
            'event_time' => current_time('mysql'),
            'object_type' => 'Media',
            'severity' => 'notice',
            'event_type' => 'deleted',
            'message' =>
                'Featured image removed from post.' .
                '<br/>Post ID: <b>' . absint($post_id) . '</b>' .
                '<br/>Post Title: <b>' . esc_html($post->post_title) . '</b>' .
                '<br/>Edit Post: <a href="' . esc_url(get_edit_post_link($post_id)) . '" target="_blank">Edit</a>'
        ]);
    }

    /**
     * Inserts a log entry for featured image events.
     *
     * @param int    $post_id       
     * @param int    $attachment_id 
     * @param string $event_type    Event type (assigned|modified).
     *
     * @return void
     */
    private function log_featured_image_event($post_id, $attachment_id, $event_type)
    {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $media_url = wp_get_attachment_url($attachment_id);
        if (!$media_url) {
            return;
        }

        Log_Manager_Logger::insert([
            'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'userid' => get_current_user_id(),
            'event_time' => current_time('mysql'),
            'object_type' => 'Media',
            'severity' => 'notice',
            'event_type' => $event_type,
            'message' =>
                'Media ID <b>' . absint($attachment_id) . '</b> ' . esc_html($event_type) . ' as featured image.' .
                '<br/>Post ID: <b>' . absint($post_id) . '</b>' .
                '<br/>Post Title: <b>' . esc_html($post->post_title) . '</b>' .
                '<br/>Media URL: <a href="' . esc_url($media_url) . '" target="_blank">View Media</a>'
        ]);
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
     * @since 1.0.1
     * @return void
     */
    public function sdw_post_changes_logs($new_status, $old_status, $post)
    {
        // Avoid autosaves and revisions
        if (wp_is_post_autosave($post->ID) || wp_is_post_revision($post->ID)) {
            return;
        }

        // Ensure valid post object
        if (empty($post) || empty($post->ID)) {
            return;
        }

        /**
         * Resolve correct post ID
         * (In case WordPress passes a revision object)
         */
        $post_id = $post->ID;

        if ($post->post_type === 'revision' && !empty($post->post_parent)) {
            $post_id = $post->post_parent;
        }

        $user_id = get_current_user_id();
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        $time = date('Y/m/d H:i:s');
        $title = get_the_title($post->ID);
        $type = get_post_type($post->ID);

        $event_type = '';
        $severity = 'notice';
        $message = '';

        /**
         * 1. Restore from trash
         */
        if ($old_status === 'trash' && $new_status !== 'trash') {
            $event_type = 'restored';
            $message = 'Post has been restored.';
        }
        /**
         * 2. Move to trash
         */ elseif ($new_status === 'trash') {
            $event_type = 'trashed';
            $message = 'Post has been moved to trash.';
        }
        /**
         * 3. New post created
         */ elseif ($old_status === 'auto-draft' && $new_status !== 'auto-draft') {
            $event_type = 'created';
            $message = 'New post has been created.';
        }
        /**
         * 4. Any other status change (draft → publish, publish → private, etc.)
         */ elseif ($old_status !== $new_status) {

            $event_type = 'modified';
            if ($new_status === 'publish' && $old_status !== 'publish') {
                $message = 'Post has been published.';
            } elseif ($new_status === 'private') {
                $message = 'Post has been set to private.';
            } else {
                $message = sprintf(
                    'Post status changed from %s to %s.',
                    esc_html($old_status),
                    esc_html($new_status)
                );
            }
        }
        /**
         * 5. Post content updated (same status)
         */ else {
            $event_type = 'modified';
            $message = 'Post content has been updated.';
        }

        $edit_post_url = get_edit_post_link($post);

        // Append post details
        $message .=
            '<br/>Post Title: <b>' . esc_html($title) . '</b>' .
            '<br/>Post ID: <b>' . absint($post->ID) . '</b>' .
            '<br/>Post Type: <b>' . esc_html($type) . '</b>' .
            '<br/>View post: <b><a href="' . esc_url($edit_post_url) . '" target="_blank">view post in editor</a></b>';

        // Append revisions URL if available
        if (wp_get_post_revisions($post->ID)) {
            $message .= '<br/>Post Revisions: <a href="' . esc_url(wp_get_post_revisions_url($post->ID)) . '" target="_blank">View revisions</a>';
        }

        // Insert log entry
        Log_Manager_Logger::insert([
            'ip_address' => $ip,
            'userid' => $user_id,
            'event_time' => $time,
            'object_type' => 'Post',
            'severity' => $severity,
            'event_type' => $event_type,
            'message' => $message,
        ]);
    }

    /**
     * Logs permanent deletion of a post.
     *
     * @param int $post_id Deleted post ID.
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

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'userid' => get_current_user_id(),
            'event_time' => date('Y/m/d H:i:s'),
            'object_type' => 'Post',
            'severity' => 'notice',
            'event_type' => 'deleted',
            'message' => 'Permanently deleted the post. ' . '<br/>Post Title: <b>' . get_the_title($post->ID) . '</b><br> Post ID: <b>' . $post->ID . '</b> <br/>Post Type: <b>' . get_post_type($post->ID) . '</b>',
        ]);
    }

    /**
     * Logs creation of a taxonomy term.
     *
     * @param int    $term_id  Term ID.
     * @param int    $tt_id    Term taxonomy ID.
     * @param string $taxonomy Taxonomy slug.
     * 
     * @since 1.0.1
     * @return void
     */
    public function sdw_taxonomy_create_log($term_id, $tt_id, $taxonomy)
    {
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term)) {
            return;
        }
        $term_link = get_edit_term_link($term_id, $taxonomy);

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid' => get_current_user_id(),
            'event_time' => date('Y/m/d H:i:s'),
            'object_type' => 'Taxonomy',
            'severity' => 'notice',
            'event_type' => 'created',
            'message' =>
                'Taxonomy term created.' .
                '<br/>Name: <b><a href="' . esc_url($term_link) . '" target="_blank">' .
                esc_html($term->name) . '</a></b>' .
                '<br/>ID: <b>' . $term_id . '</b>' .
                '<br/>Taxonomy: <b>' . esc_html($taxonomy) . '</b>',
        ]);
    }

    /**
     * Stores taxonomy term data before update for comparison.
     *
     * @param int    $term_id  Term ID.
     * @param int    $tt_id    Term taxonomy ID.
     * @param string $taxonomy Taxonomy slug.
     * 
     * @since 1.0.1
     * @return void
     */
    public function sdw_capture_term_before_update($term_id, $tt_id, $taxonomy)
    {
        $term = get_term($term_id, $taxonomy);
        if (!$term || is_wp_error($term))
            return;

        self::$before_term_update[$term_id] = [
            'name' => $term->name,
            'slug' => $term->slug,
            'parent' => (int) $term->parent,
            'description' => $term->description,
        ];
    }

    /**
     * Logs taxonomy term updates by comparing old and new values.
     *
     * @param int    $term_id  Term ID.
     * @param int    $tt_id    Term taxonomy ID.
     * @param string $taxonomy Taxonomy slug.
     * 
     * @since 1.0.1
     * @return void
     */
    public function sdw_taxonomy_update_log($term_id, $tt_id, $taxonomy)
    {
        if (!isset(self::$before_term_update[$term_id])) {
            return;
        }

        $before = self::$before_term_update[$term_id];
        $after = get_term($term_id, $taxonomy);

        if (!$after || is_wp_error($after)) {
            return;
        }

        $term_link = get_edit_term_link($term_id, $taxonomy);
        $changes = [];

        // Parent change
        if ($before['parent'] !== (int) $after->parent) {
            if ($after->parent) {
                $parent = get_term($after->parent, $taxonomy);
                $parent_link = get_edit_term_link($parent->term_id, $taxonomy);

                $changes[] =
                    'Category <b><a href="' . esc_url($term_link) . '" target="_blank">' .
                    esc_html($after->name) . '</a></b> (ID ' . $term_id .
                    ') assigned as child of <b><a href="' . esc_url($parent_link) . '" target="_blank">' .
                    esc_html($parent->name) . '</a></b> (ID ' . $parent->term_id . ')';
            }
        }

        // Name change
        if ($before['name'] !== $after->name) {
            $changes[] =
                'Taxonomy name changed from <b>' . esc_html($before['name']) .
                '</b> to <b><a href="' . esc_url($term_link) . '" target="_blank">' .
                esc_html($after->name) . '</a></b> (ID ' . $term_id . ')';
        }

        // Slug change
        if ($before['slug'] !== $after->slug) {
            $changes[] =
                'Slug changed for <b><a href="' . esc_url($term_link) . '" target="_blank">' .
                esc_html($after->name) . '</a></b> (ID ' . $term_id . '): <b>' .
                esc_html($before['slug']) . '</b> → <b>' .
                esc_html($after->slug) . '</b>';
        }

        // Description change
        if ($before['description'] !== $after->description) {
            $old_desc = trim($before['description']) === '' ? '""' : esc_html($before['description']);
            $new_desc = trim($after->description) === '' ? '""' : esc_html($after->description);

            $changes[] =
                'Description updated for <b><a href="' . esc_url($term_link) . '" target="_blank">' .
                esc_html($after->name) . '</a></b> (ID ' . $term_id . ')<br/>' .
                '<b>Old:</b> ' . $old_desc .
                '<br/><b>New:</b> ' . $new_desc;
        }

        if (empty($changes)) {
            unset(self::$before_term_update[$term_id]);
            return;
        }

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid' => get_current_user_id(),
            'event_time' => date('Y/m/d H:i:s'),
            'object_type' => 'Taxonomy',
            'severity' => 'notice',
            'event_type' => 'modified',
            'message' => implode('<br/>', $changes . "<b>abacus</b>"),
        ]);

        unset(self::$before_term_update[$term_id]);
    }

    /**
     * Stores taxonomy term data before deletion.
     *
     * @param int    $term_id  Term ID.
     * @param string $taxonomy Taxonomy slug.
     * 
     * @since 1.0.1
     * @return void
     */
    public function sdw_capture_term_before_delete($term_id, $taxonomy)
    {
        self::$before_term_update['delete_' . $term_id] = get_term($term_id, $taxonomy);
    }

    /**
     * Logs deletion of a taxonomy term.
     *
     * @param int    $term_id      Term ID.
     * @param int    $tt_id        Term taxonomy ID.
     * @param string $taxonomy     Taxonomy slug.
     * @param object $deleted_term Deleted term object.
     * 
     * @since 1.0.1
     * @return void
     */
    public function sdw_taxonomy_delete_log($term_id, $tt_id, $taxonomy, $deleted_term)
    {
        $term = self::$before_term_update['delete_' . $term_id] ?? $deleted_term;
        if (!$term)
            return;

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid' => get_current_user_id(),
            'event_time' => date('Y/m/d H:i:s'),
            'object_type' => 'Taxonomy',
            'severity' => 'warning',
            'event_type' => 'deleted',
            'message' =>
                'Taxonomy term deleted.<br/>Name: <b>' . esc_html($term->name) .
                '</b><br/>ID: <b>' . $term_id . '</b>',
        ]);

        unset(self::$before_term_update['delete_' . $term_id]);
    }

    /**
     * Logs taxonomy term assignment or removal on an object.
     *
     * @param int    $object_id  Object (post) ID.
     * @param array  $terms      Assigned terms.
     * @param array  $tt_ids     New term taxonomy IDs.
     * @param string $taxonomy   Taxonomy slug.
     * @param bool   $append     Append mode flag.
     * @param array  $old_tt_ids Old term taxonomy IDs.
     * 
     * @since 1.0.1
     * @return void
     */
    public function sdw_taxonomy_assignment_log($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids)
    {
        // Only for posts + categories
        if ('post' !== get_post_type($object_id) || 'category' !== $taxonomy) {
            return;
        }

        $post = get_post($object_id);
        if (!$post) {
            return;
        }

        $new_term_ids = array_map('intval', (array) $terms);
        $old_term_ids = array_map('intval', (array) $old_tt_ids);

        $added_ids = array_diff($new_term_ids, $old_term_ids);
        $removed_ids = array_diff($old_term_ids, $new_term_ids);

        if (empty($added_ids) && empty($removed_ids)) {
            return;
        }

        $added_names = [];
        $removed_names = [];

        if (!empty($added_ids)) {
            $added_terms = get_terms([
                'taxonomy' => 'category',
                'include' => $added_ids,
                'hide_empty' => false,
            ]);
            $added_names = wp_list_pluck($added_terms, 'name');
        }

        if (!empty($removed_ids)) {
            $removed_terms = get_terms([
                'taxonomy' => 'category',
                'include' => $removed_ids,
                'hide_empty' => false,
            ]);
            $removed_names = wp_list_pluck($removed_terms, 'name');
        }

        $messages = [];

        if (!empty($added_names)) {
            $messages[] =
                '<b>' . esc_html(implode(', ', $added_names)) . '</b> category have been added to the post.';
        }

        if (!empty($removed_names)) {
            $messages[] =
                '<b>' . esc_html(implode(', ', $removed_names)) . '</b> category have been removed from the post.';
        }

        $messages[] =
            '<br/>Post Title: <b>' . esc_html($post->post_title) . '</b>' .
            '<br/>Post ID: <b>' . absint($object_id) . '</b>' .
            '<br/><a href="' . esc_url(get_edit_post_link($post)) . '" target="_blank">View post in editor</a>';

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid' => get_current_user_id(),
            'event_time' => date('Y/m/d H:i:s'),
            'object_type' => 'Taxonomy',
            'severity' => 'notice',
            'event_type' => 'assigned',
            'message' => implode('<br/>', $messages),
        ]);
    }


    /**
     * Stores taxonomy ACF field values before saving.
     *
     * @param string $post_id ACF post ID (term_x).
     * 
     * @since 1.0.1
     * @return void
     */
    public function sdw_capture_taxonomy_acf_before_save($post_id)
    {
        if (strpos($post_id, 'term_') !== 0)
            return;

        $term_id = (int) str_replace('term_', '', $post_id);

        $fields = get_fields($post_id);
        if (!$fields) {
            self::$before_term_update['acf_' . $term_id] = [];
            return;
        }

        self::$before_term_update['acf_' . $term_id] = $fields;
    }

    /**
     * Logs changes in taxonomy ACF fields after save.
     *
     * @param string $post_id ACF post ID (term_x).
     * 
     * @since 1.0.1
     * @return void
     */
    public function sdw_taxonomy_acf_after_log($post_id)
    {

        if (strpos($post_id, 'term_') !== 0) {
            return;
        }

        $term_id = (int) str_replace('term_', '', $post_id);
        $term = get_term($term_id);

        if (!$term || is_wp_error($term)) {
            return;
        }

        $term_link = get_edit_term_link($term_id, $term->taxonomy);
        $new_fields = get_fields($post_id) ?: [];
        $old_fields = self::$before_term_update['acf_' . $term_id] ?? [];

        if (!$new_fields) {
            return;
        }

        $changes = [];
        foreach ($new_fields as $field_key => $new_value) {
            $old_value = $old_fields[$field_key] ?? null;

            if ($old_value == $new_value) {
                continue;
            }

            $field_object = get_field_object($field_key, $post_id);
            $label = $field_object['label'] ?? $field_key;
            $old = ($old_value === null || $old_value === '') ? '""' : esc_html(print_r($old_value, true));
            $new = ($new_value === null || $new_value === '') ? '""' : esc_html(print_r($new_value, true));

            $changes[] =
                'Field <b>' . esc_html($label) . '</b> updated for taxonomy ' .
                '<b><a href="' . esc_url($term_link) . '" target="_blank">' .
                esc_html($term->name) . '</a></b> (ID ' . $term_id . ')<br/>' .
                '<b>Old:</b> ' . $old . '<br/>' .
                '<b>New:</b> ' . $new;
        }

        if (!$changes) {
            return;
        }

        Log_Manager_Logger::insert([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'userid' => get_current_user_id(),
            'event_time' => date('Y/m/d H:i:s'),
            'object_type' => 'Taxonomy',
            'severity' => 'notice',
            'event_type' => 'modified',
            'message' => implode('<br/><br/>', $changes),
        ]);

    }
}