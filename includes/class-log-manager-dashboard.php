<?php
/**
 * Plugin Dashboard View Class File.
 * Handels: pagination, filteration, sorting, bulk actions, data fetching
 * 
 * @since 1.0.1
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
			'user_email' => 'Email',
			'ip_address' => 'IP Address',
			'event_time' => 'Date',
			'severity' => 'Severity',
			'event_type' => 'Event Type',
			'object_type' => 'Object Type',
			'message' => 'Message',
		];
	}

	/**
	 * Return the user's email
	 * 
	 * @return string
	 */
	public function column_user_email($item)
	{
		if (empty($item['userid'])) {
			return '—';
		}

		$user = get_userdata(absint($item['userid']));
		if (!$user) {
			return '<em>User deleted</em>';
		}

		return sprintf(
			'<a href="mailto:%1$s">%1$s</a>',
			esc_html($user->user_email)
		);
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

		if (!empty($_POST['reset_filters'])) {
			$_POST = [];
			wp_redirect(admin_url('admin.php?page=' . esc_attr($_REQUEST['page'])));
			exit;
		}

		$start_date = $_POST['start_date'] ?? '';
		$end_date = $_POST['end_date'] ?? '';
		$role = $_POST['role'] ?? '';
		$user_id = $_POST['user_id'] ?? '';
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
				<option value="info" <?php selected($_POST['severity'] ?? '', 'info'); ?>>Info</option>
				<option value="notice" <?php selected($_POST['severity'] ?? '', 'notice'); ?>>Notice</option>
				<option value="warning" <?php selected($_POST['severity'] ?? '', 'warning'); ?>>Warning</option>
				<option value="error" <?php selected($_POST['severity'] ?? '', 'error'); ?>>Error</option>
				<option value="critical" <?php selected($_POST['severity'] ?? '', 'critical'); ?>>Critical</option>
				<option value="bug" <?php selected($_POST['severity'] ?? '', 'bug'); ?>>Bug</option>
			</select>

			<?php submit_button('Filter', '', 'filter_action', false); ?>

			<!-- Export CSV Button -->
			<button type="submit" name="export_csv" value="1" class="button button-secondary">
				Export CSV
			</button>

			<!-- Export PDF Button -->
			<button type="submit" name="export_pdf" value="1" class="button button-secondary">
				Export PDF
			</button>

			<!-- Reset Button -->
			<a href="<?php echo esc_url(remove_query_arg(['start_date', 'end_date', 'role', 'user_id', 'severity', 's'])); ?>"
				class="button">
				Reset
			</a>

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
	 * Override table navigation to remove top pagination
	 *
	 * @param string $which
	 * @return void
	 */
	protected function pagination($which)
	{
		if ('top' === $which) {
			return; // hide ONLY top pagination
		}

		parent::pagination($which); // keep bottom pagination
	}


	/**
	 * Handles bulk delete action
	 * 
	 * @return void
	 */
	public function process_bulk_action()
	{
		global $wpdb;
		$table = $wpdb->prefix . 'log_db';

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

		$per_page = $this->get_items_per_page('logs_per_page', 10);
		$current_page = $this->get_pagenum();

		if (!empty($_POST['reset_filters'])) {
			$_POST = [];
			wp_redirect(admin_url('admin.php?page=' . esc_attr($_REQUEST['page'])));
			exit;
		}

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
		$table = $wpdb->prefix . 'log_db';
		$offset = ($page - 1) * $per_page;

		$allowed_orderby = ['id', 'event_time', 'severity', 'event_type', 'object_type'];
		$orderby = in_array($_POST['orderby'] ?? '', $allowed_orderby, true) ? $_POST['orderby'] : 'id';
		$order = (!empty($_POST['order']) && $_POST['order'] === 'asc') ? 'ASC' : 'DESC';

		$where = [];
		$values = [];

		if (!empty($_POST['s'])) {
			$like = '%' . $wpdb->esc_like($_POST['s']) . '%';
			$where[] = "(ip_address LIKE %s 
						OR event_type LIKE %s 
						OR object_type LIKE %s 
						OR message LIKE %s
						OR severity LIKE %s)";
			array_push($values, $like, $like, $like, $like, $like);
		}

		if (!empty($_POST['start_date'])) {
			$where[] = "event_time >= %s";
			$values[] = str_replace('-', '/', $_POST['start_date']);
		}

		if (!empty($_POST['end_date'])) {
			$where[] = "event_time <= %s";
			$values[] = str_replace('-', '/', $_POST['end_date']);
		}

		if (!empty($_POST['user_id'])) {
			$where[] = "userid = %d";
			$values[] = absint($_POST['user_id']);
		}

		if (!empty($_POST['severity'])) {
			$where[] = "severity = %s";
			$values[] = $_POST['severity'];
		}

		if (!empty($_POST['role'])) {
			$user_ids = get_users(['role' => $_POST['role'], 'fields' => 'ID']);
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
		$table = $wpdb->prefix . 'log_db';
		$where = [];
		$values = [];

		if (!empty($_POST['s'])) {
			$like = '%' . $wpdb->esc_like($_POST['s']) . '%';
			$where[] = "(ip_address LIKE %s 
						OR event_type LIKE %s 
						OR object_type LIKE %s 
						OR message LIKE %s
						OR severity LIKE %s)";
			array_push($values, $like, $like, $like, $like, $like);
		}

		if (!empty($_POST['start_date'])) {
			$where[] = "event_time >= %s";
			$values[] = str_replace('-', '/', $_POST['start_date']);
		}

		if (!empty($_POST['end_date'])) {
			$where[] = "event_time <= %s";
			$values[] = str_replace('-', '/', $_POST['end_date']);
		}

		if (!empty($_POST['user_id'])) {
			$where[] = "userid = %d";
			$values[] = absint($_POST['user_id']);
		}

		if (!empty($_POST['severity'])) {
			$where[] = "severity = %s";
			$values[] = $_POST['severity'];
		}

		if (!empty($_POST['role'])) {
			$user_ids = get_users(['role' => $_POST['role'], 'fields' => 'ID']);
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
		// Guest
		if (empty($item['userid'])) {
			return '<em>Guest</em>';
		}

		$user = get_userdata(absint($item['userid']));

		// Deleted user
		if (!$user) {
			return '<em>User deleted</em>';
		}

		$avatar = get_avatar($user->ID, 32);
		$profile_url = admin_url('user-edit.php?user_id=' . absint($user->ID));
		$display_name = $user->display_name;
		$email = $user->user_email;
		$username = $user->user_login;
		$roles = !empty($user->roles)
			? implode(', ', array_map('ucfirst', $user->roles))
			: '—';

		return sprintf(
			'<div class="lm-user-cell">
			<a href="%s" class="lm-user-link">
				<div class="lm-user-avatar">%s</div>
				<div class="lm-user-meta">
					<strong class="lm-user-name">%s</strong>
					<div class="lm-user-role">%s</div>
				</div>
			</a>

			<div class="lm-user-tooltip">
				<b>Username:</b> %s<br>
				<b>Email:</b> %s<br>
				<b>Role:</b> %s
			</div>
		</div>',
			esc_url($profile_url),
			$avatar,
			esc_html($display_name),
			esc_html($roles),
			esc_html($username),
			esc_html($email),
			esc_html($roles)
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

			$short = mb_substr(wp_strip_all_tags($item['message']), 0, 30);

			return sprintf(
				'<span>%s...</span>
             <a href="#" 
                class="lm-view-log"
                data-id="%d"
                data-user="%s"
                data-ip="%s"
                data-date="%s"
                data-severity="%s"
                data-event="%s"
                data-object="%s"
                data-message="%s">
                View details
             </a>',
				esc_html($short),
				absint($item['id']),
				esc_attr($item['userid'] ?: 'Guest'),
				esc_attr($item['ip_address']),
				esc_attr($item['event_time']),
				esc_attr(ucfirst($item['severity'])),
				esc_attr($item['event_type']),
				esc_attr($item['object_type']),
				esc_attr($item['message'])
			);
		}

		return esc_html($item[$column_name] ?? '—');
		}
	}

