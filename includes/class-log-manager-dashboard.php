<?php
/**
 * Plugin Dashboard View Class File.
 * Handels: pagination, filteration, sorting, bulk actions, data fetching
 * 
 * @since 1.0.0
 * @package Log_Manager
 */

if (!class_exists('WP_List_Table')) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Log Manager table extending WP_List_Table class.
 * 
 */
class Log_Manager_Log_Table extends WP_List_Table
{

	/**
	 * Defines all columns shown in the log table
	 * 
	 * @return void
	 */
	public function get_columns()
	{
		return [
			'cb' => '<input type="checkbox" />',
			'userid' => 'User',
			'ip_address' => 'IP Address',
			'event_time' => 'Date',
			'severity' => 'Severity',
			'event_type' => 'Event Type',
			'object_type' => 'Object Type',
			'message' => 'Message',
		];
	}

	/**
	 * Handels extra filters above the table.
	 * Filters applied: dates, role, user, severity.
	 * 
	 * @return void
	 */
	protected function extra_tablenav($which)
	{
		if ($which !== 'top')
			return;

		$roles = wp_roles()->roles;
		$users = get_users(['fields' => ['ID', 'display_name']]);

		$start_date = $_GET['start_date'] ?? '';
		$end_date = $_GET['end_date'] ?? '';
		$role = $_GET['role'] ?? '';
		$user_id = $_GET['user_id'] ?? '';
		?>
		<div class="alignleft actions">

			<!-- Start Date -->
			<input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" />

			<!-- End Date -->
			<input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" />

			<!-- Role Filter -->
			<select name="role">
				<option value="">All Roles</option>
				<?php foreach ($roles as $key => $role_data): ?>
					<option value="<?php echo esc_attr($key); ?>" <?php selected($role, $key); ?>>
						<?php echo esc_html($role_data['name']); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<!-- User Filter -->
			<select name="user_id">
				<option value="">All Users</option>
				<?php foreach ($users as $user): ?>
					<option value="<?php echo esc_attr($user->ID); ?>" <?php selected($user_id, $user->ID); ?>>
						<?php echo esc_html($user->display_name); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<!-- Severity Filter -->
			<select name="severity">
				<option value="">All Severities</option>
				<option value="info" <?php selected($_GET['severity'] ?? '', 'info'); ?>>Info</option>
				<option value="notice" <?php selected($_GET['severity'] ?? '', 'notice'); ?>>Notice</option>
				<option value="warning" <?php selected($_GET['severity'] ?? '', 'warning'); ?>>Warning</option>
				<option value="error" <?php selected($_GET['severity'] ?? '', 'error'); ?>>Error</option>
				<option value="critical" <?php selected($_GET['severity'] ?? '', 'critical'); ?>>Critical</option>
				<option value="bug" <?php selected($_GET['severity'] ?? '', 'bug'); ?>>Bug</option>
			</select>

			<?php submit_button('Filter', '', 'filter_action', false); ?>
		</div>
		<?php
	}

	/**
	 *  Defines hidden columns
	 * 
	 *  @return void
	 */
	public function get_hidden_columns()
	{
		return ['id'];
	}

	/**
	 * Registers bulk actions for the table
	 * 
	 * @return void
	 */
	protected function get_bulk_actions()
	{
		return [
			'delete' => __('Delete', 'log_manager'),
		];
	}

