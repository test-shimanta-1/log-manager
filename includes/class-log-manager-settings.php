<?php
/**
 * Plugin Settings Class File
 * 
 * Handles: Storage settings, CRON scheduling configuration
 * 
 * @since 1.0.2
 * @package Log_Manager
 */
class Log_Manager_Settings
{
    /**
     * Constructor.
     *
     * @since 1.0.2
     *
     * @return void
     */
    public function __construct()
    {
        add_action('admin_init', [$this, 'sdw_register_settings_callback']);
        add_action('admin_init', [$this, 'sdw_handle_cron_actions']);
        add_action('log_manager_cleanup_hook', [$this, 'sdw_log_manager_cleanup_logs']);
        add_filter('cron_schedules', [$this, 'sdw_register_custom_cron_schedules']);
    }

    /**
     * Register Log Manager plugin settings
     *
     * @since 1.0.2
     *
     * @return void
     */
    public function sdw_register_settings_callback()
    {
        // Storage Settings
        register_setting(
            'log_manager_settings',
            'log_manager_storage_type',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'database',
            ]
        );

        register_setting(
            'log_manager_settings',
            'log_manager_file_path',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        // CRON Settings
        register_setting(
            'log_manager_settings',
            'log_manager_cron_enabled',
            [
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            ]
        );

        register_setting(
            'log_manager_settings',
            'log_manager_cron_schedule_type',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'daily',
            ]
        );

