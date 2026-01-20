<?php

/**
 * Main Plugin Class File
 * 
 * 
 * @since 1.0.1
 * @package Log_Manager
 */

class Log_Manager
{

    /**
     * Constructor.
     *
     * Loads all plugin dependencies on plugin initialization.
     *
     * @since 1.0.1
     */
    public function __construct()
    {
        $this->load_dependencies();
    }

    /**
     * Load the required plugin dependencies.
     *
     * Includes all class files required to run the plugin 
     * such as admin, dashboard, database, and post hooks.
     *
     * @since 1.0.1
     * @return void
     */
    private function load_dependencies()
    {
        require_once LOG_MANAGER_PATH . 'includes/class-log-manager-admin.php';
        require_once LOG_MANAGER_PATH . 'includes/class-log-manager-dashboard.php';
        require_once LOG_MANAGER_PATH . 'includes/class-log-manager-db.php';
        require_once LOG_MANAGER_PATH . 'includes/class-log-manager-post-hooks.php';
        require_once LOG_MANAGER_PATH . 'includes/class-log-manager-user-hooks.php';
        require_once LOG_MANAGER_PATH . 'includes/class-log-manager-settings.php';
        require_once LOG_MANAGER_PATH . 'includes/class-log-manager-logger.php';
        require_once LOG_MANAGER_PATH . 'includes/class-log-manager-media-hooks.php';
        require_once LOG_MANAGER_PATH . 'includes/class-log-manager-plugin-hooks.php';
    }

    /**
     * Initialize plugin components.
     *
     * Creates instances of all core plugin classes
     * and registers their hooks with WordPress.
     *
     * @since 1.0.1
     * @return void
     */
    public function initialize()
    {
        new Log_Manager_Admin();
        new Log_Manager_Dashboard();
        new Log_Manager_DB();
        new Log_Manager_Post_Hooks();
        new Log_Manager_User_Hooks();
        new Log_Manager_Settings();
        new Log_Manager_Media_Hooks();
        new Log_Manager_Plugin_Hooks();
    }
}