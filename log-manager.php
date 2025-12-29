<?php
/*
 * Plugin Name:       Log Manager
 * Description:       Log Manager is a WordPress plugin that logs user login activity and actions performed on posts and pages. It offers a structured admin interface with pagination, sorting, severity-based filtering, and user-wise activity tracking. The plugin is designed to be extensible, with planned support for frontend activity logging, log exports, and automated cleanup of outdated records.
 * Text Domain:       log-manager
 * Version:           1.0.0
 * Author:            sundew team
 * Author URI:        https://sundewsolutions.com/
 * 
 * 
 * @package Log_Manager
 * @since 1.0.0
 * 
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Constants.
define('LOG_MANAGER_VERSION', '1.1.0');
define('LOG_MANAGER_PATH', plugin_dir_path(__FILE__));
define('LOG_MANAGER_URL', plugin_dir_url(__FILE__));
define('LOG_MANAGER_FILE', __FILE__);

// Core Includes.
require_once LOG_MANAGER_PATH . 'includes/class-log-manager.php';


function initialize_log_manager()
{
    $plugin = new Log_Manager();
    $plugin->initialize();
}

// Initialize
initialize_log_manager();

?>