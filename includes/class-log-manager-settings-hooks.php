<?php
/**
 * Admin Settings Hooks - Improved Logging
 *
 * Handles logging of WordPress admin configuration changes
 * for all WordPress core settings pages.
 *
 * @since 1.0.6
 * @package Log_Manager
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Log_Manager_Settings_Hooks
{
    public function __construct()
    {
        // WordPress Admin → Settings
        add_action('updated_option', [$this, 'sdw_log_option_updated'], 10, 3);
        add_action('add_option', [$this, 'sdw_log_option_added'], 10, 2);
        add_action('delete_option', [$this, 'sdw_log_option_deleted'], 10, 1);

        // Appearance
        add_action('switch_theme', [$this, 'sdw_log_theme_switched'], 10, 3);
        add_action('customize_save_after', [$this, 'sdw_log_customizer_saved']);

        // System updates
        add_action('upgrader_process_complete', [$this, 'sdw_log_system_update'], 10, 2);
    }

    /**
     * Log updated option
     */
    public function sdw_log_option_updated($option, $old_value, $new_value)
    {
        if ($this->sdw_should_ignore_option($option) || $old_value === $new_value) {
            return;
        }

        $old_val = $this->sdw_format_value($old_value);
        $new_val = $this->sdw_format_value($new_value);

        $label = $this->sdw_get_option_label($option) ?: $option;

        $message = sprintf(
            "%s was updated from '%s' to '%s'",
            esc_html($label),
            esc_html($old_val),
            esc_html($new_val)
        );

        $this->sdw_create_log('modified', 'Settings', $message, 'notice');
    }

    /**
     * Log newly added option
     */
    public function sdw_log_option_added($option, $value)
    {
        if ($this->sdw_should_ignore_option($option)) {
            return;
        }

        $value_str = $this->sdw_format_value($value);
        $label = $this->sdw_get_option_label($option) ?: $option;

        $message = sprintf(
            "%s was added with value '%s'",
            esc_html($label),
            esc_html($value_str)
        );

        $this->sdw_create_log('created', 'Settings', $message, 'info');
    }

    /**
     * Log deleted option
     */
    public function sdw_log_option_deleted($option)
    {
        if ($this->sdw_should_ignore_option($option)) {
            return;
        }

        $label = $this->sdw_get_option_label($option) ?: $option;

        $message = sprintf(
            "%s was deleted",
            esc_html($label)
        );

        $this->sdw_create_log('deleted', 'Settings', $message, 'warning');
    }

    /**
     * Log theme switch
     */
    public function sdw_log_theme_switched($new_name, $new_theme, $old_theme)
    {
        $message = sprintf(
            "Theme switched from '%s' to '%s'",
            $old_theme->get('Name'),
            $new_theme->get('Name')
        );

        $this->sdw_create_log('modified', 'Theme', $message, 'notice');
    }

    /**
     * Log customizer save
     */
    public function sdw_log_customizer_saved()
    {
        $this->sdw_create_log(
            'modified',
            'Theme',
            'Theme customizer settings were updated',
            'notice'
        );
    }

    /**
     * Log system updates (core/theme/plugin)
     */
    public function sdw_log_system_update($upgrader, $hook_extra)
    {
        if (empty($hook_extra['type']) || empty($hook_extra['action']) || $hook_extra['action'] !== 'update') {
            return;
        }

        $object_type = ucfirst($hook_extra['type']);

        $this->sdw_create_log(
            'modified',
            $object_type,
            sprintf('%s update completed successfully', $object_type),
            'info'
        );
    }

    /**
     * Format value for logging
     */
    private function sdw_format_value($value)
    {
        if (is_array($value) || is_object($value)) {
            return wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }

    /**
     * Get human-readable label for option
     */
    private function sdw_get_option_label($option)
    {
        $map = [
            'blogname' => 'Site title',
            'blogdescription' => 'Tagline',
            'siteurl' => 'Site URL',
            'home' => 'Home URL',
            'admin_email' => 'Administrator email',
            'users_can_register' => 'User registration setting',
            'default_role' => 'Default user role',
            'timezone_string' => 'Timezone',
            'date_format' => 'Date format',
            'time_format' => 'Time format',
            'start_of_week' => 'Week start day',
            'default_category' => 'Default post category',
            'default_post_format' => 'Default post format',
            'show_on_front' => 'Your homepage displays',
            'page_on_front' => 'Homepage',
            'page_for_posts' => 'Posts page',
            'posts_per_page' => 'Blog pages show at most',
            'posts_per_rss' => 'Syndication feeds show the most recent',
            'rss_use_excerpt' => 'For each post in a feed, include',
            'blog_public' => 'Search engine visibility',
            'default_ping_status' => 'Default ping status',
            'default_comment_status' => 'Default comment status',
            'comment_registration' => 'Users must be registered to comment',
            'page_comments' => 'Enable threaded comments',
            'comments_per_page' => 'Comments per page',
            'avatar_default' => 'Default avatar',
            'thumbnail_size_w' => 'Thumbnail width',
            'thumbnail_size_h' => 'Thumbnail height',
            'medium_size_w' => 'Medium width',
            'medium_size_h' => 'Medium height',
            'large_size_w' => 'Large width',
            'large_size_h' => 'Large height',
            'uploads_use_yearmonth_folders' => 'Organize uploads by month/year',
            'permalink_structure' => 'Permalink structure',
            'category_base' => 'Category base',
            'tag_base' => 'Tag base',
            'page_on_privacy_policy' => 'Privacy policy page',
        ];

        return $map[$option] ?? null;
    }

    /**
     * Ignore internal / noisy options
     */
    private function sdw_should_ignore_option($option)
    {
        return (
            strpos($option, '_transient') === 0 ||
            strpos($option, '_site_transient') === 0 ||
            strpos($option, 'theme_mods_') === 0
        );
    }

    /**
     * Create a log entry
     */
    private function sdw_create_log($event_type, $object_type, $message, $severity)
    {
        if (!class_exists('Log_Manager_Logger')) {
            return;
        }

        Log_Manager_Logger::insert([
            'userid'      => strval(get_current_user_id()),
            'severity'    => $severity,
            'ip_address'  => $this->sdw_get_client_ip(),
            'event_type'  => $event_type,
            'event_time'  => current_time('mysql'),
            'object_type' => $object_type,
            'message'     => $message,
        ]);
    }

    /**
     * Get client IP address
     */
    private function sdw_get_client_ip()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        }

        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }
}