	/**
	 * Handles bulk delete action
	 * 
	 * @return void
	 */
	public function process_bulk_action()
	{
		global $wpdb;
		$table = $wpdb->prefix . 'event_db';

		if ($this->current_action() !== 'delete')
			return;

		if (empty($_REQUEST['id']) || !is_array($_REQUEST['id']))
			return;

		$ids = array_map('absint', $_REQUEST['id']);
		if (empty($ids))
			return;

		$placeholders = implode(',', array_fill(0, count($ids), '%d'));

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table WHERE id IN ($placeholders)",
				$ids
			)
		);
	}

	/**
	 * Defines sortable columns
	 * 
	 * @return void
	 */
	public function get_sortable_columns()
	{
		return [
			'event_time' => ['event_time', true],
			'severity' => ['severity', false],
			'event_type' => ['event_type', false],
			'object_type' => ['object_type', false],
		];
	}

	/**
	 * Prepares table data before rendering: Pagination, Fetch logs, Column headers
	 * 
	 * @return void
	 */
	public function prepare_items()
	{
		$this->process_bulk_action();

		$per_page = 10;
		$current_page = $this->get_pagenum();

		$this->items = $this->get_logs($per_page, $current_page);
		$this->set_pagination_args([
			'total_items' => $this->get_total_items(),
			'per_page' => $per_page,
		]);

		$this->_column_headers = [
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns(),
		];
	}

	/**
	 * Fetches log records from database.
	 * Applies: search, sorting, pagination, filteration(darte, user, role, severity)
	 * 
	 * @return array ~ List of log records for the current page after applying filters, sorting, and pagination.
	 */
	private function get_logs($per_page, $page)
	{
		global $wpdb;
		$table = $wpdb->prefix . 'event_db';
		$offset = ($page - 1) * $per_page;

		$allowed_orderby = ['id', 'event_time', 'severity', 'event_type', 'object_type'];
		$orderby = in_array($_GET['orderby'] ?? '', $allowed_orderby, true) ? $_GET['orderby'] : 'id';
		$order = (!empty($_GET['order']) && $_GET['order'] === 'asc') ? 'ASC' : 'DESC';

		$where = [];
		$values = [];

		// Search
		if (!empty($_GET['s'])) {
			$like = '%' . $wpdb->esc_like($_GET['s']) . '%';
			$where[] = "(ip_address LIKE %s 
						OR event_type LIKE %s 
						OR object_type LIKE %s 
						OR message LIKE %s
						OR severity LIKE %s)";
			array_push($values, $like, $like, $like, $like, $like);
		}

		// Date filter
		if (!empty($_GET['start_date'])) {
			$where[] = "event_time >= %s";
			$values[] = str_replace('-', '/', $_GET['start_date']);
		}

		if (!empty($_GET['end_date'])) {
			$where[] = "event_time <= %s";
			$values[] = str_replace('-', '/', $_GET['end_date']);
		}

		// User filter
		if (!empty($_GET['user_id'])) {
			$where[] = "userid = %d";
			$values[] = absint($_GET['user_id']);
		}

		// Severity filter
		if (!empty($_GET['severity'])) {
			$where[] = "severity = %s";
			$values[] = $_GET['severity'];
		}

		// Role filter
		if (!empty($_GET['role'])) {
			$user_ids = get_users(['role' => $_GET['role'], 'fields' => 'ID']);
			if (!empty($user_ids)) {
				$where[] = 'userid IN (' . implode(',', array_map('absint', $user_ids)) . ')';
			} else {
				$where[] = '1=0';
			}
		}

		$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

		$sql = "SELECT * FROM $table $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
		$values[] = $per_page;
		$values[] = $offset;

		return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
	}

	/**
	 * Returns total number of log records, used for pagination count
	 * 
	 * @return int Total number of log records matching the applied filters (used for pagination).
	 */
	private function get_total_items()
	{
		global $wpdb;
		$table = $wpdb->prefix . 'event_db';
		$where = [];
		$values = [];

		if (!empty($_GET['s'])) {
			$like = '%' . $wpdb->esc_like($_GET['s']) . '%';
			$where[] = "(ip_address LIKE %s 
						OR event_type LIKE %s 
						OR object_type LIKE %s 
						OR message LIKE %s
						OR severity LIKE %s)";
			array_push($values, $like, $like, $like, $like, $like);
		}

		// Date filter
		if (!empty($_GET['start_date'])) {
			$where[] = "event_time >= %s";
			$values[] = str_replace('-', '/', $_GET['start_date']);
		}

		if (!empty($_GET['end_date'])) {
			$where[] = "event_time <= %s";
			$values[] = str_replace('-', '/', $_GET['end_date']);
		}

		// User filter
		if (!empty($_GET['user_id'])) {
			$where[] = "userid = %d";
			$values[] = absint($_GET['user_id']);
		}

		// Severity filter
		if (!empty($_GET['severity'])) {
			$where[] = "severity = %s";
			$values[] = $_GET['severity'];
		}

		// Role filter
		if (!empty($_GET['role'])) {
			$user_ids = get_users(['role' => $_GET['role'], 'fields' => 'ID']);
			if (!empty($user_ids)) {
				$where[] = 'userid IN (' . implode(',', array_map('absint', $user_ids)) . ')';
			} else {
				$where[] = '1=0';
			}
		}

		$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
		$sql = "SELECT COUNT(*) FROM $table $where_sql";

		return (int) ($values ? $wpdb->get_var($wpdb->prepare($sql, $values)) : $wpdb->get_var($sql));
	}

	/**
	 * Renders checkbox column for bulk actions
	 * 
	 * @return string HTML checkbox markup for bulk action selection of a row.
	 */
	public function column_cb($item)
	{
		return sprintf('<input type="checkbox" name="id[]" value="%d" />', absint($item['id']));
	}

	/**
	 * Custom rendering for User column
	 * 
	 * @return string HTML output displaying user role and details, or Guest/User deleted text.
	 */
	public function column_userid($item)
	{
		if (empty($item['userid']))
			return '<em>Guest</em>';
		$user = get_userdata(absint($item['userid']));
		if (!$user)
			return '<em>User deleted</em>';

		$roles = !empty($user->roles) ? implode(', ', array_map('ucfirst', $user->roles)) : '—';
		return sprintf(
			'<span class="firstSpan">%s
                <span class="secondSpan">
                    <b>Username:</b> %s<br>
                    <b>Email:</b> %s<br>
                    <b>Nickname:</b> %s
                </span>
            </span>',
			esc_html($roles),
			esc_html($user->user_login),
			esc_html($user->user_email),
			esc_html($user->display_name)
		);
	}

	/**
	 * Default column renderer, handles long messages with 'read more' toggle
	 * 
	 * @return string HTML or text value for table cells, including Read More handling for messages.
	 */
	public function column_default($item, $column_name)
	{
		if ($column_name === 'message') {
			$full_message = $item['message'];
			$short = mb_substr(wp_strip_all_tags($full_message), 0, 20);
			if (mb_strlen(wp_strip_all_tags($full_message)) <= 20)
				return esc_html($short);
			return sprintf(
				'<span class="bxft-short">%s...</span>
                 <span class="bxft-full" style="display:none;">%s</span>
                 <a href="#" class="bxft-read-more">Read more</a>',
				esc_html($short),
				wp_kses_post($full_message)
			);
		}
		return esc_html($item[$column_name] ?? '—');
	}
}