/**
 * Dashboard Renderer class
 * 
 * @since 1.0.1
 * @package Log_Manager
 * 
 */
class Log_Manager_Dashboard
{
	/**
	 * Constructor.
	 *
	 * Registers screen options for Log Manager pagination.
	 *
	 * @since 1.0.1
	 * @return void
	 */
	public function __construct()
	{
		add_action('load-toplevel_page_log-manager', [$this, 'sdw_log_manager_screen_options']);
		add_filter('set-screen-option', [$this, 'sdw_log_manager_set_screen_option'], 10, 3);
	}

	/**
	 * Adds "Logs per page" option to Screen Options.
	 *
	 * @since 1.0.1
	 * @return void
	 */
	function sdw_log_manager_screen_options()
	{
		$option = 'per_page';

		$args = [
			'label' => 'Logs per page',
			'default' => 10,
			'option' => 'logs_per_page',
		];

		add_screen_option($option, $args);
	}

	/**
	 * Saves screen option value.
	 *
	 * @since 1.0.1
	 * @return void
	 */
	function sdw_log_manager_set_screen_option($status, $option, $value)
	{
		if ($option === 'logs_per_page') {
			return (int) $value;
		}
		return $status;
	}

	/**
	 * Dashboard UI including filters, table, styles, and scripts.
	 * 
	 * @since 1.0.0
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
				if (e.target.classList.contains('lm-view-log')) {
					e.preventDefault();
					const btn = e.target;

					document.getElementById('lm-id').textContent = btn.dataset.id;
					document.getElementById('lm-user').textContent = btn.dataset.user;
					document.getElementById('lm-ip').textContent = btn.dataset.ip;
					document.getElementById('lm-date').textContent = btn.dataset.date;
					document.getElementById('lm-severity').textContent = btn.dataset.severity;
					document.getElementById('lm-event').textContent = btn.dataset.event;
					document.getElementById('lm-object').textContent = btn.dataset.object;
					document.getElementById('lm-message').innerHTML  = btn.dataset.message;

					document.getElementById('lm-log-modal').style.display = 'block';
				}

				if (e.target.classList.contains('lm-close') || e.target.id === 'lm-log-modal') {
					document.getElementById('lm-log-modal').style.display = 'none';
				}
			});

		</script>

		<div class="wrap">
			<h1 class="wp-heading-inline">Log Manager Dashboard</h1>

			<form method="post">
				<input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
				<?php
				$table->search_box('Search Logs', 'log-search');

				if ($table->current_action() === 'delete') {
					echo '<div class="notice notice-success is-dismissible">
							<p>Logs deleted successfully.</p>
						</div>';
				}

				$table->display();
				?>
			</form>

			<div id="lm-log-modal" style="display:none;">
				<div class="lm-modal-content">
					<span class="lm-close">&times;</span>
					<h2>Log Details</h2>

					<table class="widefat striped">
						<tr>
							<th>Log ID</th>
							<td id="lm-id"></td>
						</tr>
						<tr>
							<th>User ID</th>
							<td id="lm-user"></td>
						</tr>
						<tr>
							<th>IP Address</th>
							<td id="lm-ip"></td>
						</tr>
						<tr>
							<th>Date & Time</th>
							<td id="lm-date"></td>
						</tr>
						<tr>
							<th>Severity</th>
							<td id="lm-severity"></td>
						</tr>
						<tr>
							<th>Event Type</th>
							<td id="lm-event"></td>
						</tr>
						<tr>
							<th>Object Type</th>
							<td id="lm-object"></td>
						</tr>
						<tr>
							<th>Message</th>
							<td id="lm-message"></td>
						</tr>
					</table>
				</div>
			</div>

			<style>
				#lm-log-modal {
					position: fixed;
					inset: 0;
					background: rgba(0, 0, 0, 0.6);
					z-index: 9999;
				}

				.lm-modal-content {
					background: #fff;
					width: 70%;
					margin: 5% auto;
					padding: 20px;
					border-radius: 6px;
					position: relative;
				}

				.lm-close {
					position: absolute;
					right: 15px;
					top: 10px;
					font-size: 22px;
					cursor: pointer;
				}

				.lm-user-cell {
					position: relative;
					display: inline-block;
				}

				.lm-user-link {
					display: flex;
					gap: 10px;
					align-items: center;
					text-decoration: none;
					color: inherit;
				}

				.lm-user-avatar img {
					border-radius: 50%;
				}

				.lm-user-name {
					color: #2271b1;
				}

				.lm-user-role {
					font-size: 12px;
					color: #666;
				}

				.lm-user-tooltip {
					position: absolute;
					top: 100%;
					left: 0;
					width: 260px;
					background: #1e1e1e;
					color: #fff;
					padding: 10px;
					border-radius: 6px;
					font-size: 12px;
					visibility: hidden;
					opacity: 0;
					transition: opacity 0.2s ease;
					z-index: 999;
				}

				.lm-user-cell:hover .lm-user-tooltip {
					visibility: visible;
					opacity: 1;
				}
			</style>

		</div>
		<?php
	}
}


/**
 * Handles Log Manager CSV export.
 * Exports all logs or filtered logs based on active filters.
 *
 * @since 1.0.1
 * @return void
 */
function sdw_log_manager_export_csv_handler()
{
	// Only run in admin area
	if (!is_admin()) {
		return;
	}

	if (!is_admin() || empty($_POST['export_csv']))
		return;

	if (!current_user_can('manage_options'))
		return;

	global $wpdb;
	$table = $wpdb->prefix . 'log_db';

	$where = [];
	$values = [];

	if (!empty($_POST['s'])) {
		$like = '%' . $wpdb->esc_like($_POST['s']) . '%';
		$where[] = "(ip_address LIKE %s OR event_type LIKE %s OR object_type LIKE %s OR message LIKE %s OR severity LIKE %s)";
		$values = array_merge($values, [$like, $like, $like, $like, $like]);
	}

	if (!empty($_POST['start_date'])) {
		$where[] = "event_time >= %s";
		$values[] = $_POST['start_date'] . ' 00:00:00';
	}

	if (!empty($_POST['end_date'])) {
		$where[] = "event_time <= %s";
		$values[] = $_POST['end_date'] . ' 23:59:59';
	}

	if (!empty($_POST['user_id'])) {
		$where[] = "userid = %d";
		$values[] = absint($_POST['user_id']);
	}

	if (!empty($_POST['severity'])) {
		$where[] = "severity = %s";
		$values[] = sanitize_text_field($_POST['severity']);
	}

	if (!empty($_POST['role'])) {
		$user_ids = get_users(['role' => sanitize_text_field($_POST['role']), 'fields' => 'ID']);
		if (!empty($user_ids)) {
			$where[] = 'userid IN (' . implode(',', array_map('absint', $user_ids)) . ')';
		} else {
			$where[] = '1=0';
		}
	}

	if (!empty($_POST['id']) && is_array($_POST['id'])) {
		$placeholders = implode(',', array_fill(0, count($_POST['id']), '%d'));
		$where[] = "id IN ($placeholders)";
		$values = array_merge($values, array_map('absint', $_POST['id']));
	}

	$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
	$sql = "SELECT * FROM $table $where_sql ORDER BY id DESC";
	$results = $values ? $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=log-manager-' . date('Y-m-d') . '.csv');
	$output = fopen('php://output', 'w');
	fputcsv($output, ['User ID', 'IP Address', 'Date & Time', 'Severity', 'Event Type', 'Object Type', 'Message']);
	foreach ($results as $row) {
		fputcsv($output, [$row['userid'] ?: 'Guest',$row['ip_address'],$row['event_time'],ucfirst($row['severity']),$row['event_type'],$row['object_type'],$row['message']]);
		}
	fclose($output);
	exit;
}
add_action('admin_init', 'sdw_log_manager_export_csv_handler');


/**
 * Exports log records as a PDF file.
 *
 * - Triggers from dashboard Export → PDF action.
 * - Applies all active filters (search, date, user, role, severity).
 * - Exports all matching logs (not limited by pagination).
 * - Generates PDF using mPDF and forces file download.
 *
 * @since 1.0.1
 * @package Log_Manager
 */
use Mpdf\Mpdf;
function sdw_log_manager_export_pdf_handler()
{
	if (!is_admin()) {
		return;
	}

	if (empty($_POST['export_pdf'])) {
		return;
	}

	if (!current_user_can('manage_options')) {
		return;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'log_db';

	$where = [];
	$values = [];

	if (!empty($_POST['s'])) {
		$like = '%' . $wpdb->esc_like($_POST['s']) . '%';
		$where[] = "(ip_address LIKE %s 
					OR event_type LIKE %s 
					OR object_type LIKE %s 
					OR message LIKE %s
					OR severity LIKE %s)";
		array_push($values, $like, $like, $like, $like, $like);
	}

	if (!empty($_POST['start_date'])) {
		$where[] = "event_time >= %s";
		$values[] = $_POST['start_date'] . ' 00:00:00';
	}

	if (!empty($_POST['end_date'])) {
		$where[] = "event_time <= %s";
		$values[] = $_POST['end_date'] . ' 23:59:59';
	}

	if (!empty($_POST['user_id'])) {
		$where[] = "userid = %d";
		$values[] = absint($_POST['user_id']);
	}

	if (!empty($_POST['severity'])) {
		$where[] = "severity = %s";
		$values[] = sanitize_text_field($_POST['severity']);
	}

	if (!empty($_POST['role'])) {
		$user_ids = get_users([
			'role' => sanitize_text_field($_POST['role']),
			'fields' => 'ID',
		]);

		if (!empty($user_ids)) {
			$where[] = 'userid IN (' . implode(',', array_map('absint', $user_ids)) . ')';
		} else {
			$where[] = '1=0';
		}
	}

	if (!empty($_POST['id']) && is_array($_POST['id'])) {
		$placeholders = implode(',', array_fill(0, count($_POST['id']), '%d'));
		$where[] = "id IN ($placeholders)";
		$values = array_merge($values, array_map('absint', $_POST['id']));
	}

	$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
	$sql = "SELECT * FROM $table $where_sql ORDER BY id DESC";

	$logs = $values
		? $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A)
		: $wpdb->get_results($sql, ARRAY_A);

