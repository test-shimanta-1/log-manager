<?php
/**
 * Log Manager main class file
 * 
 * It handle plugin database initialization, admin page render ui etc.
 * 
 * @package Log_Manager
 * @since 1.0.6
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Log_Manager
{
    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Database table name
     */
    const TABLE_NAME = 'log_manager_logs';

    /**
     * Severity levels
     */
    const SEVERITY_LEVELS = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug'
    ];

    /**
     * Initialize plugin
     * 
     * @return Log_Manager Singleton instance
     */
    public static function sdw_init()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->setup_hooks();
    }

    /**
     * Setup WordPress hooks
     * 
     * @return void
     */
    private function setup_hooks()
    {
        // Initialize on WordPress init
        add_action('init', [$this, 'sdw_init_plugin']);
    }

    /**
     * Initialize plugin components
     * 
     * @return void
     */
    public function sdw_init_plugin()
    {
        // Initialize hooks handler
        Log_Manager_Hooks::init();

        add_action('admin_init', [__CLASS__, 'sdw_handle_post_requests'], 1);
        add_action('admin_init', [__CLASS__, 'sdw_handle_delete_requests'], 1);
    }

    /**
     * Plugin activation
     * 
     * @return void
     */
    public static function sdw_activate()
    {
        // Create database table
        self::sdw_create_table();
    }

    /**
     * Plugin deactivation
     * 
     * @return void
     */
    public static function sdw_deactivate()
    {
    }

    /**
     * Create database table with optimized indexes
     * 
     * @return void
     */
    private static function sdw_create_table()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            user_id BIGINT UNSIGNED,
            user_ip VARCHAR(45),
            severity ENUM('emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug') DEFAULT 'info',
            action VARCHAR(100) NOT NULL,
            object_type VARCHAR(50),
            object_id BIGINT UNSIGNED,
            object_name VARCHAR(255),
            details LONGTEXT,
            PRIMARY KEY (id),
            INDEX idx_timestamp (timestamp),
            INDEX idx_user_id (user_id),
            INDEX idx_severity (severity),
            INDEX idx_action (action),
            INDEX idx_object_type (object_type),
            INDEX idx_object_id (object_id),
            INDEX idx_timestamp_severity (timestamp, severity),
            INDEX idx_user_action (user_id, action),
            INDEX idx_object (object_type, object_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Log an activity with HTML support
     * 
     * @param string $action Action performed
     * @param string $object_type Type of object
     * @param int $object_id Object ID
     * @param string $object_name Object name
     * @param array $details Additional details
     * @param string $severity Severity level
     * @return bool|int Result of logging operation
     */
    public static function sdw_log($action, $object_type = '', $object_id = 0, $object_name = '', $details = [], $severity = 'info')
    {
        // Get log destination from settings
        $destination = Log_Manager_Settings::sdw_get_log_destination();

        // Call appropriate logging method
        if ($destination === 'textfile') {
            return self::sdw_log_to_textfile($action, $object_type, $object_id, $object_name, $details, $severity);
        } else {
            return self::sdw_log_to_database($action, $object_type, $object_id, $object_name, $details, $severity);
        }
    }

    /**
     * Log to database (your existing database code)
     * 
     * @param string $action Action performed
     * @param string $object_type Type of object
     * @param int $object_id Object ID
     * @param string $object_name Object name
     * @param array $details Additional details
     * @param string $severity Severity level
     * @return bool|int Result of database insertion
     */
    private static function sdw_log_to_database($action, $object_type = '', $object_id = 0, $object_name = '', $details = [], $severity = 'info')
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Format details for storage
        $formatted_details = is_array($details) ? $details : [];

        // Process details to ensure HTML is properly stored
        foreach ($formatted_details as $key => $value) {
            // If value contains HTML, make sure it's properly formatted
            if (is_string($value) && preg_match('/<[^>]+>/', $value)) {
                // Already HTML, keep as is
                continue;
            } elseif (is_array($value) || is_object($value)) {
                // Convert arrays/objects to JSON
                $formatted_details[$key] = $value;
            }
        }

        $data = [
            'user_id' => get_current_user_id() ?: 0,
            'user_ip' => self::sdw_get_user_ip(),
            'severity' => $severity,
            'action' => sanitize_text_field($action),
            'object_type' => sanitize_text_field($object_type),
            'object_id' => absint($object_id),
            'object_name' => sanitize_text_field($object_name),
            'details' => wp_json_encode($formatted_details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timestamp' => current_time('mysql')
        ];

        // Insert into database
        $result = $wpdb->insert($table_name, $data);

        // Error logging for debugging
        if (false === $result) {
            error_log('Log Manager Error: Failed to insert log. Error: ' . $wpdb->last_error);
        }

        return $result;
    }

    /**
     * Log to text file - SIMPLE VERSION
     * 
     * @param string $action Action performed
     * @param string $object_type Type of object
     * @param int $object_id Object ID
     * @param string $object_name Object name
     * @param array $details Additional details
     * @param string $severity Severity level
     * @return bool Success of file write operation
     */
    private static function sdw_log_to_textfile($action, $object_type = '', $object_id = 0, $object_name = '', $details = [], $severity = 'info')
    {
        $folder_path = Log_Manager_Settings::get_textfile_path();

        if (empty($folder_path)) {
            error_log('Log Manager Error: Text file path not configured.');
            return false;
        }

        // Create folder if doesn't exist
        if (!file_exists($folder_path)) {
            if (!wp_mkdir_p($folder_path)) {
                error_log('Log Manager Error: Cannot create folder: ' . $folder_path);
                return false;
            }
        }

        // Check if folder is writable
        if (!is_writable($folder_path)) {
            error_log('Log Manager Error: Folder is not writable: ' . $folder_path);
            return false;
        }

        // Create filename with current date
        $filename = date('Y-m-d') . '-logs.txt';
        $file_path = trailingslashit($folder_path) . $filename;

        // Prepare log entry
        $timestamp = current_time('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $user_info = $user_id ? get_userdata($user_id)->user_login : 'System';
        $ip = self::sdw_get_user_ip();

        // Format details as JSON
        $details_json = !empty($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : '{}';

        // Create log line
        $log_line = sprintf(
            "\n[%s] [%s] [User: %s] [IP: %s] [Action: %s] [Object: %s#%d %s] Details: %s\n",
            $timestamp,
            strtoupper($severity),
            $user_info,
            $ip,
            $action,
            $object_type,
            $object_id,
            $object_name,
            $details_json
        );

        // Write to file
        $result = file_put_contents($file_path, $log_line, FILE_APPEND | LOCK_EX);

        if ($result === false) {
            error_log('Log Manager Error: Failed to write to text file: ' . $file_path);
            return false;
        }

        return true;
    }

    /**
     * Get logs with pagination and HTML support
     * 
     * @param int $page Page number
     * @param int $per_page Items per page
     * @param array $filters Filter criteria
     * @return array Logs data with pagination info
     */
    public static function sdw_get_logs($page = 1, $per_page = 50, $filters = [])
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $where = ['1=1'];
        $params = [];

        // Apply filters
        if (!empty($filters['severity'])) {
            $where[] = 'severity = %s';
            $params[] = $filters['severity'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = %s';
            $params[] = $filters['action'];
        }

        if (!empty($filters['object_type'])) {
            $where[] = 'object_type = %s';
            $params[] = $filters['object_type'];
        }

        if (!empty($filters['object_id'])) {
            $where[] = 'object_id = %d';
            $params[] = $filters['object_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'timestamp >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'timestamp <= %s';
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(object_name LIKE %s OR details LIKE %s OR action LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $where_sql = implode(' AND ', $where);

        // Calculate offset for pagination
        $offset = max(0, ($page - 1) * $per_page);

        // Optimized query with EXPLAIN hints
        $query = "SELECT SQL_CALC_FOUND_ROWS * FROM $table_name WHERE $where_sql ORDER BY timestamp DESC";
        $query .= " LIMIT %d OFFSET %d";

        $params[] = absint($per_page);
        $params[] = absint($offset);

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        $results = $wpdb->get_results($query);

        // Handle database errors
        if ($wpdb->last_error) {
            error_log('Log Manager Database Error: ' . $wpdb->last_error);
            return [];
        }

        // Get total count using FOUND_ROWS() for better performance
        $total_logs = $wpdb->get_var('SELECT FOUND_ROWS()');

        // Decode details with HTML preservation
        foreach ($results as $log) {
            if (!empty($log->details)) {
                $decoded = json_decode($log->details, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $log->details = $decoded;
                } else {
                    $unserialized = maybe_unserialize($log->details);
                    if (is_array($unserialized) || is_object($unserialized)) {
                        $log->details = (array) $unserialized;
                    }
                }
            }
        }

        return [
            'logs' => $results,
            'total' => $total_logs
        ];
    }

    /**
     * Get logs count with filters
     * 
     * @param array $filters Filter criteria
     * @return int Number of logs matching filters
     */
    public static function sdw_get_logs_count($filters = [])
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $where = ['1=1'];
        $params = [];

        // Apply filters
        if (!empty($filters['severity'])) {
            $where[] = 'severity = %s';
            $params[] = $filters['severity'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = %s';
            $params[] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'timestamp >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'timestamp <= %s';
            $params[] = $filters['date_to'];
        }

        $where_sql = implode(' AND ', $where);
        $query = "SELECT COUNT(*) FROM $table_name WHERE $where_sql";
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return $wpdb->get_var($query);
    }

    /**
     * Get logs grouped by action
     * 
     * @param int $page Page number
     * @param int $per_page Items per page
     * @return array Logs grouped by action
     */
    public static function sdw_get_logs_by_action($page = 1, $per_page = 10)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $offset = ($page - 1) * $per_page;

        $query = $wpdb->prepare(
            "SELECT action, COUNT(*) as count 
             FROM $table_name 
             GROUP BY action 
             ORDER BY count DESC 
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get logs grouped by user
     * 
     * @param int $page Page number
     * @param int $per_page Items per page
     * @return array Logs grouped by user
     */
    public static function sdw_get_logs_by_user($page = 1, $per_page = 10)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $offset = ($page - 1) * $per_page;

        $query = $wpdb->prepare(
            "SELECT user_id, COUNT(*) as count 
             FROM $table_name 
             WHERE user_id > 0 
             GROUP BY user_id 
             ORDER BY count DESC 
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get user IP address
     * 
     * @return string User IP address
     */
    private static function sdw_get_user_ip()
    {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ip_list[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /**
     * Get activity summary
     * 
     * @return array Summary statistics
     */
    public static function sdw_get_summary()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        return [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
            'today' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE DATE(timestamp) = %s",
                    current_time('Y-m-d')
                )
            ),
            'yesterday' => $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE DATE(timestamp) = %s",
                    date('Y-m-d', strtotime('-1 day'))
                )
            ),
            'users' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_name WHERE user_id > 0"),
            'errors' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE severity IN ('error', 'critical', 'alert', 'emergency')"),
            'warnings' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE severity = 'warning'"),
            'notices' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE severity = 'notice'"),
            'info' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE severity = 'info'"),
            'debug' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE severity = 'debug'")
        ];
    }

    /**
     * Add admin menu
     * 
     * @return void
     */
    public static function sdw_add_admin_menu()
    {
        add_menu_page(
            __('Log Manager', 'log-manager'),
            __('Log Manager', 'log-manager'),
            'manage_options',
            'log-manager',
            [__CLASS__, 'sdw_render_admin_page'],
            'dashicons-tide',
            30
        );

        add_submenu_page(
            'log-manager',
            __('Settings', 'log-manager'),
            __('Settings', 'log-manager'),
            'manage_options',
            'log-manager-settings',
            ['Log_Manager_Settings', 'sdw_render_settings_page']  // ← Use Settings class
        );
    }

    /**
     * Render admin page
     * 
     * @return void
     */
    public static function sdw_render_admin_page()
    {
        // Initialize filters array
        $filters = [];

        // Handle reset parameter FIRST
        if (isset($_GET['reset']) && $_GET['reset'] == '1') {
            // Clear all stored filters
            delete_transient('log_manager_current_filters_' . get_current_user_id());
            delete_transient('log_manager_current_per_page_' . get_current_user_id());

            // Remove reset parameter from URL
            wp_safe_redirect(admin_url('admin.php?page=log-manager'));
            exit;
        }

        // Check if we're viewing filtered results (only if NOT reset)
        if (isset($_GET['filtered']) && $_GET['filtered'] == '1') {
            // Retrieve stored filters
            $filters = get_transient('log_manager_current_filters_' . get_current_user_id());
            $per_page = get_transient('log_manager_current_per_page_' . get_current_user_id());

            // If no stored filters, use defaults
            if ($filters === false) {
                $filters = [];
            }
            if ($per_page === false) {
                $per_page = get_option('posts_per_page', 10);
            }
        } else {
            // For direct GET requests (not from POST redirect), use URL parameters
            $per_page = get_option('posts_per_page', 10);

            if (!empty($_GET['severity'])) {
                $filters['severity'] = sanitize_text_field($_GET['severity']);
            }
            if (!empty($_GET['user_id'])) {
                $filters['user_id'] = intval($_GET['user_id']);
            }
            if (!empty($_GET['action'])) {
                $filters['action'] = sanitize_text_field($_GET['action']);
            }
            if (!empty($_GET['object_type'])) {
                $filters['object_type'] = sanitize_text_field($_GET['object_type']);
            }
            if (!empty($_GET['date_from'])) {
                $filters['date_from'] = sanitize_text_field($_GET['date_from']);
            }
            if (!empty($_GET['date_to'])) {
                $filters['date_to'] = sanitize_text_field($_GET['date_to']);
            }
            if (!empty($_GET['search'])) {
                $filters['search'] = sanitize_text_field($_GET['search']);
            }
            if (!empty($_GET['per_page'])) {
                $per_page = intval($_GET['per_page']);
            }
        }

        // Get current page
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

        $per_page_options = [10, 20, 30, 50, 100, 150, 200, 250, 500];
        if (!in_array($per_page, $per_page_options)) {
            $per_page_options[] = $per_page;
            sort($per_page_options);
        }

        // Get logs with pagination
        $logs_data = self::sdw_get_logs($current_page, $per_page, $filters);
        $logs = $logs_data['logs'];
        $total_logs = $logs_data['total'];
        $total_pages = ceil($total_logs / $per_page);

        $summary = self::sdw_get_summary();

        // Get unique actions for filter
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $actions = $wpdb->get_col("SELECT DISTINCT action FROM $table_name ORDER BY action");

        // Get unique object types for filter
        $object_types = $wpdb->get_col("SELECT DISTINCT object_type FROM $table_name WHERE object_type != '' ORDER BY object_type");

        ?>
        <div class="wrap">
            <h1><?php _e('Activity Logs', 'log-manager'); ?></h1>

            <!-- Summary -->
            <div class="log-manager-summary"
                style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2 style="margin-top: 0;"><?php _e('Overview', 'log-manager'); ?></h2>
                <div style="display: flex; gap: 30px; flex-wrap: wrap;">
                    <div>
                        <h3 style="margin: 0 0 5px 0; color: #2271b1;"><?php echo esc_html(number_format($summary['total'])); ?>
                        </h3>
                        <p style="margin: 0; color: #646970;"><?php _e('Total Logs', 'log-manager'); ?></p>
                    </div>
                    <div>
                        <h3 style="margin: 0 0 5px 0; color: #00a32a;"><?php echo esc_html(number_format($summary['today'])); ?>
                        </h3>
                        <p style="margin: 0; color: #646970;"><?php _e('Today', 'log-manager'); ?></p>
                    </div>
                    <div>
                        <h3 style="margin: 0 0 5px 0; color: #d63638;">
                            <?php echo esc_html(number_format($summary['errors'])); ?></h3>
                        <p style="margin: 0; color: #646970;"><?php _e('Errors', 'log-manager'); ?></p>
                    </div>
                    <div>
                        <h3 style="margin: 0 0 5px 0; color: #ffb900;">
                            <?php echo esc_html(number_format($summary['warnings'])); ?></h3>
                        <p style="margin: 0; color: #646970;"><?php _e('Warnings', 'log-manager'); ?></p>
                    </div>
                    <div>
                        <h3 style="margin: 0 0 5px 0; color: #f0c33c;"><?php echo esc_html(number_format($summary['users'])); ?>
                        </h3>
                        <p style="margin: 0; color: #646970;"><?php _e('Active Users', 'log-manager'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="log-manager-filters"
    style="background: #fff; padding: 12px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
    
    <form method="post" action="<?php echo admin_url('admin.php?page=log-manager'); ?>">
        <?php wp_nonce_field('log_manager_filter', 'log_manager_filter_nonce'); ?>
        <input type="hidden" name="log_manager_filter" value="1">
        
        <!-- Single row layout -->
        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;">
            
            <!-- Severity -->
            <div style="min-width: 100px;">
                <label for="severity" style="display: block; margin-bottom: 3px; font-size: 11px; font-weight: 600; color: #646970;">
                    <?php _e('Severity', 'log-manager'); ?>
                </label>
                <select name="severity" id="severity" style="width: 100%; padding: 5px; font-size: 12px; height: 32px;">
                    <option value=""><?php _e('All', 'log-manager'); ?></option>
                    <?php foreach (self::SEVERITY_LEVELS as $level): ?>
                        <option value="<?php echo esc_attr($level); ?>" <?php selected(!empty($filters['severity']) && $filters['severity'] === $level); ?>>
                            <?php echo esc_html(ucfirst($level)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- User -->
            <div style="min-width: 120px;">
                <label for="user_id" style="display: block; margin-bottom: 3px; font-size: 11px; font-weight: 600; color: #646970;">
                    <?php _e('User', 'log-manager'); ?>
                </label>
                <?php
                wp_dropdown_users([
                    'name' => 'user_id',
                    'show_option_all' => __('All Users', 'log-manager'),
                    'selected' => !empty($filters['user_id']) ? $filters['user_id'] : 0,
                    'include_selected' => true,
                    'style' => 'width: 100%; padding: 5px; font-size: 12px; height: 32px;'
                ]);
                ?>
            </div>
            
            <!-- Action -->
            <div style="min-width: 120px;">
                <label for="action" style="display: block; margin-bottom: 3px; font-size: 11px; font-weight: 600; color: #646970;">
                    <?php _e('Action', 'log-manager'); ?>
                </label>
                <select name="action" id="action" style="width: 100%; padding: 5px; font-size: 12px; height: 32px;">
                    <option value=""><?php _e('All', 'log-manager'); ?></option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?php echo esc_attr($action); ?>" <?php selected(!empty($filters['action']) && $filters['action'] === $action); ?>>
                            <?php echo esc_html(self::sdw_format_action($action)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Object Type -->
            <div style="min-width: 100px;">
                <label for="object_type" style="display: block; margin-bottom: 3px; font-size: 11px; font-weight: 600; color: #646970;">
                    <?php _e('Type', 'log-manager'); ?>
                </label>
                <select name="object_type" id="object_type" style="width: 100%; padding: 5px; font-size: 12px; height: 32px;">
                    <option value=""><?php _e('All', 'log-manager'); ?></option>
                    <?php foreach ($object_types as $type): ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected(!empty($filters['object_type']) && $filters['object_type'] === $type); ?>>
                            <?php echo esc_html(ucfirst($type)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Date Range -->
            <div style="min-width: 160px;">
                <label for="date_from" style="display: block; margin-bottom: 3px; font-size: 11px; font-weight: 600; color: #646970;">
                    <?php _e('Date Range', 'log-manager'); ?>
                </label>
                <div style="display: flex; gap: 5px;">
                    <input type="date" name="date_from" id="date_from"
                        value="<?php echo !empty($filters['date_from']) ? esc_attr($filters['date_from']) : ''; ?>"
                        style="width: 70px; padding: 5px; font-size: 12px; height: 32px;">
                    <span style="color: #8c8f94; align-self: center;">→</span>
                    <input type="date" name="date_to" id="date_to"
                        value="<?php echo !empty($filters['date_to']) ? esc_attr($filters['date_to']) : ''; ?>"
                        style="width: 70px; padding: 5px; font-size: 12px; height: 32px;">
                </div>
            </div>
            
            <!-- Search -->
            <div style="flex: 1; min-width: 150px;">
                <label for="search" style="display: block; margin-bottom: 3px; font-size: 11px; font-weight: 600; color: #646970;">
                    <?php _e('Search', 'log-manager'); ?>
                </label>
                <input type="text" name="search" id="search"
                    value="<?php echo !empty($filters['search']) ? esc_attr($filters['search']) : ''; ?>"
                    placeholder="<?php esc_attr_e('Search...', 'log-manager'); ?>"
                    style="width: 100%; padding: 5px; font-size: 12px; height: 32px;">
            </div>
            
            <!-- Per Page -->
            <div style="min-width: 80px;">
                <label for="per_page" style="display: block; margin-bottom: 3px; font-size: 11px; font-weight: 600; color: #646970;">
                    <?php _e('Per Page', 'log-manager'); ?>
                </label>
                <select name="per_page" id="per_page" style="width: 100%; padding: 5px; font-size: 12px; height: 32px;">
                    <?php foreach ($per_page_options as $option): ?>
                        <option value="<?php echo esc_attr($option); ?>" <?php selected($per_page, $option); ?>>
                            <?php echo esc_html($option); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Filter Buttons -->
            <div style="display: flex; gap: 5px; align-items: flex-end;">
                <button type="submit" name="apply_filters" class="button button-primary" 
                    style="padding: 6px 10px; font-size: 12px; height: 32px; white-space: nowrap;">
                    <?php _e('Filter', 'log-manager'); ?>
                </button>
                <button type="submit" name="reset_filters" class="button" 
                    style="padding: 6px 10px; font-size: 12px; height: 32px; white-space: nowrap;" onclick="resetForm(event)">
                    <?php _e('Reset', 'log-manager'); ?>
                </button>
            </div>
            
            <!-- Export Buttons (smaller) -->
            <div style="display: flex; gap: 5px; align-items: flex-end;">
                <a href="<?php
                    echo wp_nonce_url(
                        add_query_arg(
                            array_merge(
                                ['export_type' => 'csv', 'page' => 'log-manager'],
                                $filters
                            ),
                            admin_url('admin.php')
                        ),
                        'log_manager_export'
                    );
                ?>" class="button" style="background: #00a32a; border-color: #00a32a; color: white; padding: 6px 8px; font-size: 11px; height: 32px;">
                    <span class="dashicons dashicons-media-spreadsheet" style="vertical-align: middle; margin-right: 3px; font-size: 13px;"></span>
                    <?php _e('CSV', 'log-manager'); ?>
                </a>
                
                <a href="<?php
                    echo wp_nonce_url(
                        add_query_arg(
                            array_merge(
                                ['export_type' => 'pdf', 'page' => 'log-manager'],
                                $filters
                            ),
                            admin_url('admin.php')
                        ),
                        'log_manager_export'
                    );
                ?>" class="button" style="background: #d63638; border-color: #d63638; color: white; padding: 6px 8px; font-size: 11px; height: 32px;">
                    <span class="dashicons dashicons-media-document" style="vertical-align: middle; margin-right: 3px; font-size: 13px;"></span>
                    <?php _e('PDF', 'log-manager'); ?>
                </a>
            </div>
        </div>
        
        <!-- Hidden current page field -->
        <input type="hidden" name="paged" value="<?php echo esc_attr($current_page); ?>">
    </form>
</div>

            <!-- Logs Table with bottom-left pagination -->
            <div style="position: relative;">
                <!-- Bulk Actions Form -->
                <form method="post" id="bulk-action-form" style="margin-bottom: 10px;">
                    <?php wp_nonce_field('bulk_delete_logs', 'bulk_delete_nonce'); ?>
                    <input type="hidden" name="action" value="bulk_delete">

                    <div class="tablenav top"
                        style="display: flex; justify-content: space-between; align-items: center; margin: 10px 0;">
                        <div class="alignleft actions bulkactions">
                            <select name="bulk_action" id="bulk-action-selector-top">
                                <option value=""><?php _e('Bulk Actions', 'log-manager'); ?></option>
                                <option value="delete"><?php _e('Delete', 'log-manager'); ?></option>
                            </select>
                            <input type="submit" id="doaction" class="button action"
                                value="<?php _e('Apply', 'log-manager'); ?>">
                        </div>
                    </div>

                    <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; overflow: hidden;">
                        <table class="wp-list-table widefat fixed striped" style="border: none;">
                            <thead>
                                <tr>
                                    <th width="30" class="check-column">
                                        <input type="checkbox" id="cb-select-all-1">
                                    </th>
                                    <th width="150"><?php _e('Time', 'log-manager'); ?></th>
                                    <th width="100"><?php _e('Severity', 'log-manager'); ?></th>
                                    <th width="120"><?php _e('User', 'log-manager'); ?></th>
                                    <th width="150"><?php _e('Action', 'log-manager'); ?></th>
                                    <th width="150"><?php _e('Object', 'log-manager'); ?></th>
                                    <th><?php _e('Details', 'log-manager'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 20px;">
                                            <?php _e('No activity logs found.', 'log-manager'); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $log): ?>
                                        <?php
                                        $user = $log->user_id ? get_user_by('id', $log->user_id) : null;
                                        $username = $user ? $user->display_name : __('System', 'log-manager');
                                        $time = date_i18n('M j, H:i:s', strtotime($log->timestamp));
                                        $time_full = date_i18n('Y-m-d H:i:s', strtotime($log->timestamp));
                                        $details = $log->details;
                                        ?>
                                        <tr>
                                            <th scope="row" class="check-column">
                                                <input type="checkbox" name="log_ids[]" value="<?php echo esc_attr($log->id); ?>">
                                            </th>
                                            <td>
                                                <span title="<?php echo esc_attr($time_full); ?>">
                                                    <?php echo esc_html($time); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="severity-badge severity-<?php echo esc_attr($log->severity); ?>"
                                                    style="display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; background: #f0f0f1; color: #50575e;">
                                                    <?php echo esc_html(ucfirst($log->severity)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($user): ?>
                                                    <a href="<?php echo get_edit_user_link($log->user_id); ?>"
                                                        title="<?php echo esc_attr($user->user_email); ?>">
                                                        <?php echo esc_html($username); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?php echo esc_html($username); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code><?php echo esc_html(self::sdw_format_action($log->action)); ?></code>
                                            </td>
                                            <td>
                                                <?php
                                                $object_text = '';
                                                if ($log->object_id > 0) {
                                                    if ($log->object_type === 'post') {
                                                        $object_text = ' Post #' . $log->object_id;
                                                        $edit_url = get_edit_post_link($log->object_id);
                                                        if ($edit_url) {
                                                            $object_text = '<a href="' . esc_url($edit_url) . '" target="_blank"> Post #' . $log->object_id . '</a>';
                                                        }
                                                    } elseif ($log->object_type === 'user') {
                                                        $object_text = ' User #' . $log->object_id;
                                                        $edit_url = get_edit_user_link($log->object_id);
                                                        if ($edit_url) {
                                                            $object_text = '<a href="' . esc_url($edit_url) . '" target="_blank"> User #' . $log->object_id . '</a>';
                                                        }
                                                    } elseif ($log->object_type === 'attachment') {
                                                        $object_text = ' Media #' . $log->object_id;
                                                    } elseif ($log->object_type === 'comment') {
                                                        $object_text = ' Comment #' . $log->object_id;
                                                    } elseif ($log->object_type === 'term') {
                                                        $object_text = ' Term #' . $log->object_id;
                                                    } elseif ($log->object_type === 'revision') {
                                                        $object_text = ' Revision #' . $log->object_id;
                                                    } else {
                                                        $object_text = ucfirst($log->object_type) . ' #' . $log->object_id;
                                                    }
                                                } else {
                                                    if ($log->object_type === 'plugin') {
                                                        $object_text = ' ' . $log->object_name;
                                                    } elseif ($log->object_type === 'theme') {
                                                        $object_text = ' ' . $log->object_name;
                                                    } elseif ($log->object_type === 'option') {
                                                        $object_text = ' ' . $log->object_name;
                                                    } elseif ($log->object_type === 'widget') {
                                                        $object_text = ' ' . $log->object_name;
                                                    } elseif ($log->object_type === 'acf') {
                                                        $object_text = ' ' . $log->object_name;
                                                    } else {
                                                        $object_text = $log->object_name ?: ucfirst($log->object_type ?: 'System');
                                                    }
                                                }
                                                echo $object_text;
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($details): ?>
                                                    <?php echo self::sdw_format_details_display($details); ?>
                                                <?php else: ?>
                                                    <em><?php _e('No details', 'log-manager'); ?></em>
                                                <?php endif; ?>
                                            </td>

                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <!-- Bottom Left Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav bottom"
                        style="margin: 20px 0; display: flex; justify-content: space-between; align-items: center;">
                        <div class="tablenav-pages" style="float: none;">
                            <span class="displaying-num"><?php
                            printf(
                                __('Displaying %1$s–%2$s of %3$s', 'log-manager'),
                                number_format(($current_page - 1) * $per_page + 1),
                                number_format(min($current_page * $per_page, $total_logs)),
                                number_format($total_logs)
                            );
                            ?></span>
                            <span class="pagination-links">
                                <?php
                                // Create pagination links with filters
                                $pagination_args = array_merge(['page' => 'log-manager'], $filters);

                                // First page link
                                if ($current_page > 1) {
                                    $first_args = array_merge($pagination_args, ['paged' => 1]);
                                    $first_url = add_query_arg($first_args, admin_url('admin.php'));
                                    echo '<a class="first-page button" href="' . esc_url($first_url) . '"><span class="screen-reader-text">' . __('First page', 'log-manager') . '</span><span aria-hidden="true">«</span></a>';
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">«</span>';
                                }

                                // Previous page link
                                if ($current_page > 1) {
                                    $prev_args = array_merge($pagination_args, ['paged' => $current_page - 1]);
                                    $prev_url = add_query_arg($prev_args, admin_url('admin.php'));
                                    echo '<a class="prev-page button" href="' . esc_url($prev_url) . '"><span class="screen-reader-text">' . __('Previous page', 'log-manager') . '</span><span aria-hidden="true">‹</span></a>';
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">‹</span>';
                                }
                                ?>

                                <span class="paging-input" style="margin: 0 10px;">
                                    <label for="current-page-selector"
                                        class="screen-reader-text"><?php _e('Current Page', 'log-manager'); ?></label>
                                    <?php _e('Page', 'log-manager'); ?>
                                    <span class="current-page"><?php echo esc_html($current_page); ?></span>
                                    <?php _e('of', 'log-manager'); ?> <span
                                        class="total-pages"><?php echo esc_html($total_pages); ?></span>
                                </span>

                                <?php
                                // Next page link
                                if ($current_page < $total_pages) {
                                    $next_args = array_merge($pagination_args, ['paged' => $current_page + 1]);
                                    $next_url = add_query_arg($next_args, admin_url('admin.php'));
                                    echo '<a class="next-page button" href="' . esc_url($next_url) . '"><span class="screen-reader-text">' . __('Next page', 'log-manager') . '</span><span aria-hidden="true">›</span></a>';
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">›</span>';
                                }

                                // Last page link
                                if ($current_page < $total_pages) {
                                    $last_args = array_merge($pagination_args, ['paged' => $total_pages]);
                                    $last_url = add_query_arg($last_args, admin_url('admin.php'));
                                    echo '<a class="last-page button" href="' . esc_url($last_url) . '"><span class="screen-reader-text">' . __('Last page', 'log-manager') . '</span><span aria-hidden="true">»</span></a>';
                                } else {
                                    echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">»</span>';
                                }
                                ?>
                            </span>
                        </div>

                        <!-- Quick page jump form (POST method) -->
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <form method="post" action="<?php echo admin_url('admin.php?page=log-manager'); ?>"
                                style="display: flex; align-items: center; gap: 5px;">
                                <?php wp_nonce_field('log_manager_filter', 'log_manager_filter_nonce'); ?>
                                <input type="hidden" name="log_manager_filter" value="1">
                                <?php foreach ($filters as $key => $value): ?>
                                    <?php if (!empty($value)): ?>
                                        <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <input type="hidden" name="per_page" value="<?php echo esc_attr($per_page); ?>">
                                <label for="jump-page" style="font-size: 13px;"><?php _e('Jump to:', 'log-manager'); ?></label>
                                <input type="number" id="jump-page" name="paged" min="1" max="<?php echo esc_attr($total_pages); ?>"
                                    value="<?php echo esc_attr($current_page); ?>" style="width: 60px;">
                                <button type="submit" class="button button-small"><?php _e('Go', 'log-manager'); ?></button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Show total count when no pagination needed -->
                    <div class="tablenav bottom" style="margin: 20px 0;">
                        <div class="displaying-num">
                            <?php
                            printf(
                                _n('%s item', '%s items', $total_logs, 'log-manager'),
                                number_format($total_logs)
                            );
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
            .severity-emergency {
                background: #dc3232 !important;
                color: white !important;
            }

            .severity-alert {
                background: #f56e28 !important;
                color: white !important;
            }

            .severity-critical {
                background: #d63638 !important;
                color: white !important;
            }

            .severity-error {
                background: #ff0000 !important;
                color: white !important;
            }

            .severity-warning {
                background: #ffb900 !important;
                color: #000 !important;
            }

            .severity-notice {
                background: #00a0d2 !important;
                color: white !important;
            }

            .severity-info {
                background: #2271b1 !important;
                color: white !important;
            }

            .severity-debug {
                background: #a7aaad !important;
                color: #000 !important;
            }

            /* Log details styling */
            .log-details {
                font-size: 12px;
                line-height: 1.4;
                max-height: 150px;
                overflow-y: auto;
                padding: 8px;
                background: #f8f9fa;
                border-radius: 4px;
                border-left: 3px solid #2271b1;
            }

            .detail-item {
                margin-bottom: 6px;
                padding-bottom: 6px;
                border-bottom: 1px dashed #e0e0e0;
            }

            .detail-item:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }

            .detail-key {
                font-weight: 600;
                color: #1d2327;
                display: inline-block;
                min-width: 120px;
                background: #fff;
                padding: 2px 6px;
                border-radius: 3px;
                border: 1px solid #dcdcde;
            }

            .change-old {
                color: #d63638;
                background: #fcf0f1;
                padding: 1px 4px;
                border-radius: 2px;
                text-decoration: line-through;
                margin-right: 4px;
            }

            .change-new {
                color: #00a32a;
                background: #f0f9f1;
                padding: 1px 4px;
                border-radius: 2px;
                font-weight: 600;
            }

            .change-arrow {
                color: #8c8f94;
                margin: 0 8px;
                font-weight: bold;
            }

            /* HTML content styling */
            .log-details .html-content {
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 4px;
                padding: 15px;
                margin-top: 5px;
                max-width: 100%;
                overflow-x: auto;
            }

            /* ACF specific styling */
            .log-details .html-content .acf-changes-summary,
            .log-details .html-content .location-rules {
                font-size: 13px;
                line-height: 1.5;
            }

            .log-details .html-content .change-item {
                margin: 8px 0;
                padding: 8px;
                background: #f8f9fa;
                border-radius: 4px;
                border-left: 3px solid #2271b1;
            }

            .log-details .html-content .change-label {
                font-weight: 600;
                color: #1d2327;
                display: block;
                margin-bottom: 5px;
            }

            .log-details .html-content .change-old,
            .log-details .html-content .change-new {
                padding: 2px 6px;
                border-radius: 3px;
                margin: 0 2px;
            }

            .log-details .html-content .change-old {
                background: #fcf0f1;
                color: #d63638;
                text-decoration: line-through;
            }

            .log-details .html-content .change-new {
                background: #f0f9f1;
                color: #00a32a;
                font-weight: 600;
            }

            .log-details .html-content .change-arrow {
                color: #8c8f94;
                margin: 0 8px;
                font-weight: bold;
            }

            .log-details .html-content .field-changes-section {
                margin-top: 15px;
                border-top: 2px solid #f0f0f1;
                padding-top: 15px;
            }

            .log-details .html-content .field-changes-header {
                font-weight: 600;
                color: #1d2327;
                margin-bottom: 10px;
                font-size: 14px;
            }

            .log-details .html-content .field-change-group {
                margin: 10px 0;
                padding: 10px;
                border-radius: 5px;
            }

            .log-details .html-content .modified-fields {
                background: #fff9e6;
                border: 1px solid #ffecb5;
            }

            .log-details .html-content .field-group-header {
                font-weight: 600;
                margin-bottom: 8px;
                padding-bottom: 5px;
                border-bottom: 1px dashed #ddd;
                color: #664d03;
            }

            .log-details .html-content .field-item {
                margin: 8px 0;
                padding: 8px 10px;
                background: #fff;
                border-radius: 4px;
                border-left: 4px solid #2271b1;
            }

            .log-details .html-content .modified-field {
                border-left-color: #dba617;
            }

            .log-details .html-content .field-meta {
                font-size: 11px;
                color: #646970;
                font-family: monospace;
                margin-left: 8px;
            }

            .log-details .html-content .field-modifications {
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px dashed #e0e0e0;
            }

            .log-details .html-content .field-modification {
                margin: 4px 0;
                padding: 4px 8px;
                background: #f8f9fa;
                border-radius: 3px;
                font-size: 12px;
            }

            .log-details .html-content .mod-prop {
                font-weight: 600;
                color: #50575e;
                display: inline-block;
                min-width: 120px;
            }

            .log-details .html-content .mod-old,
            .log-details .html-content .mod-new {
                padding: 1px 4px;
                border-radius: 2px;
                margin: 0 4px;
            }

            .log-details .html-content .mod-old {
                background: #ffe2e3;
                color: #d63638;
            }

            .log-details .html-content .mod-new {
                background: #cce8d1;
                color: #00a32a;
            }

            .log-details .html-content .location-rules {
                background: #f6f7f7;
                padding: 10px;
                border-radius: 4px;
                border: 1px solid #dcdcde;
            }

            .log-details .html-content .location-group {
                margin: 5px 0;
                padding: 8px;
                background: #fff;
                border-radius: 3px;
                border-left: 3px solid #2271b1;
            }

            .log-details .html-content .location-group code {
                background: #f0f0f1;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: monospace;
                color: #50575e;
            }

            /* Action links styling */
            .action-links {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 2px solid #e0e0e0;
            }

            .action-links a {
                display: inline-block;
                margin-right: 10px;
                padding: 3px 8px;
                background: #2271b1;
                color: white;
                text-decoration: none;
                border-radius: 3px;
                font-size: 11px;
            }

            .action-links a:hover {
                background: #135e96;
            }

            /* Make table more readable */
            .wp-list-table th {
                font-weight: 600;
                background: #f6f7f7;
            }

            .wp-list-table tr:hover {
                background: #f6f7f7 !important;
            }

            /* Pagination styling */
            .tablenav.bottom {
                background: #fff;
                padding: 15px;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                border-top: none;
                border-top-left-radius: 0;
                border-top-right-radius: 0;
            }

            .tablenav-pages a.button {
                display: inline-block;
                padding: 3px 8px;
                min-width: 30px;
                height: 28px;
                line-height: 20px;
                text-align: center;
                text-decoration: none;
            }

            .tablenav-pages-navspan.button.disabled {
                display: inline-block;
                padding: 3px 8px;
                min-width: 30px;
                height: 28px;
                line-height: 20px;
                text-align: center;
                background: #f6f7f7;
                color: #a7aaad;
                border-color: #dcdcde;
                cursor: default;
            }

            .paging-input input.current-page {
                padding: 3px 5px;
                border: 1px solid #8c8f94;
                border-radius: 3px;
            }

            /* Responsive table */
            @media screen and (max-width: 1200px) {
                .wp-list-table {
                    display: block;
                    overflow-x: auto;
                    white-space: nowrap;
                }
            }

            /* Mobile responsive */
            @media screen and (max-width: 782px) {
                .tablenav.bottom {
                    flex-direction: column;
                    gap: 15px;
                }

                .tablenav-pages {
                    width: 100%;
                    justify-content: center;
                }

                .tablenav-pages .pagination-links {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-wrap: wrap;
                    gap: 5px;
                }
            }
        </style>

        <style>
            /* Checkbox column styling */
            .check-column {
                width: 30px !important;
                padding: 8px 0 0 8px !important;
                vertical-align: top;
            }

            .check-column input[type="checkbox"] {
                margin: 0;
            }

            /* Bulk actions styling */
            .tablenav .bulkactions {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .tablenav .button-secondary {
                background: #d63638;
                border-color: #d63638;
                color: white;
            }

            .tablenav .button-secondary:hover {
                background: #b32d2e;
                border-color: #b32d2e;
                color: white;
            }

            /* Action buttons column */
            td .button-small {
                padding: 2px 8px;
                height: 24px;
                line-height: 20px;
            }

            /* Confirmation dialog styling */
            .confirm-dialog {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
        </style>

        <script>
            jQuery(document).ready(function ($) {
                $('button[name="reset_filters"]').on('click', function (e) {
                    e.preventDefault();
                    var form = $(this).closest('form');
                    form.find('select').val('');
                    form.find('input[type="text"], input[type="date"], input[type="number"]').val('');
                    form.find('input[name="user_id"]').val(0);
                    form.find('input[name="paged"]').val(1);

                    // Submit the reset
                    form.submit();
                });

                // Handle export button clicks - simple version
                $(document).on('click', 'a.button[href*="export_type"]', function (e) {
                    var $button = $(this);

                    // Store original state
                    var originalHtml = $button.html();

                    // Show loading state
                    $button.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> ' + $button.text());
                    $button.addClass('disabled').css('pointer-events', 'none');

                });
            });
        </script>

        <script>
            // Bulk actions functionality
            jQuery(document).ready(function ($) {
                // Select all checkboxes on current page
                $('#cb-select-all-1').on('click', function () {
                    $('input[name="log_ids[]"]').prop('checked', this.checked);
                });

                // Handle bulk action form submission
                $('#doaction').on('click', function (e) {
                    var action = $('#bulk-action-selector-top').val();
                    var checked = $('input[name="log_ids[]"]:checked').length;

                    if (action === 'delete' && checked > 0) {
                        // Calculate how many logs we're deleting
                        var totalLogs = <?php echo $total_logs; ?>;
                        var currentPageLogs = <?php echo count($logs); ?>;

                        // Check if we're deleting all logs on page
                        var allOnPageChecked = (checked === currentPageLogs);

                        var confirmMessage = '';
                        if (allOnPageChecked && currentPageLogs > 1) {
                            confirmMessage = '<?php _e('You are about to delete ALL logs on this page (' . count($logs) . ' logs). This action cannot be undone. Are you sure?', 'log-manager'); ?>';
                        } else if (checked === 1) {
                            confirmMessage = '<?php _e('Are you sure you want to delete this log?', 'log-manager'); ?>';
                        } else {
                            confirmMessage = '<?php _e('You are about to delete ' . checked . ' logs. This action cannot be undone. Are you sure?', 'log-manager'); ?>'.replace('checked', checked);
                        }

                        if (!confirm(confirmMessage)) {
                            e.preventDefault();
                            return false;
                        }
                    }

                    if (action === '' && checked > 0) {
                        alert('<?php _e('Please select a bulk action.', 'log-manager'); ?>');
                        e.preventDefault();
                        return false;
                    }

                    if (checked === 0) {
                        alert('<?php _e('Please select at least one log to perform bulk action.', 'log-manager'); ?>');
                        e.preventDefault();
                        return false;
                    }
                });

                // Add "Select All" hint text
                $(document).ready(function () {
                    var totalLogs = <?php echo $total_logs; ?>;
                    var perPage = <?php echo $per_page; ?>;

                    if (totalLogs > perPage) {
                        $('.bulkactions').append('<span class="select-all-hint" style="margin-left: 10px; font-size: 12px; color: #646970;">(' +
                            '<?php _e("To delete all logs, use filters to narrow down or export first", "log-manager"); ?>' +
                            ')</span>');
                    }
                });
            });
        </script>

        <?php
    }

    /**
     * Format action for display
     * 
     * @param string $action Action string
     * @return string Formatted action label
     */
    public static function sdw_format_action($action)
    {
        $actions = [
            'post_created' => ' Post Created',
            'post_updated' => ' Post Updated',
            'post_deleted' => ' Post Deleted',
            'post_trashed' => ' Post Trashed',
            'post_untrashed' => ' Post Restored',
            'featured_image_added' => ' Featured Image Added',
            'featured_image_changed' => ' Featured Image Changed',
            'featured_image_removed' => ' Featured Image Removed',
            'revision_created' => ' Revision Created',
            'user_registered' => ' User Registered',
            'user_updated' => ' User Updated',
            'user_login' => ' User Login',
            'user_logout' => ' User Logout',
            'password_reset' => ' Password Reset',
            'password_changed' => ' Password Changed',
            'password_reset_requested' => ' Password Reset Requested',
            'user_role_changed' => ' User Role Changed',
            'user_meta_updated' => ' User Meta Updated',
            'user_meta_added' => ' User Meta Added',
            'user_meta_deleted' => ' User Meta Deleted',
            'option_updated' => ' Setting Updated',
            'plugin_activated' => ' Plugin Activated',
            'plugin_deactivated' => ' Plugin Deactivated',
            'plugin_deleted' => ' Plugin Deleted',
            'theme_switched' => ' Theme Switched',
            'comment_posted' => ' Comment Posted',
            'comment_edited' => ' Comment Edited',
            'comment_deleted' => ' Comment Deleted',
            'media_added' => ' Media Added',
            'media_edited' => ' Media Edited',
            'media_deleted' => ' Media Deleted',
            'term_created' => ' Term Created',
            'term_updated' => ' Term Updated',
            'term_deleted' => ' Term Deleted',
            'taxonomy_updated' => ' Taxonomy Updated',
            'widget_updated' => ' Widget Updated',
            'widgets_rearranged' => ' Widgets Rearranged',
            'import_started' => ' Import Started',
            'import_completed' => ' Import Completed',
            'export_started' => ' Export Started',
            'acf_fields_updated' => ' ACF Fields Updated',
            'acf_field_group_updated' => ' ACF Field Group Updated',
            'acf_field_group_duplicated' => ' ACF Field Group Duplicated',
            'acf_field_group_deleted' => ' ACF Field Group Deleted',
            'term_meta_updated' => ' Term Meta Updated',
            'term_meta_added' => ' Term Meta Added',
            'term_meta_deleted' => ' Term Meta Deleted',
            'post_meta_updated' => ' Post Meta Updated',
            'post_meta_added' => ' Post Meta Added',
            'post_meta_deleted' => ' Post Meta Deleted',
            'menu_updated' => ' Menu Updated',
            'menu_created' => ' Menu Created',
            'menu_deleted' => ' Menu Deleted',
            'sidebar_widgets_updated' => ' Sidebar Widgets Updated',
            'customizer_saved' => ' Customizer Saved',
            'login_failed' => ' Login Failed',
        ];

        return $actions[$action] ?? ucwords(str_replace('_', ' ', $action));
    }

    /**
     * Get object display text
     * 
     * @param object $log Log object
     * @return string Formatted object text
     */
    public static function sdw_get_object_display_text($log)
    {
        if ($log->object_id > 0) {
            if ($log->object_type === 'post') {
                return 'Post #' . $log->object_id . ' - ' . $log->object_name;
            } elseif ($log->object_type === 'user') {
                return 'User #' . $log->object_id . ' - ' . $log->object_name;
            } elseif ($log->object_type === 'attachment') {
                return 'Media #' . $log->object_id . ' - ' . $log->object_name;
            } else {
                return ucfirst($log->object_type) . ' #' . $log->object_id . ' - ' . $log->object_name;
            }
        } else {
            return $log->object_name ?: ucfirst($log->object_type ?: 'System');
        }
    }

    /**
     * Format details for display with HTML support
     * 
     * @param array $details Details array
     * @return string HTML formatted details
     */
    private static function sdw_format_details_display($details)
    {
        if (empty($details) || !is_array($details)) {
            return '<em>' . __('No details', 'log-manager') . '</em>';
        }

        $output = '<div class="log-details">';

        // 1. Collect action links to show at the bottom
        $action_links = [];
        $link_keys = ['edit_post', 'view_post', 'view_revisions', 'edit_term', 'edit_acf_group', 'visit_user', 'settings_page', 'view_media', 'plugin_details'];
        foreach ($link_keys as $key) {
            if (!empty($details[$key])) {
                $action_links[] = $details[$key];
                unset($details[$key]); // remove so we don't show twice
            }
        }

        // 2. Loop through remaining details
        foreach ($details as $key => $value) {
            // Special handling for changes_display (ACF HTML)
            if ($key === 'changes_display' || $key === 'location') {
                $output .= '<div class="detail-item">';
                $output .= '<span class="detail-key">' . esc_html(ucfirst(str_replace('_', ' ', $key))) . ':</span> ';
                $output .= '<div class="detail-value html-content">' . $value . '</div>';
                $output .= '</div>';
                continue;
            }

            // Special beautiful rendering for content changes
            if ($key === 'content' && is_array($value)) {
                $output .= self::sdw_render_beautiful_content_changes($value);
                continue;
            }

            // Normal key-value
            $output .= '<div class="detail-item">';
            $output .= '<span class="detail-key">' . esc_html(ucfirst(str_replace('_', ' ', $key))) . ':</span> ';

            if (is_array($value)) {
                if (isset($value['old']) && isset($value['new'])) {
                    $output .= '<span class="change-old">"' . esc_html($value['old']) . '"</span>';
                    $output .= '<span class="change-arrow"> → </span>';
                    $output .= '<span class="change-new">"' . esc_html($value['new']) . '"</span>';
                } else {
                    $output .= '<pre style="font-size: 12px; max-height: 200px; overflow: auto; background: #f6f7f7; padding: 10px; border-radius: 4px;">' . esc_html(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                }
            } else {
                // Check if value contains HTML tags
                if (is_string($value) && preg_match('/<[^>]+>/', $value)) {
                    $output .= '<div class="detail-value html-content">' . $value . '</div>';
                } else {
                    $output .= '<span class="detail-value">' . esc_html($value) . '</span>';
                }
            }

            $output .= '</div>';
        }

        // 3. Action links at the bottom
        if (!empty($action_links)) {
            $output .= '<div class="action-links">';
            $output .= '<span class="detail-key">Quick Actions:</span> ';
            $output .= implode(' ', $action_links);
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Render settings page
     * 
     * @return void
     */
    public static function sdw_render_settings_page()
    {
        ?>
        <div class="wrap">
            <h1><?php _e('Log Manager Settings', 'log-manager'); ?></h1>
        </div>
        <?php
    }

    /**
     * Beautiful rendering of content change details
     * 
     * @param array $data Content change data
     * @return string HTML formatted content changes
     */
    private static function sdw_render_beautiful_content_changes($data)
    {
        $output = '<div class="content-change-box">';

        // Summary line
        $output .= '<div class="content-header">';
        $output .= '<strong>Content Updated</strong>';

        if (!empty($data['characters_changed'])) {
            $ch = $data['characters_changed'];
            $is_plus = strpos($ch, '+') === 0;
            $num = abs((int) $ch);

            $output .= ' <span class="' . ($is_plus ? 'added-chars' : 'removed-chars') . '">';
            $output .= $is_plus ? '↑ +' : '↓ -';
            $output .= $num . ' char' . ($num !== 1 ? 's' : '');
            $output .= '</span>';
        }

        if (!empty($data['old_length']) && !empty($data['new_length'])) {
            $output .= ' <small>(' . esc_html($data['old_length']) . ' → ' . esc_html($data['new_length']) . ')</small>';
        }
        $output .= '</div>';

        // Detailed changes
        if (!empty($data['detailed_changes'])) {
            $dc = $data['detailed_changes'];

            // Added content
            if (!empty($dc['added']['sample'])) {
                $sample = esc_html($dc['added']['sample']);
                $count = $dc['added']['count'] ?? 0;

                $output .= '<div class="diff-section added">';
                $output .= '<div class="diff-title">＋ Added (' . $count . ' lines)</div>';
                $output .= '<pre class="diff-pre">' . nl2br($sample) . '</pre>';
                $output .= '</div>';
            }

            // Modified lines
            if (!empty($dc['modified'])) {
                $output .= '<div class="diff-section modified">';
                $output .= '<div class="diff-title"> Modified lines</div>';

                foreach (array_slice($dc['modified'], 0, 4) as $mod) {  // limit to 4 for space
                    $output .= '<div class="mod-line">';
                    $output .= '<div class="line-info">Line ' . ($mod['line'] ?? '?') . ':</div>';
                    $output .= '<div><span class="old-text">' . esc_html($mod['old'] ?? '') . '</span></div>';
                    $output .= '<div><span class="new-text">' . esc_html($mod['new'] ?? '') . '</span></div>';
                    $output .= '</div>';
                }

                if (count($dc['modified']) > 4) {
                    $output .= '<div class="more-info">... and ' . (count($dc['modified']) - 4) . ' more modified lines</div>';
                }
                $output .= '</div>';
            }
        }

        // Word changes summary (small)
        if (!empty($data['word_changes']['added_words']['count'])) {
            $aw = $data['word_changes']['added_words'];
            $output .= '<div class="word-summary">';
            $output .= ' ' . $aw['count'] . ' new words (e.g. "' . esc_html(substr($aw['sample'] ?? '', 0, 80)) . '…")';
            $output .= '</div>';
        }

        $output .= '</div>';

        return $output;
    }

    /**
     * Handle POST filter requests early
     * 
     * @return void
     */
    public static function sdw_handle_post_requests()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['log_manager_filter'])) {
            return;
        }

        // Verify nonce
        if (
            !isset($_POST['log_manager_filter_nonce']) ||
            !wp_verify_nonce($_POST['log_manager_filter_nonce'], 'log_manager_filter')
        ) {
            wp_die('Security check failed');
        }

        // Check if reset button was clicked
        if (isset($_POST['reset_filters'])) {
            // Clear all stored filters
            delete_transient('log_manager_current_filters_' . get_current_user_id());
            delete_transient('log_manager_current_per_page_' . get_current_user_id());

            // Redirect to clean URL WITHOUT filtered=1
            wp_safe_redirect(admin_url('admin.php?page=log-manager&reset=1'));
            exit;
        }

        // Collect filters from POST
        $filters = [];
        $filter_keys = ['severity', 'user_id', 'action', 'object_type', 'date_from', 'date_to', 'search'];

        foreach ($filter_keys as $key) {
            if (!empty($_POST[$key])) {
                $filters[$key] = sanitize_text_field($_POST[$key]);
            }
        }

        $per_page = get_option('posts_per_page', 10);
        if (!empty($_POST['per_page'])) {
            $per_page = intval($_POST['per_page']);
        }

        // Store filters in session/transient for pagination
        set_transient('log_manager_current_filters_' . get_current_user_id(), $filters, HOUR_IN_SECONDS);
        set_transient('log_manager_current_per_page_' . get_current_user_id(), $per_page, HOUR_IN_SECONDS);

        // Preserve current page if set
        $paged = 1;
        if (!empty($_POST['paged'])) {
            $paged = intval($_POST['paged']);
        }

        // Redirect with filtered=1 ONLY for apply_filters, NOT for reset
        $redirect_args = ['page' => 'log-manager', 'filtered' => '1'];
        if ($paged > 1) {
            $redirect_args['paged'] = $paged;
        }

        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
        exit;
    }

    /**
     * Delete single log by ID
     * 
     * @param int $log_id Log ID to delete
     * @return bool|int Result of deletion
     */
    public static function sdw_delete_log($log_id)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        return $wpdb->delete($table_name, ['id' => $log_id], ['%d']);
    }

    /**
     * Bulk delete logs by IDs
     * 
     * @param array $log_ids Array of log IDs to delete
     * @return bool|int Result of deletion
     */
    public static function sdw_bulk_delete_logs($log_ids)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        if (empty($log_ids)) {
            return false;
        }

        // Sanitize IDs
        $ids = array_map('intval', $log_ids);
        $ids = array_filter($ids);

        if (empty($ids)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $query = $wpdb->prepare(
            "DELETE FROM $table_name WHERE id IN ($placeholders)",
            $ids
        );

        return $wpdb->query($query);
    }

    /**
     * Handle delete requests
     * 
     * @return void
     */
    public static function sdw_handle_delete_requests()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if it's a delete request
        if (!isset($_GET['page']) || $_GET['page'] !== 'log-manager') {
            return;
        }

        // Bulk delete (selected logs)
        if (
            isset($_POST['action']) && $_POST['action'] === 'bulk_delete' &&
            isset($_POST['bulk_delete_nonce']) &&
            wp_verify_nonce($_POST['bulk_delete_nonce'], 'bulk_delete_logs')
        ) {

            if (!empty($_POST['log_ids'])) {
                $log_ids = array_map('intval', $_POST['log_ids']);
                self::sdw_bulk_delete_logs($log_ids);
            }

            wp_redirect(remove_query_arg(['_wp_http_referer'], wp_unslash($_SERVER['REQUEST_URI'])));
            exit;
        }

        // Bulk delete ALL filtered logs
        if (
            isset($_POST['action']) && $_POST['action'] === 'bulk_delete_all' &&
            isset($_POST['bulk_delete_nonce']) &&
            wp_verify_nonce($_POST['bulk_delete_nonce'], 'bulk_delete_logs') &&
            isset($_POST['delete_all']) && $_POST['delete_all'] == '1'
        ) {

            // Get all log IDs matching current filters
            $filters = [];
            $filter_keys = ['severity', 'user_id', 'action', 'object_type', 'date_from', 'date_to', 'search'];

            foreach ($filter_keys as $key) {
                if (!empty($_POST[$key])) {
                    $filters[$key] = sanitize_text_field($_POST[$key]);
                }
            }

            // Get all log IDs matching filters
            $all_logs_data = self::sdw_get_logs(1, 1000000, $filters); // Get all logs
            $log_ids = array_map(function ($log) {
                return $log->id;
            }, $all_logs_data['logs']);

            if (!empty($log_ids)) {
                self::sdw_bulk_delete_logs($log_ids);
            }

            wp_redirect(remove_query_arg(['_wp_http_referer'], wp_unslash($_SERVER['REQUEST_URI'])));
            exit;
        }
    }
}