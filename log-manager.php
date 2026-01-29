<?php
/*
 * Plugin Name:       Log Manager
 * Description:       Log Manager is a WordPress plugin that logs user login activity and actions performed on posts and pages. It offers a structured admin interface with pagination, sorting, severity-based filtering, and user-wise activity tracking. The plugin is designed to be extensible, with planned support for frontend activity logging, log exports, and automated cleanup of outdated records.
 * Text Domain:       log-manager
 * Version:           1.0.6
 * Author:            sundew team
 * Author URI:        https://sundewsolutions.com/
 * 
 * @package Log_Manager
 * 
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('LOG_MANAGER_VERSION', '1.0.6');
define('LOG_MANAGER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LOG_MANAGER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LOG_MANAGER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include plugin classes
require_once LOG_MANAGER_PLUGIN_DIR . 'includes/class-log-manager.php';
require_once LOG_MANAGER_PLUGIN_DIR . 'includes/class-log-manager-hooks.php';
require_once LOG_MANAGER_PLUGIN_DIR . 'includes/class-log-manager-export.php';
require_once LOG_MANAGER_PLUGIN_DIR . 'includes/class-log-manager-acf-tracker.php';

// Initialize plugin
add_action('plugins_loaded', function() {
    Log_Manager::init();
});

// Activation hook
register_activation_hook(__FILE__, ['Log_Manager', 'activate']);

// Deactivation hook
register_deactivation_hook(__FILE__, ['Log_Manager', 'deactivate']);

// Handle export requests early
add_action('admin_init', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'log-manager' && isset($_GET['export_type'])) {
        Log_Manager_Export::handle_export_request();
    }
});