	/* ---------- mPDF SETUP ---------- */
	$upload_dir = wp_upload_dir();
	$temp_dir = $upload_dir['basedir'] . '/mpdf-temp';

	if (!file_exists($temp_dir)) {
		wp_mkdir_p($temp_dir);
	}

	$mpdf = new Mpdf([
		'tempDir' => $temp_dir,
		'mode' => 'utf-8',
		'format' => 'A4-L',
	]);

	/* ---------- PDF HTML ---------- */
	ob_start();
	?>
	<style>
		body {
			font-family: sans-serif;
			font-size: 11px;
		}

		h1 {
			text-align: center;
		}

		table {
			width: 100%;
			border-collapse: collapse;
		}

		th,
		td {
			border: 1px solid #000;
			padding: 6px;
		}

		th {
			background: #f0f0f0;
		}
	</style>

	<h1>Log Manager Report</h1>

	<table>
		<thead>
			<tr>
				<th>User ID</th>
				<th>IP Address</th>
				<th>Date & Time</th>
				<th>Severity</th>
				<th>Event</th>
				<th>Object</th>
				<th>Message</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($logs as $log): ?>
				<tr>
					<td><?php echo esc_html($log['userid'] ?: 'Guest'); ?></td>
					<td><?php echo esc_html($log['ip_address']); ?></td>
					<td><?php echo esc_html($log['event_time']); ?></td>
					<td><?php echo esc_html(ucfirst($log['severity'])); ?></td>
					<td><?php echo esc_html($log['event_type']); ?></td>
					<td><?php echo esc_html($log['object_type']); ?></td>
					<td><?php echo esc_html($log['message']); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
	$html = ob_get_clean();

	$mpdf->WriteHTML($html);
	$mpdf->Output(
		'log-manager-' . date('Y-m-d') . '.pdf',
		'D'
	);
	exit;
}
add_action('admin_init', 'sdw_log_manager_export_pdf_handler');

