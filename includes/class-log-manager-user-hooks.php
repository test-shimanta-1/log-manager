<?php
/**
 * user related event hooks class file
 * 
 * Handles logging of user authentication events such as
 * login success, login failure, and logout.
 * 
 * @since 1.0.2
 * @package Log_Manager
 */

class Log_Manager_User_Hooks
{

    private $meta_cache = [];

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
        add_filter('authenticate', [$this, 'sdw_capture_all_login_attempts'], 30, 3);
    
        // User lifecycle
        add_action('user_register', [$this, 'sdw_log_user_created']);
        add_action('profile_update', [$this, 'sdw_log_user_updated'], 10, 2);
        add_action('delete_user', [$this, 'sdw_log_user_deleted'], 10, 2);

        // User meta (core + ACF + custom)
        add_action('update_user_meta', [$this, 'cache_old_user_meta'], 5, 4);
        add_action('added_user_meta', [$this, 'sdw_log_user_meta_added'], 10, 4);
        add_action('updated_user_meta', [$this, 'sdw_log_user_meta_updated'], 10, 4);
        add_action('deleted_user_meta', [$this, 'log_user_meta_deleted'], 10, 4);
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
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $message = sprintf(
            'Login successful.<br/>User ID: <b>%d</b><br/>Role: <b>%s</b><br/>Email: <b>%s</b>%s',
            $user_id,
            esc_html(implode(', ', $user->roles)),
            esc_html($user->user_email),
            $this->sdw_get_user_full_name($user)
        );

        Log_Manager_Logger::insert([
            'ip_address'  => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'userid'      => $user_id,
            'event_time'  => current_time('mysql'),
            'object_type' => 'User',
            'severity'    => 'info',
            'event_type'  => 'logged-in',
            'message'     => $message,
        ]);

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
    public function sdw_log_user_logout($user_id)
    {
        if (!$user_id) {
            return;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }

        $message = sprintf(
            'User logged out.<br/>User ID: <b>%d</b><br/>Role: <b>%s</b><br/>Email: <b>%s</b>%s',
            $user_id,
            esc_html(implode(', ', $user->roles)),
            esc_html($user->user_email),
            $this->sdw_get_user_full_name($user)
        );

        Log_Manager_Logger::insert([
            'ip_address'  => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'userid'      => $user_id,
            'event_time'  => current_time('mysql'),
            'object_type' => 'User',
            'severity'    => 'info',
            'event_type'  => 'logout',
            'message'     => $message,
        ]);
        
    }


    /**
     * Log failed login attempts via the authenticate filter.
     *
     * Captures failed authentication for existing users
     * (wrong password) and non-existent users, while
     * ignoring successful logins and logout requests.
     *
     * @since 1.0.2
     *
     * @param WP_User|WP_Error|null $user
     * @param string               $username
     * @param string               $password
     *
     * @return WP_User|WP_Error|null
     */
    public function sdw_capture_all_login_attempts($user, $username, $password)
    {
        // Skip during logout
        if (did_action('wp_logout')) {
            return $user;
        }

        // Skip empty username (logout / cron / API)
        if (empty($username)) {
            return $user;
        }

        // Ignore successful authentication
        if (!is_wp_error($user) || empty($username)) {
            return $user;
        }

        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');

        // Try resolving user
        $user_obj = get_user_by('login', $username);
        if (!$user_obj && is_email($username)) {
            $user_obj = get_user_by('email', $username);
        }

        /**
         * Existing user → wrong password (WARNING)
         */
        if ($user_obj instanceof WP_User) {

            $message = sprintf(
                'Wrong password attempt.<br/>User ID: <b>%d</b><br/>Username: <b>%s</b><br/>Email: <b>%s</b>%s',
                $user_obj->ID,
                esc_html($user_obj->user_login),
                esc_html($user_obj->user_email),
                $this->sdw_get_user_full_name($user_obj)
            );

            Log_Manager_Logger::insert([
                'ip_address'  => $ip,
                'userid'      => $user_obj->ID,
                'event_time'  => current_time('mysql'),
                'object_type' => 'User',
                'severity'    => 'warning',
                'event_type'  => 'login-failed',
                'message'     => $message,
            ]);

            return $user;
        }

        /**
         * Non-existent user → ALERT
         */
        Log_Manager_Logger::insert([
            'ip_address'  => $ip,
            'userid'      => 0,
            'event_time'  => current_time('mysql'),
            'object_type' => 'User',
            'severity'    => 'alert',
            'event_type'  => 'login-failed',
            'message'     => 'Login attempt with non-existent username: <b>' . esc_html($username) . '</b>',
        ]);

        return $user;

    }

