<?php
/**
 * Log Manager settings class file
 * 
 * It handle plugin settings menu page. consist of settings such as save upcoming log & cron settings
 * 
 * @package Log_Manager
 * @since 1.0.6
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Log_Manager_Settings
{
    const OPTION_NAME = 'log_manager_settings';
    const CRON_HOOK = 'log_manager_cleanup_hook';

    private static $defaults = [
        'log_destination' => 'database',
        'textfile_path' => '',
        'cron_schedule_type' => 'daily',
        'cron_time' => '02:00',
        'cron_specific_date' => '',
        'custom_days' => 7,
        'delete_count' => 100,
    ];

    /**
     * Initialize the settings class
     * 
     * @return void
     */
    public static function sdw_init()
    {
        add_action('admin_init', [__CLASS__, 'sdw_register_settings']);
        add_action('admin_init', [__CLASS__, 'sdw_handle_cron_actions']);
        add_action(self::CRON_HOOK, [__CLASS__, 'sdw_run_cleanup']);
        add_filter('cron_schedules', [__CLASS__, 'sdw_register_custom_schedules']);
    }

    /**
     * Register custom cron schedules
     * 
     * @param array $schedules Existing cron schedules
     * @return array Modified cron schedules
     */
    public static function sdw_register_custom_schedules($schedules)
    {
        $settings = self::sdw_get_settings();
        $days = intval($settings['custom_days']);

        if ($days > 0) {
            $schedules['log_manager_custom'] = [
                'interval' => $days * DAY_IN_SECONDS,
                'display' => sprintf(__('Every %d days', 'log-manager'), $days)
            ];
        }

        return $schedules;
    }

    /**
     * Handle cron actions from form submission
     * 
     * @return void
     */
    public static function sdw_handle_cron_actions()
    {
        if (!isset($_POST['option_page']) || $_POST['option_page'] !== 'log_manager_settings_group') {
            return;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'log_manager_settings_group-options')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Get submitted values
        $settings = self::sdw_get_settings_from_post();

        // Clear existing schedule
        wp_clear_scheduled_hook(self::CRON_HOOK);

        // Schedule cleanup based on settings
        self::sdw_schedule_cleanup($settings);
    }

    /**
     * Schedule cleanup based on settings
     * 
     * @param array $settings Settings array
     * @return void
     */
    private static function sdw_schedule_cleanup($settings)
    {
        $hook = self::CRON_HOOK;
        $time = $settings['cron_time'];
        $schedule_type = $settings['cron_schedule_type'];

        switch ($schedule_type) {
            case 'daily':
                self::sdw_schedule_daily_cleanup($hook, $time);
                break;

            case 'custom_range':
                $days = $settings['custom_days'];
                self::sdw_schedule_custom_range_cleanup($hook, $days, $time);
                break;

            case 'specific_date':
                $date = $settings['cron_specific_date'];
                self::sdw_schedule_specific_date_cleanup($hook, $date, $time);
                break;
        }
    }

    /**
     * Schedule daily cleanup
     * 
     * @param string $hook Cron hook name
     * @param string $time Time string (HH:MM)
     * @return void
     */
    private static function sdw_schedule_daily_cleanup($hook, $time)
    {
        list($h, $m) = explode(':', $time);
        $tz = wp_timezone();
        $now = new DateTime('now', $tz);
        $run = new DateTime("today $h:$m:00", $tz);

        if ($run <= $now) {
            $run->modify('+1 day');
        }

        wp_schedule_event($run->getTimestamp(), 'daily', $hook);
    }

    /**
     * Schedule cleanup at custom intervals
     * 
     * @param string $hook Cron hook name
     * @param int $days Number of days between cleanups
     * @param string $time Time string (HH:MM)
     * @return void
     */
    private static function sdw_schedule_custom_range_cleanup($hook, $days, $time)
    {
        if ($days <= 0) {
            return;
        }

        list($h, $m) = explode(':', $time);
        $tz = wp_timezone();
        $now = new DateTime('now', $tz);

        $run = new DateTime("now", $tz);
        $run->setTime($h, $m, 0);

        // If time has passed today, schedule for next interval
        if ($run <= $now) {
            $run->modify("+{$days} days");
        }

        wp_schedule_event($run->getTimestamp(), 'log_manager_custom', $hook);
    }

    /**
     * Schedule cleanup on specific date
     * 
     * @param string $hook Cron hook name
     * @param string $date Date string (YYYY-MM-DD)
     * @param string $time Time string (HH:MM)
     * @return void
     */
    private static function sdw_schedule_specific_date_cleanup($hook, $date, $time)
    {
        if (empty($date)) {
            return;
        }

        $tz = wp_timezone();
        $run = new DateTime("$date $time:00", $tz);

        if ($run > new DateTime('now', $tz)) {
            wp_schedule_single_event($run->getTimestamp(), $hook);
        }
    }

    /**
     * Get settings from POST data
     * 
     * @return array Sanitized settings
     */
    private static function sdw_get_settings_from_post()
    {
        $defaults = self::$defaults;
        $settings = [];

        foreach ($defaults as $key => $default) {
            if (isset($_POST['log_manager_settings'][$key])) {
                $settings[$key] = $_POST['log_manager_settings'][$key];
            } else {
                $settings[$key] = $default;
            }
        }

        return $settings;
    }

    /**
     * Run the cleanup process
     * 
     * @return void
     */
    public static function sdw_run_cleanup()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . Log_Manager::TABLE_NAME;

        $settings = self::sdw_get_settings();

        // Get number of logs to delete
        $delete_count = intval($settings['delete_count']);
        if ($delete_count <= 0) {
            return;
        }

        // Get oldest logs to delete
        $logs_to_delete = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name 
                 ORDER BY timestamp ASC 
                 LIMIT %d",
                $delete_count
            )
        );

        if (empty($logs_to_delete)) {
            return;
        }

        // Create backup file
        self::sdw_create_backup($logs_to_delete);

        // Delete logs
        $ids = implode(',', array_map(function ($log) {
            return $log->id;
        }, $logs_to_delete));

        $deleted = $wpdb->query("DELETE FROM $table_name WHERE id IN ($ids)");

        // Reschedule if not specific date
        if ($settings['cron_schedule_type'] !== 'specific_date') {
            self::sdw_schedule_cleanup($settings);
        }

        // Log the cleanup action
        if ($deleted !== false) {
            Log_Manager::sdw_log(
                'logs_cleaned',
                'system',
                0,
                'Log Cleanup',
                [
                    'deleted_count' => count($logs_to_delete),
                    'delete_count' => $delete_count,
                    'schedule_type' => $settings['cron_schedule_type']
                ],
                'info'
            );
        }
    }

    /**
     * Get next cleanup run time
     * 
     * @return string Formatted date or 'Not scheduled'
     */
    public static function sdw_get_next_run_time()
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            return date_i18n('Y-m-d H:i:s', $timestamp);
        }
        return 'Not scheduled';
    }

    /**
     * Create backup of logs before deletion
     * 
     * @param array $logs Array of log objects to backup
     * @return void
     */
    private static function sdw_create_backup($logs)
    {
        $backup_dir = WP_CONTENT_DIR . '/log-backups/';

        // Create directory if it doesn't exist
        if (!file_exists($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        // Create backup file name
        $timestamp = current_time('Y-m-d-H-i-s');
        $filename = $timestamp . '-logs-deleted.txt';
        $filepath = $backup_dir . $filename;

        // Prepare backup content
        $content = "========================================\n";
        $content .= "LOG MANAGER - DELETED LOGS BACKUP\n";
        $content .= "========================================\n";
        $content .= "Generated: " . current_time('Y-m-d H:i:s') . "\n";
        $content .= "Total logs deleted: " . count($logs) . "\n";
        $content .= "Next cleanup: " . self::sdw_get_next_run_time() . "\n";
        $content .= "========================================\n\n";

        foreach ($logs as $log) {
            $details = !empty($log->details) ? json_decode($log->details, true) : [];

            $content .= sprintf(
                "[ID: %d] [Date: %s] [Severity: %s] [User: %d] [IP: %s]\n",
                $log->id,
                $log->timestamp,
                strtoupper($log->severity),
                $log->user_id,
                $log->user_ip
            );
            $content .= sprintf(
                "Action: %s | Object: %s #%d - %s\n",
                $log->action,
                $log->object_type,
                $log->object_id,
                $log->object_name
            );

            if (!empty($details)) {
                $content .= "Details: " . json_encode($details, JSON_UNESCAPED_UNICODE) . "\n";
            }

            $content .= str_repeat("-", 40) . "\n";
        }

        $content .= "\n========================================\n";
        $content .= "END OF BACKUP\n";
        $content .= "========================================\n";

        // Write to file
        file_put_contents($filepath, $content);
    }

    /**
     * Register WordPress settings
     * 
     * @return void
     */
    public static function sdw_register_settings()
    {
        register_setting(
            'log_manager_settings_group',
            self::OPTION_NAME,
            [__CLASS__, 'sdw_sanitize_settings']
        );

        // Main settings section
        add_settings_section(
            'log_manager_main_section',
            __('Logging Configuration', 'log-manager'),
            '__return_empty_string',
            'log-manager-settings'
        );

        // Log destination field
        add_settings_field(
            'log_destination',
            __('Log Destination', 'log-manager'),
            [__CLASS__, 'sdw_render_log_destination_field'],
            'log-manager-settings',
            'log_manager_main_section'
        );

        // Text file path field
        add_settings_field(
            'textfile_path',
            __('Text File Path', 'log-manager'),
            [__CLASS__, 'sdw_render_textfile_path_field'],
            'log-manager-settings',
            'log_manager_main_section'
        );

        // Cleanup Schedule section
        add_settings_section(
            'log_manager_schedule_section',
            __('Cleanup Schedule', 'log-manager'),
            [__CLASS__, 'sdw_render_schedule_section_description'],
            'log-manager-settings'
        );

        // Schedule type field
        add_settings_field(
            'cron_schedule_type',
            __('Schedule Type', 'log-manager'),
            [__CLASS__, 'sdw_render_schedule_type_field'],
            'log-manager-settings',
            'log_manager_schedule_section'
        );

        // Time field
        add_settings_field(
            'cron_time',
            __('Run Time', 'log-manager'),
            [__CLASS__, 'sdw_render_cron_time_field'],
            'log-manager-settings',
            'log_manager_schedule_section'
        );

        // Custom days field
        add_settings_field(
            'custom_days',
            __('Every X Days', 'log-manager'),
            [__CLASS__, 'sdw_render_custom_days_field'],
            'log-manager-settings',
            'log_manager_schedule_section'
        );

        // Specific date field
        add_settings_field(
            'cron_specific_date',
            __('Specific Date', 'log-manager'),
            [__CLASS__, 'sdw_render_specific_date_field'],
            'log-manager-settings',
            'log_manager_schedule_section'
        );

        // Delete count field
        add_settings_field(
            'delete_count',
            __('Delete Count', 'log-manager'),
            [__CLASS__, 'sdw_render_delete_count_field'],
            'log-manager-settings',
            'log_manager_schedule_section'
        );
    }

    /**
     * Sanitize settings input
     * 
     * @param array $input Raw input data
     * @return array Sanitized settings
     */
    public static function sdw_sanitize_settings($input)
    {
        $sanitized = [];

        // Log destination
        $sanitized['log_destination'] = in_array($input['log_destination'], ['database', 'textfile'])
            ? $input['log_destination']
            : 'database';

        // Text file path
        $sanitized['textfile_path'] = sanitize_text_field($input['textfile_path']);

        // Schedule type
        $valid_types = ['daily', 'custom_range', 'specific_date'];
        $sanitized['cron_schedule_type'] = in_array($input['cron_schedule_type'], $valid_types)
            ? $input['cron_schedule_type']
            : 'daily';

        // Cron time
        $sanitized['cron_time'] = preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $input['cron_time'])
            ? $input['cron_time']
            : '02:00';

        // Specific date
        $sanitized['cron_specific_date'] = sanitize_text_field($input['cron_specific_date']);

        // Delete count (1-100000)
        $count = intval($input['delete_count']);
        $sanitized['delete_count'] = ($count >= 1 && $count <= 100000) ? $count : 100;

        // Custom days (1-365)
        $custom_days = intval($input['custom_days']);
        $sanitized['custom_days'] = ($custom_days >= 1 && $custom_days <= 365) ? $custom_days : 7;

        return $sanitized;
    }

    /**
     * Render schedule section description
     * 
     * @return void
     */
    public static function sdw_render_schedule_section_description()
    {
        $next_run = wp_next_scheduled(self::CRON_HOOK);
        $settings = self::sdw_get_settings();

        if ($next_run) {
            $time_left = $next_run - time();

            if ($time_left > 0) {
                $days = floor($time_left / DAY_IN_SECONDS);
                $hours = floor(($time_left % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
                $minutes = floor(($time_left % HOUR_IN_SECONDS) / 60);
                $seconds = $time_left % 60;

                echo '<div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 15px 0; border-radius: 4px;">';
                echo '<h3 style="margin-top: 0; color: #2271b1;">' . __(' Scheduled Cleanup Status', 'log-manager') . '</h3>';

                echo '<div style="display: flex; gap: 30px; flex-wrap: wrap; margin-bottom: 15px;">';
                echo '<div>';
                echo '<strong>' . __('Next run:', 'log-manager') . '</strong><br>';
                echo '<span style="font-size: 16px; font-weight: bold;">' . date_i18n('Y-m-d H:i:s', $next_run) . '</span>';
                echo '</div>';

                echo '<div>';
                echo '<strong>' . __('Delete count:', 'log-manager') . '</strong><br>';
                echo '<span style="font-size: 16px;">' . esc_html($settings['delete_count']) . ' logs</span>';
                echo '</div>';
                echo '</div>';

                echo '<div style="background: #fff; padding: 15px; border-radius: 4px; border: 1px solid #c3c4c7;">';
                echo '<strong>' . __('⏳ Time until next run:', 'log-manager') . '</strong><br>';
                echo '<div id="log-manager-countdown" style="font-size: 24px; font-weight: bold; color: #2271b1; margin: 10px 0;">';
                echo '<span id="countdown-days">' . $days . '</span>d ';
                echo '<span id="countdown-hours">' . $hours . '</span>h ';
                echo '<span id="countdown-minutes">' . $minutes . '</span>m ';
                echo '<span id="countdown-seconds">' . $seconds . '</span>s';
                echo '</div>';
                echo '</div>';

                echo '<script>
                function updateCountdown() {
                    var days = document.getElementById("countdown-days");
                    var hours = document.getElementById("countdown-hours");
                    var minutes = document.getElementById("countdown-minutes");
                    var seconds = document.getElementById("countdown-seconds");
                    
                    var totalSeconds = 
                        parseInt(days.textContent) * 86400 +
                        parseInt(hours.textContent) * 3600 +
                        parseInt(minutes.textContent) * 60 +
                        parseInt(seconds.textContent);
                    
                    totalSeconds--;
                    
                    if (totalSeconds < 0) {
                        location.reload();
                        return;
                    }
                    
                    var d = Math.floor(totalSeconds / 86400);
                    var h = Math.floor((totalSeconds % 86400) / 3600);
                    var m = Math.floor((totalSeconds % 3600) / 60);
                    var s = totalSeconds % 60;
                    
                    days.textContent = d;
                    hours.textContent = h;
                    minutes.textContent = m;
                    seconds.textContent = s;
                }
                
                setInterval(updateCountdown, 1000);
                </script>';

                echo '</div>';
            } else {
                echo '<div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin: 10px 0;">';
                echo '<strong>' . __('Status:', 'log-manager') . '</strong> ' . __('Cleanup is running now or about to run...', 'log-manager');
                echo '</div>';
            }
        } else {
            echo '<div style="background: #f8d7da; border-left: 4px solid #dc3545; padding: 12px; margin: 10px 0;">';
            echo '<strong>' . __('Status:', 'log-manager') . '</strong> ' . __('No cleanup scheduled.', 'log-manager');
            echo '</div>';
        }

        echo '<p>' . __('Configure automatic log cleanup schedule.', 'log-manager') . '</p>';
    }

    /**
     * Render schedule type field
     * 
     * @return void
     */
    public static function sdw_render_schedule_type_field()
    {
        $settings = self::sdw_get_settings();
        ?>
        <select name="log_manager_settings[cron_schedule_type]" id="cron_schedule_type" style="width: 200px;">
            <option value="daily" <?php selected($settings['cron_schedule_type'], 'daily'); ?>>
                 <?php _e('Daily', 'log-manager'); ?>
            </option>
            <option value="custom_range" <?php selected($settings['cron_schedule_type'], 'custom_range'); ?>>
                 <?php _e('Custom Interval (Every X Days)', 'log-manager'); ?>
            </option>
            <option value="specific_date" <?php selected($settings['cron_schedule_type'], 'specific_date'); ?>>
                 <?php _e('Specific Date', 'log-manager'); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Render cron time field
     * 
     * @return void
     */
    public static function sdw_render_cron_time_field()
    {
        $settings = self::sdw_get_settings();
        $type = $settings['cron_schedule_type'];
        ?>
        <div id="time_field" style="<?php echo ($type === 'specific_date') ? '' : ''; ?>">
            <input type="time" name="log_manager_settings[cron_time]" value="<?php echo esc_attr($settings['cron_time']); ?>"
                style="width: 120px;">
            <p class="description"><?php _e('Time to run cleanup (24-hour format)', 'log-manager'); ?></p>
        </div>
        <?php
    }

    /**
     * Render custom days field
     * 
     * @return void
     */
    public static function sdw_render_custom_days_field()
    {
        $settings = self::sdw_get_settings();
        ?>
        <div id="custom_days_field"
            style="<?php echo ($settings['cron_schedule_type'] !== 'custom_range') ? 'display: none;' : ''; ?>">
            <input type="number" name="log_manager_settings[custom_days]"
                value="<?php echo esc_attr($settings['custom_days']); ?>" min="1" max="365" step="1" style="width: 100px;">
            <span><?php _e('days', 'log-manager'); ?></span>
            <p class="description"><?php _e('Cleanup will run every X days', 'log-manager'); ?></p>
        </div>
        <?php
    }

    /**
     * Render specific date field
     * 
     * @return void
     */
    public static function sdw_render_specific_date_field()
    {
        $settings = self::sdw_get_settings();
        ?>
        <div id="specific_date_field"
            style="<?php echo ($settings['cron_schedule_type'] !== 'specific_date') ? 'display: none;' : ''; ?>">
            <input type="date" name="log_manager_settings[cron_specific_date]"
                value="<?php echo esc_attr($settings['cron_specific_date']); ?>" min="<?php echo date('Y-m-d'); ?>"
                style="width: 200px;">
            <p class="description"><?php _e('Select date for one-time cleanup (runs once at specified time)', 'log-manager'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render delete count field
     * 
     * @return void
     */
    public static function sdw_render_delete_count_field()
    {
        $settings = self::sdw_get_settings();
        ?>
        <input type="number" name="log_manager_settings[delete_count]"
            value="<?php echo esc_attr($settings['delete_count']); ?>" min="1" max="100000" step="1" style="width: 120px;">
        <p class="description"><?php _e('Number of oldest logs to delete on each cleanup', 'log-manager'); ?></p>
        <?php
    }

    /**
     * Render log destination field
     * 
     * @return void
     */
    public static function sdw_render_log_destination_field()
    {
        $settings = self::sdw_get_settings();
        $current = $settings['log_destination'] ?? 'database';

        ?>
        <label style="display: block; margin-bottom: 10px;">
            <input type="radio" name="log_manager_settings[log_destination]" value="database" <?php checked($current, 'database'); ?>>
            <?php _e('Database', 'log-manager'); ?>
        </label>

        <label>
            <input type="radio" name="log_manager_settings[log_destination]" value="textfile" <?php checked($current, 'textfile'); ?>>
            <?php _e('Text File', 'log-manager'); ?>
        </label>
        <?php
    }

    /**
     * Render text file path field
     * 
     * @return void
     */
    public static function sdw_render_textfile_path_field()
    {
        $settings = self::sdw_get_settings();
        $current = $settings['textfile_path'] ?? '';

        ?>
        <div id="textfile-path-field"
            style="<?php echo ($settings['log_destination'] !== 'textfile') ? 'display: none;' : ''; ?>">
            <input type="text" name="log_manager_settings[textfile_path]" value="<?php echo esc_attr($current); ?>"
                class="regular-text" placeholder="<?php esc_attr_e('/full/path/to/folder', 'log-manager'); ?>">
            <p class="description">
                <?php _e('Enter folder path where log file will be created. Example:', 'log-manager'); ?><br>
                <code>/home/username/public_html/logs/</code><br>
                <code>C:\xampp\htdocs\mysite\logs\</code>
            </p>
        </div>
        <?php
    }

    /**
     * Render the settings page
     * 
     * @return void
     */
    public static function sdw_render_settings_page()
    {
        ?>
        <div class="wrap">
            <h1><?php _e('Log Manager Settings', 'log-manager'); ?></h1>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully!', 'log-manager'); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('log_manager_settings_group');
                do_settings_sections('log-manager-settings');
                submit_button();
                ?>
            </form>

            <script>
                jQuery(document).ready(function ($) {
                    function toggleScheduleFields() {
                        var scheduleType = $('#cron_schedule_type').val();

                        $('#custom_days_field').toggle(scheduleType === 'custom_range');
                        $('#specific_date_field').toggle(scheduleType === 'specific_date');
                        $('#time_field').show(); // Always show time field
                    }

                    function toggleTextFilePath() {
                        var isTextFile = $('input[name="log_manager_settings[log_destination]"][value="textfile"]').is(':checked');
                        $('#textfile-path-field').toggle(isTextFile);
                    }

                    toggleScheduleFields();
                    toggleTextFilePath();

                    $('#cron_schedule_type').change(toggleScheduleFields);
                    $('input[name="log_manager_settings[log_destination]"]').change(toggleTextFilePath);
                });
            </script>

            <div style="margin-top: 30px; padding: 15px; background: #f6f7f7; border: 1px solid #ccd0d4;">
                <h3><?php _e('Schedule Options:', 'log-manager'); ?></h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><strong> Daily:</strong> Delete logs every day at specified time</li>
                    <li><strong> Custom Interval:</strong> Delete logs every X days at specified time</li>
                    <li><strong> Specific Date:</strong> Delete logs once on selected date & time</li>
                </ul>

                <h4><?php _e('Backup Files:', 'log-manager'); ?></h4>
                <?php
                $backup_dir = WP_CONTENT_DIR . '/log-backups/';
                if (file_exists($backup_dir)) {
                    $backup_files = glob($backup_dir . '*.txt');
                    if (!empty($backup_files)) {
                        echo '<p>' . sprintf(__('Backups are stored in: %s', 'log-manager'), '<code>' . $backup_dir . '</code>') . '</p>';
                        echo '<p>' . sprintf(__('Total backup files: %d', 'log-manager'), count($backup_files)) . '</p>';
                    } else {
                        echo '<p>' . __('No backup files found yet.', 'log-manager') . '</p>';
                    }
                } else {
                    echo '<p>' . __('Backup directory will be created when cleanup runs.', 'log-manager') . '</p>';
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get settings from database
     * 
     * @return array Settings array
     */
    public static function sdw_get_settings()
    {
        $settings = get_option(self::OPTION_NAME, self::$defaults);
        return wp_parse_args($settings, self::$defaults);
    }

    /**
     * Get log destination setting
     * 
     * @return string 'database' or 'textfile'
     */
    public static function sdw_get_log_destination()
    {
        $settings = self::sdw_get_settings();
        return $settings['log_destination'];
    }

    /**
     * Get text file path setting
     * 
     * @return string Text file path
     */
    public static function sdw_get_textfile_path()
    {
        $settings = self::sdw_get_settings();
        return $settings['textfile_path'];
    }
}