        register_setting(
            'log_manager_settings',
            'log_manager_cron_time',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '02:00',
            ]
        );

        register_setting(
            'log_manager_settings',
            'log_manager_cron_specific_date',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ]
        );

        register_setting(
            'log_manager_settings',
            'log_manager_cron_interval',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'daily',
            ]
        );

        register_setting(
            'log_manager_settings',
            'log_manager_delete_count',
            [
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 100,
            ]
        );

        register_setting(
            'log_manager_settings',
            'log_manager_custom_days',
            [
                'type' => 'integer',
                'sanitize_callback' => 'absint',
                'default' => 7,
            ]
        );

        register_setting(
            'log_manager_settings',
            'log_manager_weekly_day',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'monday',
            ]
        );

        register_setting(
            'log_manager_settings',
            'log_manager_monthly_option',
            [
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'first',
            ]
        );
    }

    /**
     * Handle CRON enable/disable and schedule actions
     *
     * @since 1.0.2
     *
     * @return void
     */
    public function sdw_handle_cron_actions()
    {
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'log_manager_settings-options')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if settings were just saved
        if (!isset($_POST['log_manager_cron_enabled'])) {
            return;
        }

        $enabled = rest_sanitize_boolean($_POST['log_manager_cron_enabled']);
        $schedule_type = sanitize_text_field($_POST['log_manager_cron_schedule_type']);
        $cron_time = sanitize_text_field($_POST['log_manager_cron_time']);
        $specific_date = sanitize_text_field($_POST['log_manager_cron_specific_date']);
        $interval = sanitize_text_field($_POST['log_manager_cron_interval']);

        $hook_name = 'log_manager_cleanup_hook';

        // Clear existing schedules
        // wp_clear_scheduled_hook($hook_name);
        if (wp_next_scheduled($hook_name)) {
            wp_clear_scheduled_hook($hook_name);
        }


        // Schedule if enabled
        if ($enabled) {
            if ($schedule_type === 'daily') {
                $this->schedule_daily_cleanup($hook_name, $cron_time);
            } elseif ($schedule_type === 'specific_date') {
                $this->schedule_specific_date_cleanup($hook_name, $specific_date, $cron_time);
            } elseif ($schedule_type === 'custom_interval') {
                $this->schedule_interval_cleanup($hook_name, $interval, $cron_time);
            }
        }
    }

    /**
     * Export logs to text file before deletion
     *
     * @param array $logs Array of log entries to export
     * @return string|false File path on success, false on failure
     */
    public static function export_logs_before_deletion($logs)
    {
        if (empty($logs) || !is_array($logs)) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $export_dir = trailingslashit($upload_dir['basedir']) . 'log-manager-exports';

        if (!is_dir($export_dir)) {
            wp_mkdir_p($export_dir);
        }

        if (!is_writable($export_dir)) {
            return false;
        }

        $timezone = wp_timezone();
        $datetime = new DateTime('now', $timezone);

        $file = trailingslashit($export_dir)
            . 'deleted-logs-'
            . $datetime->format('Y-m-d_H-i-s')
            . '.txt';

        $handle = fopen($file, 'a');
        if (!$handle) {
            return false;
        }

        // Header
        fwrite($handle, str_repeat('-', 120) . PHP_EOL);
        fwrite(
            $handle,
            sprintf(
                "%-20s | %-7s | %-10s | %-12s | %-12s | %-15s | %s\n",
                'Event Time',
                'User ID',
                'Severity',
                'Event Type',
                'Object Type',
                'IP Address',
                'Message'
            )
        );
        fwrite($handle, str_repeat('-', 120) . PHP_EOL);

        // Rows
        foreach ($logs as $log) {

            if (!is_object($log)) {
                continue;
            }

            fwrite(
                $handle,
                sprintf(
                    "%-20s | %-7s | %-10s | %-12s | %-12s | %-15s | %s\n",
                    $log->event_time ?? '',
                    $log->userid ?? '0',
                    $log->severity ?? '',
                    $log->event_type ?? '',
                    $log->object_type ?? '',
                    $log->ip_address ?? '',
                    str_replace(["\r", "\n"], ' ', $log->message ?? '')
                )
            );
        }

        fwrite($handle, str_repeat('-', 120) . PHP_EOL);
        fwrite($handle, "End of Export\n");

        fclose($handle);

        return $file;
    }


    /**
     * Schedule daily cleanup
     *
     * @param string $hook_name
     * @param string $time (HH:MM format)
     * @return void
     */
    private function schedule_daily_cleanup($hook_name, $time)
    {
        list($hour, $minute) = explode(':', $time);

        // Get WordPress timezone
        $timezone = wp_timezone();
        $current_datetime = new DateTime('now', $timezone);

        // Create target datetime in WordPress timezone
        $target_datetime = new DateTime("today {$hour}:{$minute}:00", $timezone);

        // If time has passed today, schedule for tomorrow
        if ($target_datetime <= $current_datetime) {
            $target_datetime = new DateTime("tomorrow {$hour}:{$minute}:00", $timezone);
        }

        // Convert to UTC timestamp for WordPress
        $next_run = $target_datetime->getTimestamp();

        wp_schedule_event($next_run, 'daily', $hook_name);
    }

    /**
     * Schedule specific date cleanup
     *
     * @param string $hook_name
     * @param string $date (YYYY-MM-DD format)
     * @param string $time (HH:MM format)
     * @return void
     */
    private function schedule_specific_date_cleanup($hook_name, $date, $time)
    {
        if (empty($date)) {
            return;
        }

        // Get WordPress timezone
        $timezone = wp_timezone();
        $current_datetime = new DateTime('now', $timezone);

        // Create target datetime in WordPress timezone
        $datetime_string = $date . ' ' . $time . ':00';
        $target_datetime = new DateTime($datetime_string, $timezone);

        // Only schedule if in the future
        if ($target_datetime > $current_datetime) {
            $timestamp = $target_datetime->getTimestamp();
            wp_schedule_single_event($timestamp, $hook_name);
        }
    }

    /**
     * Schedule interval-based cleanup
     *
     * @param string $hook_name
     * @param string $interval
     * @param string $time (HH:MM format)
     * @return void
     */
    // private function schedule_interval_cleanup($hook_name, $interval, $time)
    // {
    //     list($hour, $minute) = explode(':', $time);

    //     // Get WordPress timezone
    //     $timezone = wp_timezone();
    //     $current_datetime = new DateTime('now', $timezone);

    //     // Handle custom_range differently
    //     if ($interval === 'custom_range') {
    //         $custom_days = get_option('log_manager_custom_days', 7);
    //         $interval_seconds = $custom_days * DAY_IN_SECONDS;

    //         $start_datetime = new DateTime("today {$hour}:{$minute}:00", $timezone);
    //         if ($start_datetime <= $current_datetime) {
    //             $start_datetime = new DateTime("tomorrow {$hour}:{$minute}:00", $timezone);
    //         }

    //         $start_timestamp = $start_datetime->getTimestamp();

    //         // Create custom schedule
    //         // add_filter('cron_schedules', function ($schedules) use ($interval_seconds, $custom_days) {
    //         //     $schedules['log_manager_custom'] = [
    //         //         'interval' => $interval_seconds,
    //         //         'display' => sprintf(__('Every %d days'), $custom_days)
    //         //     ];
    //         //     return $schedules;
    //         // });

    //         wp_schedule_event($start_timestamp, 'log_manager_custom', $hook_name);
    //         return;
    //     }

    //     // Handle weekly schedule
    //     if ($interval === 'weekly') {
    //         $weekly_day = get_option('log_manager_weekly_day', 'monday');
    //         $next_day = new DateTime("next {$weekly_day} {$hour}:{$minute}:00", $timezone);

    //         // If the calculated time is not in the future, move to next week
    //         if ($next_day <= $current_datetime) {
    //             $next_day->modify('+1 week');
    //         }

    //         $start_timestamp = $next_day->getTimestamp();
    //         wp_schedule_event($start_timestamp, 'weekly', $hook_name);
    //         return;
    //     }

    //     // Handle monthly schedule
    //     if ($interval === 'monthly') {
    //         $monthly_option = get_option('log_manager_monthly_option', 'first');

    //         if ($monthly_option === 'first') {
    //             $start_datetime = new DateTime("first day of next month {$hour}:{$minute}:00", $timezone);
    //         } else {
    //             $start_datetime = new DateTime("last day of this month {$hour}:{$minute}:00", $timezone);
    //             if ($start_datetime <= $current_datetime) {
    //                 $start_datetime = new DateTime("last day of next month {$hour}:{$minute}:00", $timezone);
    //             }
    //         }

    //         $start_timestamp = $start_datetime->getTimestamp();

    //         // Create monthly schedule
    //         add_filter('cron_schedules', function ($schedules) {
    //             $schedules['monthly'] = [
    //                 'interval' => 30 * DAY_IN_SECONDS,
    //                 'display' => __('Once Monthly')
    //             ];
    //             return $schedules;
    //         });

    //         wp_schedule_event($start_timestamp, 'monthly', $hook_name);
    //         return;
    //     }

    //     // Default intervals (hourly, twicedaily, daily)
    //     $start_datetime = new DateTime("today {$hour}:{$minute}:00", $timezone);
    //     if ($start_datetime <= $current_datetime) {
    //         $start_datetime = new DateTime("tomorrow {$hour}:{$minute}:00", $timezone);
    //     }

    //     $start_timestamp = $start_datetime->getTimestamp();
    //     wp_schedule_event($start_timestamp, $interval, $hook_name);
    // }

    private function schedule_interval_cleanup($hook_name, $interval, $time)
    {
        list($hour, $minute) = explode(':', $time);

        $timezone = wp_timezone();
        $now = new DateTime('now', $timezone);

        // Base start time
        $start = new DateTime("today {$hour}:{$minute}:00", $timezone);
        if ($start <= $now) {
            $start->modify('+1 day');
        }

        $timestamp = $start->getTimestamp();

        /**
         * CUSTOM RANGE (every N days)
         */
        if ($interval === 'custom_range') {

            if (!wp_next_scheduled($hook_name)) {
                wp_schedule_event($timestamp, 'log_manager_custom', $hook_name);
            }
            return;
        }

        /**
         * WEEKLY
         */
        if ($interval === 'weekly') {

            $weekly_day = get_option('log_manager_weekly_day', 'monday');
            $weekly = new DateTime("next {$weekly_day} {$hour}:{$minute}:00", $timezone);

            if ($weekly <= $now) {
                $weekly->modify('+1 week');
            }

            if (!wp_next_scheduled($hook_name)) {
                wp_schedule_event($weekly->getTimestamp(), 'weekly', $hook_name);
            }
            return;
        }

        /**
         * MONTHLY (CORRECT WAY → single event)
         */
        if ($interval === 'monthly') {

            $monthly_option = get_option('log_manager_monthly_option', 'first');

            if ($monthly_option === 'first') {
                $monthly = new DateTime("first day of next month {$hour}:{$minute}:00", $timezone);
            } else {
                $monthly = new DateTime("last day of this month {$hour}:{$minute}:00", $timezone);
                if ($monthly <= $now) {
                    $monthly = new DateTime("last day of next month {$hour}:{$minute}:00", $timezone);
                }
            }

            if (!wp_next_scheduled($hook_name)) {
                wp_schedule_single_event($monthly->getTimestamp(), $hook_name);
            }
            return;
        }

        /**
         * DEFAULT (hourly, twicedaily, daily)
         */
        if (!wp_next_scheduled($hook_name)) {
            wp_schedule_event($timestamp, $interval, $hook_name);
        }
    }


    /**
     * Get next scheduled time for display
     *
     * @return string|bool
     */
    public static function get_next_scheduled_time()
    {
        $timestamp = wp_next_scheduled('log_manager_cleanup_hook');

        if ($timestamp) {
            // Convert to WordPress timezone for display
            $timezone = wp_timezone();
            $datetime = new DateTime('@' . $timestamp);
            $datetime->setTimezone($timezone);
            return $datetime->format('Y-m-d H:i:s') . ' (' . $timezone->getName() . ')';
        }
        return false;
    }


    public static function sdw_log_manager_cleanup_logs()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'log_db';
        $delete_count = (int) get_option('log_manager_delete_count', 100);

        if ($delete_count <= 0) {
            return;
        }

        // Fetch logs to be deleted (oldest first)
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY id ASC LIMIT %d",
                $delete_count
            )
        );

        if (empty($logs)) {
            return;
        }

        $backup_file = self::export_logs_before_deletion($logs);
        if ($backup_file === false) {
            return;
        }

        $ids = wp_list_pluck($logs, 'id');
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE id IN ($placeholders)",
                $ids
            )
        );

        // Reschedule monthly cleanup if selected
        $cron_enabled  = get_option('log_manager_cron_enabled', false);
        $schedule_type = get_option('log_manager_cron_schedule_type', '');
        $interval      = get_option('log_manager_cron_interval', '');

        if ($cron_enabled && $schedule_type === 'custom_interval' && $interval === 'monthly') {
            $settings = new self();
            $settings->schedule_interval_cleanup(
                'log_manager_cleanup_hook',
                'monthly',
                get_option('log_manager_cron_time', '02:00')
            );
        }

    }

    // public function sdw_register_custom_cron_schedules($schedules)
    // {
    //     $custom_days = (int) get_option('log_manager_custom_days', 7);
    //     $schedules['log_manager_custom'] = [
    //         'interval' => $custom_days * DAY_IN_SECONDS,
    //         'display'  => sprintf('Every %d days', $custom_days),
    //     ];
    //     $schedules['monthly'] = [
    //         'interval' => 30 * DAY_IN_SECONDS,
    //         'display'  => 'Once Monthly',
    //     ];
    //     return $schedules;
    // }
    public function sdw_register_custom_cron_schedules($schedules)
    {
        $custom_days = (int) get_option('log_manager_custom_days', 7);

        $schedules['log_manager_custom'] = [
            'interval' => $custom_days * DAY_IN_SECONDS,
            'display'  => sprintf(__('Every %d days', 'log-manager'), $custom_days),
        ];

        return $schedules;
    }



    /**
     * Render Log Manager settings page.
     *
     * @since 1.0.2
     *
     * @return void
     */
    public static function sdw_log_manager_settings_render()
    {
        $storage = get_option('log_manager_storage_type', 'database');
        $file_path = get_option('log_manager_file_path', '');

        $cron_enabled = get_option('log_manager_cron_enabled', false);
        $schedule_type = get_option('log_manager_cron_schedule_type', 'daily');
        $cron_time = get_option('log_manager_cron_time', '02:00');
        $specific_date = get_option('log_manager_cron_specific_date', '');
        $interval = get_option('log_manager_cron_interval', 'daily');
        $delete_count = get_option('log_manager_delete_count', 100);
        $custom_days = get_option('log_manager_custom_days', 7);
        $weekly_day = get_option('log_manager_weekly_day', 'monday');
        $monthly_option = get_option('log_manager_monthly_option', 'first');
        $next_scheduled = self::get_next_scheduled_time();
        ?>
        <div class="wrap">
            <h1>Log Manager Settings</h1>

            <form method="post" action="options.php">
                <?php settings_fields('log_manager_settings'); ?>

                <!-- ========== STORAGE SETTINGS ========== -->
                <h2>Storage Settings</h2>
                <table class="form-table">
                    <tr>
                        <th>Store Logs In</th>
                        <td>
                            <label>
                                <input type="radio" name="log_manager_storage_type" value="database" <?php checked($storage, 'database'); ?>>
                                Database
                            </label><br>

                            <label>
                                <input type="radio" name="log_manager_storage_type" value="file" <?php checked($storage, 'file'); ?>>
                                Text File
                            </label>
                        </td>
                    </tr>

                    <tr id="log-file-path-row">
                        <th>Text File Path</th>
                        <td>
                            <input type="text" name="log_manager_file_path" value="<?php echo esc_attr($file_path); ?>"
                                class="regular-text" placeholder="Enter your folder path...">
                            <p class="description">Full path to the directory where log files will be stored.</p>
                        </td>
                    </tr>
                </table>

                <!-- ========== CRON SETTINGS ========== -->
                <h2>Automated Cleanup Settings</h2>
                <table class="form-table">
                    <tr>
                        <th>Enable Scheduled Cleanup</th>
                        <td>
                            <label>
                                <input type="checkbox" name="log_manager_cron_enabled" value="1" <?php checked($cron_enabled, true); ?>>
                                Enable automatic log cleanup
                            </label>
                            <?php if ($next_scheduled): ?>
                                <p class="description" style="color: #2271b1;">
                                    <strong>Next scheduled cleanup:</strong> <?php echo esc_html($next_scheduled); ?>
                                </p>
                            <?php else: ?>
                                <p class="description">No cleanup scheduled.</p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <tr id="cron-settings-row">
                        <th>Schedule Type</th>
                        <td>
                            <label>
                                <input type="radio" name="log_manager_cron_schedule_type" value="daily" <?php checked($schedule_type, 'daily'); ?>>
                                <strong>Daily</strong> - Run every day at a specific time
                            </label><br>

                            <label>
                                <input type="radio" name="log_manager_cron_schedule_type" value="specific_date" <?php checked($schedule_type, 'specific_date'); ?>>
                                <strong>Specific Date</strong> - Run once on a specific date and time
                            </label><br>

                            <label>
                                <input type="radio" name="log_manager_cron_schedule_type" value="custom_interval" <?php checked($schedule_type, 'custom_interval'); ?>>
                                <strong>Custom Interval</strong> - Run at custom intervals
                            </label>
                        </td>
                    </tr>

                    <tr id="cron-time-row">
                        <th>Time</th>
                        <td>
                            <input type="time" name="log_manager_cron_time" value="<?php echo esc_attr($cron_time); ?>">
                            <p class="description">What time should the cleanup run? (24-hour format)</p>
                        </td>
                    </tr>

                    <tr id="cron-specific-date-row">
                        <th>Specific Date</th>
                        <td>
                            <input type="date" name="log_manager_cron_specific_date"
                                value="<?php echo esc_attr($specific_date); ?>" min="<?php echo date('Y-m-d'); ?>">
                            <p class="description">Select a future date for one-time cleanup.</p>
                        </td>
                    </tr>

                    <tr id="cron-interval-row">
                        <th>Interval</th>
                        <td>
                            <select name="log_manager_cron_interval" id="cron-interval-select">
                                <option value="hourly" <?php selected($interval, 'hourly'); ?>>Every Hour</option>
                                <option value="twicedaily" <?php selected($interval, 'twicedaily'); ?>>Twice Daily (Every 12
                                    Hours)</option>
                                <option value="daily" <?php selected($interval, 'daily'); ?>>Daily</option>
                                <option value="weekly" <?php selected($interval, 'weekly'); ?>>Weekly</option>
                                <option value="monthly" <?php selected($interval, 'monthly'); ?>>Monthly</option>
                                <option value="custom_range" <?php selected($interval, 'custom_range'); ?>>Custom Range (1-30
                                    days)</option>
                            </select>
                            <p class="description">How often should the cleanup run?</p>
                        </td>
                    </tr>

                    <!-- Weekly Day Selection -->
                    <tr id="weekly-day-row" style="display: none;">
                        <th>Day of Week</th>
                        <td>
                            <select name="log_manager_weekly_day">
                                <option value="monday" <?php selected($weekly_day, 'monday'); ?>>Monday</option>
                                <option value="tuesday" <?php selected($weekly_day, 'tuesday'); ?>>Tuesday</option>
                                <option value="wednesday" <?php selected($weekly_day, 'wednesday'); ?>>Wednesday</option>
                                <option value="thursday" <?php selected($weekly_day, 'thursday'); ?>>Thursday</option>
                                <option value="friday" <?php selected($weekly_day, 'friday'); ?>>Friday</option>
                                <option value="saturday" <?php selected($weekly_day, 'saturday'); ?>>Saturday</option>
                                <option value="sunday" <?php selected($weekly_day, 'sunday'); ?>>Sunday</option>
                            </select>
                            <p class="description">Select which day of the week to run cleanup.</p>
                        </td>
                    </tr>

                    <!-- Monthly Date Selection -->
                    <tr id="monthly-option-row" style="display: none;">
                        <th>Day of Month</th>
                        <td>
                            <label>
                                <input type="radio" name="log_manager_monthly_option" value="first" <?php checked($monthly_option, 'first'); ?>>
                                First day of the month
                            </label><br>
                            <label>
                                <input type="radio" name="log_manager_monthly_option" value="last" <?php checked($monthly_option, 'last'); ?>>
                                Last day of the month
                            </label>
                            <p class="description">Select when in the month to run cleanup.</p>
                        </td>
                    </tr>

                    <!-- Custom Range Days -->
                    <tr id="custom-days-row" style="display: none;">
                        <th>Number of Days</th>
                        <td>
                            <input type="number" name="log_manager_custom_days" value="<?php echo esc_attr($custom_days); ?>"
                                min="1" max="30" class="small-text"> days
                            <p class="description">Run cleanup every N days (1-30).</p>
                        </td>
                    </tr>

                    <tr id="delete-count-row">
                        <th>Number of Logs to Delete</th>
                        <td>
                            <input type="number" name="log_manager_delete_count" value="<?php echo esc_attr($delete_count); ?>"
                                min="1" max="10000" class="small-text"> logs
                            <p class="description">How many oldest logs to delete during cleanup? (Deleted in ascending order by
                                date, oldest first. Default: 100)</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>
        </div>

        <style>
            .form-table th {
                width: 250px;
            }

            .form-table input[type="time"],
            .form-table input[type="date"] {
                padding: 5px 10px;
            }

            .form-table select {
                min-width: 200px;
            }

            h2 {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }

            h2:first-of-type {
                margin-top: 0;
                padding-top: 0;
                border-top: none;
            }
        </style>

        <script>
            (function () {
                const storageRadios = document.querySelectorAll('input[name="log_manager_storage_type"]');
                const pathRow = document.getElementById('log-file-path-row');

                function togglePath() {
                    const selected = document.querySelector('input[name="log_manager_storage_type"]:checked').value;
                    pathRow.style.display = selected === 'file' ? 'table-row' : 'none';
                }

                storageRadios.forEach(r => r.addEventListener('change', togglePath));
                togglePath();

                const cronCheckbox = document.querySelector('input[name="log_manager_cron_enabled"]');
                const cronSettingsRow = document.getElementById('cron-settings-row');
                const cronTimeRow = document.getElementById('cron-time-row');
                const cronDateRow = document.getElementById('cron-specific-date-row');
                const cronIntervalRow = document.getElementById('cron-interval-row');
                const deleteCountRow = document.getElementById('delete-count-row');
                const weeklyDayRow = document.getElementById('weekly-day-row');
                const monthlyOptionRow = document.getElementById('monthly-option-row');
                const customDaysRow = document.getElementById('custom-days-row');

                function toggleCronSettings() {
                    const isEnabled = cronCheckbox.checked;
                    const display = isEnabled ? 'table-row' : 'none';

                    cronSettingsRow.style.display = display;
                    cronTimeRow.style.display = display;
                    deleteCountRow.style.display = display;

                    if (isEnabled) {
                        toggleScheduleType();
                    } else {
                        cronDateRow.style.display = 'none';
                        cronIntervalRow.style.display = 'none';
                        weeklyDayRow.style.display = 'none';
                        monthlyOptionRow.style.display = 'none';
                        customDaysRow.style.display = 'none';
                    }
                }

                const scheduleRadios = document.querySelectorAll('input[name="log_manager_cron_schedule_type"]');

                function toggleScheduleType() {
                    const selected = document.querySelector('input[name="log_manager_cron_schedule_type"]:checked').value;

                    cronDateRow.style.display = selected === 'specific_date' ? 'table-row' : 'none';
                    cronIntervalRow.style.display = selected === 'custom_interval' ? 'table-row' : 'none';

                    if (selected === 'custom_interval') {
                        toggleIntervalOptions();
                    } else {
                        weeklyDayRow.style.display = 'none';
                        monthlyOptionRow.style.display = 'none';
                        customDaysRow.style.display = 'none';
                    }
                }

                const intervalSelect = document.getElementById('cron-interval-select');
                function toggleIntervalOptions() {
                    const selected = intervalSelect.value;

                    weeklyDayRow.style.display = selected === 'weekly' ? 'table-row' : 'none';
                    monthlyOptionRow.style.display = selected === 'monthly' ? 'table-row' : 'none';
                    customDaysRow.style.display = selected === 'custom_range' ? 'table-row' : 'none';
                }

                if (cronCheckbox) {
                    cronCheckbox.addEventListener('change', toggleCronSettings);
                    scheduleRadios.forEach(r => r.addEventListener('change', toggleScheduleType));
                    intervalSelect.addEventListener('change', toggleIntervalOptions);

                    toggleCronSettings();
                }
            })();
        </script>
        <?php
    }
}
?>