    /**
     * Get the user's full name safely.
     *
     * This helper prevents storing blank or meaningless name
     * values in the log records.
     *
     * @since 1.0.2
     *
     * @param WP_User $user User object.
     *
     * @return string Full name if available, otherwise empty string.
     */
    private function sdw_get_user_full_name($user)
    {
        if (!$user instanceof WP_User) {
            return '';
        }

        $first = trim($user->user_firstname);
        $last  = trim($user->user_lastname);

        if ($first === '' && $last === '') {
            return '';
        }

        return '<br/>Full Name: <b>' . esc_html(trim($first . ' ' . $last)) . '</b>';
    }

    /**
     * On user's registration
     *
     * @since 1.0.5
     *
     * @param integer user_id
     *
     * @return void
     */
    public function sdw_log_user_created($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user) return;

        $message = sprintf(
            'User created.<br/>
            User ID: <b>%d</b><br/>
            Username: <b>%s</b><br/>
            Role: <b>%s</b>',
            $user_id,
            esc_html($user->user_login),
            esc_html(implode(', ', $user->roles))
        );

        $this->sdw_insert_log($user_id, 'created', 'User', 'notice', $message);
    }

    /**
     * On user's info update[single/multiple info, user meta fields]
     *
     * @since 1.0.5
     *
     * @param integer user_id
     * @param integer old_user_data
     *
     * @return void
     */
    public function sdw_log_user_updated($user_id, $old_user_data)
    {
        $new_user = get_userdata($user_id);
        if (!$new_user || !$old_user_data) return;

        $label_map = [
            'user_email'   => 'Email Address',
            'user_url'     => 'Website URL',
            'display_name' => 'Display Name',
            'nickname'     => 'Nickname',
            'description'  => 'Biography',
        ];

        $ignored = ['user_activation_key'];
        $changes = [];

        foreach ($new_user->data as $field => $new_value) {

            if (in_array($field, $ignored, true)) {
                continue;
            }

            $old_value = $old_user_data->$field ?? '';
            if ((string) $old_value !== (string) $new_value) {
                // Password (event only)
                if ($field === 'user_pass') {
                    $changes[$field] = [
                        'label' => 'Password',
                        'old'   => null,
                        'new'   => null,
                    ];
                    continue;
                }

                $label = $label_map[$field]
                    ?? ucwords(str_replace('_', ' ', $field));

                $changes[$field] = [
                    'label' => $label,
                    'old'   => $old_value ?: '—',
                    'new'   => $new_value ?: '—',
                ];
            }
        }

        if (empty($changes)) return;

        $user_link = $this->get_clickable_user($new_user);

        // if single field change
        if (count($changes) === 1) {
            $field  = array_key_first($changes);
            $change = $changes[$field];

            // Password
            if ($field === 'user_pass') {
                $message = sprintf(
                    'User password has been changed.<br/>
                    User: %s',
                    $user_link
                );

            } else {
                $message = sprintf(
                    'User %s has been changed.<br/>
                    User: %s<br/>
                    Previous Value: <b>%s</b><br/>
                    Current Value: <b>%s</b>',
                    esc_html(strtolower($change['label'])),
                    $user_link,
                    esc_html($change['old']),
                    esc_html($change['new'])
                );
            }

            $this->sdw_insert_log($user_id, 'modified', 'User', 'info', $message);
            return;
        }

        // multiple fields change
        $rows = [];
        foreach ($changes as $field => $change) {

            if ($field === 'user_pass') {
                $rows[] = '<b>Password</b><br/>Changed';
                continue;
            }

            $rows[] = sprintf(
                '<b>%s</b><br/>
                Previous Value: <b>%s</b><br/>
                Current Value: <b>%s</b>',
                esc_html($change['label']),
                esc_html($change['old']),
                esc_html($change['new'])
            );
        }

        $message = sprintf(
            'User profile has been updated.<br/>
            User: %s<br/><br/>%s',
            $user_link,
            implode('<br/><br/>', $rows)
        );

        $this->sdw_insert_log($user_id, 'modified', 'User', 'info', $message);
    }

    /**
     * On user's delete
     *
     * @since 1.0.5
     *
     * @param integer user_id
     * @param boolean reassign
     *
     * @return void
     */
    public function sdw_log_user_deleted($user_id, $reassign)
    {
        $message = sprintf(
            'User deleted.<br/>User ID: <b>%d</b>',
            $user_id
        );

        $this->sdw_insert_log($user_id, 'deleted', 'User', 'warning', $message);
    }

    /**
     * On user's meta added
     *
     * @since 1.0.5
     *
     * @param integer meta_id
     * @param boolean user_id
     * @param string mets_key
     * @param type meta_value
     *
     * @return void
     */
    public function sdw_log_user_meta_added($meta_id, $user_id, $meta_key, $meta_value)
    {
        if ($this->ignore_meta($meta_key)) return;
        $label = $this->get_meta_label($meta_key);

        $message = sprintf(
            'User meta field added.<br/>
            Field: <b>%s</b><br/>
            Value: <b>%s</b>',
            esc_html($label),
            esc_html($this->stringify($meta_value))
        );

        $this->sdw_insert_log($user_id, 'created', 'User', 'notice', $message);
    }

    /**
     * On user's meta update
     *
     * @since 1.0.5
     *
     * @param integer meta_id
     * @param boolean user_id
     * @param string mets_key
     * @param type meta_value
     *
     * @return void
     */
    public function sdw_log_user_meta_updated($meta_id, $user_id, $meta_key, $meta_value)
    {
        if ($this->ignore_meta($meta_key)) return;
        $old_value = $this->meta_cache[$user_id][$meta_key] ?? '';

        if ((string) $old_value === (string) $meta_value) return;
        $label = $this->get_meta_label($meta_key);

        $message = sprintf(
            'User meta field updated.<br/>
            Field: <b>%s</b><br/>
            Previous Value: <b>%s</b><br/>
            Current Value: <b>%s</b>',
            esc_html($label),
            esc_html($this->stringify($old_value)),
            esc_html($this->stringify($meta_value))
        );

        $this->sdw_insert_log($user_id, 'modified', 'User', 'info', $message);
    }

    /**
     * On user's meta delete
     *
     * @since 1.0.5
     *
     * @param integer meta_id
     * @param boolean user_id
     * @param string mets_key
     * @param type meta_value
     *
     * @return void
     */
    public function log_user_meta_deleted($meta_ids, $user_id, $meta_key, $meta_value)
    {
        if ($this->ignore_meta($meta_key)) return;
        $label = $this->get_meta_label($meta_key);

        $message = sprintf(
            'User meta field deleted.<br/>
            Field: <b>%s</b><br/>
            Value: <b>%s</b>',
            esc_html($label),
            esc_html($this->stringify($meta_value))
        );

        $this->sdw_insert_log($user_id, 'deleted', 'User', 'warning', $message);
    }

    /**
     * Temporarily stores a user’s existing meta values before an update
     *
     * @since 1.0.5
     *
     * @param integer meta_id
     * @param boolean user_id
     * @param string mets_key
     * @param type meta_value
     *
     * @return void
     */
    public function cache_old_user_meta($meta_id, $user_id, $meta_key, $meta_value)
    {
        if ($this->ignore_meta($meta_key)) return;
        $this->meta_cache[$user_id][$meta_key] = get_user_meta($user_id, $meta_key, true);
    }

    /**
     * Inserts a log entry into the Log Manager database.
     *
     * @since 1.0.5
     *
     * @param int    $user_id   ID of the user associated with the event.
     * @param string $event     Event type (e.g. created, modified, deleted).
     * @param string $object    Object type (e.g. User, Post, Media).
     * @param string $severity  Log severity level (e.g. notice, warning, error).
     * @param string $message   Human-readable log message (HTML allowed).
     *
     * @return void
     */
    private function sdw_insert_log($user_id, $event, $object, $severity, $message)
    {
        Log_Manager_Logger::insert([
            'userid'      => $user_id,
            'ip_address'  => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
            'event_time'  => current_time('mysql'),
            'object_type' => $object,
            'event_type'  => $event,
            'severity'    => $severity,
            'message'     => $message,
        ]);
    }

    /**
     * Converts a meta value into a readable string for logging.
     *
     * @since 1.0.5
     *
     * @param mixed $value Meta value to normalize.
     *
     * @return string Readable string representation of the value.
     */
    private function stringify($value)
    {
        if (is_array($value)) return json_encode($value);
        if ($value === '') return '—';
        return (string) $value;
    }

    /**
     * Determines whether a user meta key should be ignored for logging.
     *
     * @since 1.0.5
     *
     * @param string $key User meta key.
     *
     * @return bool True if the meta key should not be logged, false otherwise.
     */
    private function ignore_meta($key)
    {
        return str_starts_with($key, '_') || in_array($key, [
            'session_tokens',
            'wp_capabilities',
            'wp_user_level'
        ], true);
    }

    /**
     * Resolves a human-friendly label for a user meta field.
     *
     * @since 1.0.5
     *
     * @param string $meta_key User meta key.
     *
     * @return string Human-readable meta field label.
     */
    private function get_meta_label($meta_key)
    {
        if (function_exists('acf_get_field')) {
            $field = acf_get_field($meta_key);
            if ($field && !empty($field['label'])) {
                return $field['label'];
            }
        }

        return ucwords(str_replace('_', ' ', $meta_key));
    }

    /**
     * Generates a clickable admin link for a user profile.
     *
     * @since 1.0.5
     *
     * @param WP_User $user User object.
     *
     * @return string HTML anchor tag linking to the user edit page.
     */
    private function get_clickable_user($user)
    {
        $url = admin_url('user-edit.php?user_id=' . $user->ID);

        return sprintf(
            '<b><a href="%s" target="_blank">%s</a></b>',
            esc_url($url),
            esc_html($user->user_login)
        );
    }
   
}