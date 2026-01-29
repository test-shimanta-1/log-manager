<?php
/**
 * Log Manager Uninstall Handler
 *
 * @package Log_Manager
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Drop the log table
$table_name = $wpdb->prefix . 'log_manager_logs';
$wpdb->query("DROP TABLE IF EXISTS $table_name");