/**
 * Dashboard Renderer class
 * 
 * @since 1.0.0
 * @package Log_Manager
 * 
 */
class Log_Manager_Dashboard
{
	/**
	 * Dashboard UI including filters, table, styles, and scripts.
	 * 
	 * @return void 
	 */
	public static function sdw_dashboard_render()
	{
		$table = new Log_Manager_Log_Table();
		$table->prepare_items();
		?>
		<style>
			.firstSpan {
				color: rgb(119, 162, 241);
				cursor: pointer;
			}

			.firstSpan .secondSpan {
				visibility: hidden;
				width: 500px;
				background-color: gray;
				color: #fff;
				text-align: center;
				border-radius: 6px;
				padding: 5px;
				position: absolute;
				z-index: 999;
			}

			.firstSpan:hover .secondSpan {
				visibility: visible;
			}
		</style>

		<script>
			document.addEventListener('click', function (e) {
				if (e.target.classList.contains('bxft-read-more')) {
					e.preventDefault();

					const link = e.target;
					const shortText = link.previousElementSibling.previousElementSibling;
					const fullText = link.previousElementSibling;

					if (fullText.style.display === 'none') {
						shortText.style.display = 'none';
						fullText.style.display = 'inline';
						link.textContent = 'Read less';
					} else {
						shortText.style.display = 'inline';
						fullText.style.display = 'none';
						link.textContent = 'Read more';
					}
				}
			});
		</script>

		<div class="wrap">
			<h1 class="wp-heading-inline">Log Manager Dashboard</h1>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
				<?php
				$table->search_box('Search Logs', 'log-search');

				// triggers on log deletion
				if ($table->current_action() === 'delete') {
					echo '<div class="notice notice-success is-dismissible">
							<p>Logs deleted successfully.</p>
						</div>';
				}

				$table->display();
				?>
			</form>
		</div>
		<?php
	}

}