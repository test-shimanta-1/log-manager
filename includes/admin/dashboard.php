<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/template.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
    require_once ABSPATH . 'wp-admin/includes/screen.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BXFT_Table extends WP_List_Table
{

    public function get_sortable_columns()
    {
        return array(
            'event_time' => array('event_time', true),
            'severity' => array('severity', false),
            'event_type' => array('event_type', false),
            'object_type' => array('object_type', false),
        );
    }

    public function get_total_items()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'event_db';
        $where = array();
        $values = array();

        if (!empty($_GET['s'])) {
            $like = '%' . $wpdb->esc_like($_GET['s']) . '%';
            $where[] = "(ip_address LIKE %s OR event_type LIKE %s OR object_type LIKE %s OR message LIKE %s)";
            array_push($values, $like, $like, $like, $like);
        }

        // if (!empty($_GET['from_date'])) {
        //     $where[] = "DATE(event_time) >= %s";
        //     $values[] = $_GET['from_date'];
        // }

        // if (!empty($_GET['to_date'])) {
        //     $where[] = "DATE(event_time) <= %s";
        //     $values[] = $_GET['to_date'];
        // }

        // if (!empty($_GET['filter_user'])) {
        //     $where[] = "userid = %d";
        //     $values[] = absint($_GET['filter_user']);
        // }

        // if (!empty($_GET['filter_role'])) {
        //     $role_users = get_users([
        //         'role'   => sanitize_text_field($_GET['filter_role']),
        //         'fields' => 'ID'
        //     ]);

        //     if (!empty($role_users)) {
        //         $placeholders = implode(',', array_fill(0, count($role_users), '%d'));
        //         $where[] = "userid IN ($placeholders)";
        //         $values = array_merge($values, $role_users);
        //     } else {
        //         $where[] = "1=0";
        //     }
        // }

        if (!empty($_GET['from_date'])) {
            $where[] = "DATE(event_time) >= %s";
            $values[] = $_GET['from_date'];
        }

        if (!empty($_GET['to_date'])) {
            $where[] = "DATE(event_time) <= %s";
            $values[] = $_GET['to_date'];
        }

        if (!empty($_GET['filter_user'])) {
            $where[] = "userid = %d";
            $values[] = absint($_GET['filter_user']);
        }

        if (!empty($_GET['filter_role'])){
            $role_user_ids = get_users([
                'role'   => sanitize_text_field($_GET['filter_role']),
                'fields' => 'ID'
            ]);
            if (!empty($role_user_ids)) {
                $placeholders = implode(',', array_fill(0, count($role_user_ids), '%d'));
                $where[] = "userid IN ($placeholders)";
                $values  = array_merge($values, $role_user_ids);
            } else {
                $where[] = "1 = 0";
            }
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $sql = "SELECT COUNT(*) FROM $table $where_sql";

        return (int) (
            !empty($values)
            ? $wpdb->get_var($wpdb->prepare($sql, $values))
            : $wpdb->get_var($sql)
        );
    }


    public function prepare_items()
    {
        $this->process_bulk_action();
        $per_page = 5;
        $current_page = $this->get_pagenum();

        $data = $this->wp_list_table_data($per_page, $current_page);
        $total_items = $this->get_total_items();

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page,
        ));

        $this->items = $data;

        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);
    }

    public function wp_list_table_data($per_page, $page_number)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'event_db';
        $orderby = !empty($_GET['orderby']) ? esc_sql($_GET['orderby']) : 'id';
        $order = (!empty($_GET['order']) && $_GET['order'] === 'asc') ? 'ASC' : 'DESC';

        $where = array();
        $values = array();

        if (!empty($_GET['s'])) {
            $like = '%' . $wpdb->esc_like($_GET['s']) . '%';
            $where[] = "(ip_address LIKE %s OR event_type LIKE %s OR object_type LIKE %s OR message LIKE %s)";
            array_push($values, $like, $like, $like, $like);
        }

        // if (!empty($_GET['from_date'])) {
        //     $where[] = "DATE(event_time) >= %s";
        //     $values[] = $_GET['from_date'];
        // }

        // if (!empty($_GET['to_date'])) {
        //     $where[] = "DATE(event_time) <= %s";
        //     $values[] = $_GET['to_date'];
        // }

        // if (!empty($_GET['filter_user'])) {
        //     $where[] = "userid = %d";
        //     $values[] = absint($_GET['filter_user']);
        // }

        // if (!empty($_GET['filter_role'])) {
        //     $role_users = get_users([
        //         'role'   => sanitize_text_field($_GET['filter_role']),
        //         'fields' => 'ID'
        //     ]);

        //     if (!empty($role_users)) {
        //         $placeholders = implode(',', array_fill(0, count($role_users), '%d'));
        //         $where[] = "userid IN ($placeholders)";
        //         $values = array_merge($values, $role_users);
        //     } else {
        //         $where[] = "1=0";
        //     }
        // }

        if (!empty($_GET['from_date'])) {
            $where[] = "DATE(event_time) >= %s";
            $values[] = $_GET['from_date'];
        }

        if (!empty($_GET['to_date'])) {
            $where[] = "DATE(event_time) <= %s";
            $values[] = $_GET['to_date'];
        }

        if (!empty($_GET['filter_user'])) {
            $where[] = "userid = %d";
            $values[] = absint($_GET['filter_user']);
        }

        if (!empty($_GET['filter_role'])){
            $role_user_ids = get_users([
                'role'   => sanitize_text_field($_GET['filter_role']),
                'fields' => 'ID'
            ]);
            if (!empty($role_user_ids)) {
                $placeholders = implode(',', array_fill(0, count($role_user_ids), '%d'));
                $where[] = "userid IN ($placeholders)";
                $values  = array_merge($values, $role_user_ids);
            } else {
                $where[] = "1 = 0";
            }
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($page_number - 1) * $per_page;
        $sql = "SELECT * FROM $table
            $where_sql
            ORDER BY $orderby $order
            LIMIT %d OFFSET %d";

        $values[] = $per_page;
        $values[] = $offset;

        return $wpdb->get_results(
            $wpdb->prepare($sql, $values),
            ARRAY_A
        );
    }


    public function get_hidden_columns()
    {
        return array('id');
    }

    public function get_columns()
    {
        return array(
            'cb' => '<input type="checkbox" />',
            'userid' => 'User',
            'ip_address' => 'IP Address',
            'event_time' => 'Date',
            'severity' => 'Severity',
            'event_type' => 'Event Type',
            'object_type' => 'Object Type',
            'message' => 'Message',
        );
    }

    public function column_userid($item)
    {
        if (empty($item['userid'])) {
            return '<em>Guest</em>';
        }

        $user_id = absint($item['userid']);
        $user = get_userdata($user_id);

        if (!$user) {
            return '<em>User Deleted</em>';
        }

        $roles = !empty($user->roles)
            ? implode(', ', array_map('ucfirst', $user->roles))
            : '—';

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

    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%d" />',
            absint($item['id'])
        );
    }

    protected function get_bulk_actions() {
        $actions = array(
            'delete' => __('Delete', 'log_manager'),
        );
        return $actions;
    }

    public function process_bulk_action()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'event_db';

        if ($this->current_action() !== 'delete') {
            return;
        }
        if (empty($_REQUEST['id']) || !is_array($_REQUEST['id'])) {
            return;
        }

        $ids = array_map('absint', $_REQUEST['id']);
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
    }


    public function column_default($item, $column_name)
    {
        if ($column_name === 'message') {
            $full_message = $item['message'];
            $short = mb_substr($full_message, 0, 20);
            if (mb_strlen($full_message) <= 20) {
                return $short;
            }

            return sprintf(
                '<span class="bxft-short">%s...</span>
                <span class="bxft-full" style="display:none;">%s</span>
                <a href="#" class="bxft-read-more">Read more</a>',
                $short,
                $full_message
            );
        }

        return $item[$column_name] ?? '—';
    }

    /** date wise filter functionality */
    public function extra_tablenav($which)
    {
        if ($which !== 'top') {
        return;
    }

    $from  = esc_attr($_GET['from_date'] ?? '');
    $to    = esc_attr($_GET['to_date'] ?? '');
    $role  = esc_attr($_GET['filter_role'] ?? '');
    $user  = absint($_GET['filter_user'] ?? 0);

    $roles = wp_roles()->roles;
    $users = get_users(['orderby' => 'display_name']);
    ?>
    <div class="alignleft actions">

        <!-- From Date -->
        <input type="date" name="from_date" value="<?php echo $from; ?>" />

        <!-- To Date -->
        <input type="date" name="to_date" value="<?php echo $to; ?>" />

        <!-- Role Filter -->
        <select name="filter_role">
            <option value="">All Roles</option>
            <?php foreach ($roles as $key => $r) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($role, $key); ?>>
                    <?php echo esc_html($r['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <!-- User Filter -->
        <select name="filter_user">
            <option value="">All Users</option>
            <?php foreach ($users as $u) : ?>
                <option value="<?php echo $u->ID; ?>" <?php selected($user, $u->ID); ?>>
                    <?php echo esc_html($u->display_name); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php submit_button(__('Filter'), '', 'filter_action', false); ?>
    </div>
    <?php
    }
}

function display_bxft_table()
{
    $bxft_table = new BXFT_Table();
    $bxft_table->prepare_items();
    
    if ( isset($_GET['action'], $_GET['id']) &&  $_GET['action'] === 'delete' &&  is_array($_GET['id']) && !empty($_GET['id'])) {
    $deleted_count = count($_GET['id']);
    echo '<div class="notice notice-success is-dismissible">';
    echo '<p>' . sprintf(_n('%d log deleted successfully.', '%d logs deleted successfully.', $deleted_count), $deleted_count) . '</p>';
    echo '</div>';
    }
    ?>
    <style>
        .firstSpan {
            color: rgb(119, 162, 241)
        }

        .firstSpan .secondSpan {
            visibility: hidden;
            width: 500px;
            background-color: gray;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 0;
            position: absolute;
            z-index: 1;
        }

        .firstSpan:hover .secondSpan {
            visibility: visible;
        }
    </style>
    <div class="wrap">
        <h1 class="wp-heading-inline">Logs Dashboard</h1>
        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php
            $bxft_table->search_box('Search Logs', 'bxft-search');
            $bxft_table->display();
            ?>
        </form>
    </div>
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
    <?php
}

display_bxft_table();