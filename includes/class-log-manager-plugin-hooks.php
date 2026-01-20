<?php
/**
 * Plugin Activity Hooks & Log Templates
 * 
 * Handles plugin lifecycle events and generates structured log messages.
 * 
 * @since 1.0.5
 * @package Log_Manager
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Log_Manager_Plugin_Hooks
{
    /**
     * Initialize all plugin-related hooks
     *
     * @since 1.0.5
     * @return void
     */
    public function __construct() {
        // Plugin installation
        add_action('upgrader_process_complete', [$this, 'sdw_log_plugin_installed'], 10, 2);
        
        // Plugin activation
        add_action('activated_plugin', [$this, 'sdw_log_plugin_activated'], 10, 2);
        
        // Plugin update
        add_action('upgrader_process_complete', [$this, 'sdw_log_plugin_updated'], 10, 2);
        
        // Plugin deactivation
        add_action('deactivated_plugin', [$this, 'sdw_log_plugin_deactivated'], 10, 2);
        
        // Plugin uninstallation (before deletion)
        add_action('delete_plugin', [$this, 'sdw_log_plugin_uninstalled'], 10, 1);
    }

    /**
     * Get plugin data by plugin file path
     *
     * @since 1.0.5
     * @param string $plugin_file Plugin file path (e.g., 'akismet/akismet.php')
     * @return array Plugin data
     */
    private static function sdw_get_plugin_data($plugin_file)
    {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
        
        return [
            'name' => $plugin_data['Name'] ?? 'Unknown Plugin',
            'version' => $plugin_data['Version'] ?? 'N/A',
            'uri' => $plugin_data['PluginURI'] ?? '',
            'file' => $plugin_file
        ];
    }

    /**
     * Get plugin details URL
     *
     * @since 1.0.5
     * @param string $plugin_file Plugin file path
     * @return string Plugin details URL
     */
    private static function sdw_get_plugin_details_url($plugin_file)
    {
        $plugin_slug = dirname($plugin_file);
        return admin_url("plugin-install.php?tab=plugin-information&plugin={$plugin_slug}");
    }

    /**
     * Get current user data
     *
     * @since 1.0.5
     * @return array User data
     */
    private static function sdw_get_current_user_data()
    {
        $user = wp_get_current_user();
        
        // Build full name (first + last if available, otherwise empty)
        $full_name = trim($user->first_name . ' ' . $user->last_name);
        if (empty($full_name)) {
            $full_name = '';
        }
        
        return [
            'id' => $user->ID,
            'full_name' => $full_name,
            'login' => $user->user_login,
            'email' => $user->user_email
        ];
    }

    /**
     * Create log entry for plugin events
     *
     * @since 1.0.5
     * 
     * @param string $event_type Event type
     * @param string $plugin_file Plugin file path
     * @param string $message Log message
     * @param bool $include_link Whether to include plugin details link
     * 
     * @return void
     */
    private static function sdw_create_plugin_log($event_type, $plugin_file, $message, $include_link = false)
    {
        $plugin_data = self::sdw_get_plugin_data($plugin_file);
        $user_data = self::sdw_get_current_user_data();
        
        // Format the user display
        $user_display = '';
        if (!empty($user_data['full_name'])) {
            $user_display = 'user: <b>' . $user_data['full_name'] . '</b> user id: ' . $user_data['id'];
        } else {
            $user_display = 'user id: ' . $user_data['id'];
        }
        
        // Build the complete message using sprintf
        $full_message = sprintf($message, $plugin_data['name'], $user_display);
        
        // Add plugin details link if requested
        if ($include_link) {
            $plugin_url = self::sdw_get_plugin_details_url($plugin_file);
            if ($plugin_url) {
                $full_message .= sprintf(' <a href="%s" target="_blank">View plugin details</a>', esc_url($plugin_url));
            }
        }
        
        // Prepare log data
        $log_data = [
            'userid' => strval($user_data['id']),
            'severity' => 'info',
            'ip_address' => self::sdw_get_client_ip(),
            'event_type' => $event_type,
            'event_time' => current_time('mysql'),
            'object_type' => 'Post', // Using 'Post' as general object type for plugins
            'message' => $full_message
        ];
        
        // Store the log
        if (class_exists('Log_Manager_Logger')) {
            Log_Manager_Logger::insert($log_data);
        }
    }

    /**
     * Get client IP address
     *
     * @since 1.0.5
     * @return string IP address
     */
    private static function sdw_get_client_ip()
    {
        $ip = '127.0.0.1';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return sanitize_text_field($ip);
    }

    /**
     * Log plugin installation
     *
     * @since 1.0.5
     * @param WP_Upgrader $upgrader Upgrader object
     * @param array $hook_extra Extra hook arguments
     * @return void
     */
    public static function sdw_log_plugin_installed($upgrader, $hook_extra)
    {
        if ($hook_extra['type'] !== 'plugin' || $hook_extra['action'] !== 'install') {
            return;
        }

        if (isset($hook_extra['plugin'])) {
            $plugin_file = $hook_extra['plugin'];
            $message = '%s was installed by %s';
            self::sdw_create_plugin_log('created', $plugin_file, $message);
        }
    }

    /**
     * Log plugin activation
     *
     * @since 1.0.5
     * @param string $plugin_file Plugin file path
     * @param bool $network_wide Whether network-wide activation
     * @return void
     */
    public static function sdw_log_plugin_activated($plugin_file, $network_wide)
    {
        $message = '%s has been activated by %s';
        self::sdw_create_plugin_log('published', $plugin_file, $message, true);
    }

    /**
     * Log plugin update
     *
     * @since 1.0.5
     * @param WP_Upgrader $upgrader Upgrader object
     * @param array $hook_extra Extra hook arguments
     * @return void
     */
    public static function sdw_log_plugin_updated($upgrader, $hook_extra)
    {
        if ($hook_extra['type'] !== 'plugin' || $hook_extra['action'] !== 'update') {
            return;
        }

        if (isset($hook_extra['plugin'])) {
            $plugin_file = $hook_extra['plugin'];
            $message = '%s was updated by %s';
            self::sdw_create_plugin_log('modified', $plugin_file, $message, true);
        } elseif (isset($hook_extra['plugins'])) {
            foreach ($hook_extra['plugins'] as $plugin_file) {
                $message = '%s was updated by %s';
                self::sdw_create_plugin_log('modified', $plugin_file, $message, true);
            }
        }
    }

    /**
     * Log plugin deactivation
     *
     * @since 1.0.5
     * @param string $plugin_file Plugin file path
     * @param bool $network_wide Whether network-wide deactivation
     * @return void
     */
    public static function sdw_log_plugin_deactivated($plugin_file, $network_wide)
    {
        $message = '%s has been deactivated by %s';
        self::sdw_create_plugin_log('trashed', $plugin_file, $message, true);
    }

    /**
     * Log plugin uninstallation
     *
     * @since 1.0.5
     * @param string $plugin_file Plugin file path
     * @return void
     */
    public static function sdw_log_plugin_uninstalled($plugin_file)
    {
        $message = '%s was uninstalled by %s';
        self::sdw_create_plugin_log('deleted', $plugin_file, $message);
    }

}