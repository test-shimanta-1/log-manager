<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/template.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
    require_once ABSPATH . 'wp-admin/includes/screen.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BXFT_Table extends WP_List_Table {

    public function prepare_items() {
        $data         = $this->wp_list_table_data();
        $per_page     = 5;
        $current_page = $this->get_pagenum();
        $total_items  = count( $data );
        $this->set_pagination_args(
            array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
            )
        );

        $this->items           = array_slice(
            $data,
            ( ( $current_page - 1 ) * $per_page ),
            $per_page
        );
        $columns               = $this->get_columns();
        $hidden                = $this->get_hidden_columns();
        $this->_column_headers = array( $columns, $hidden );
    }

    public function wp_list_table_data() {
      global $wpdb;
      $table = $wpdb->prefix . 'event_db';
      return $wpdb->get_results(
          "SELECT * FROM $table ORDER BY id DESC",
          ARRAY_A
      );
    }

    public function get_hidden_columns() {
        return array( 'id' );
    }

    public function get_columns() {
      return array(
          'userid'        => 'User',
          'ip_address'    => 'IP Address',
          'event_time'    => 'Date',
          'warning_level' => 'Warning Level',
          'event_type'    => 'Event Type',
          'object_type'   => 'Object Type',
          'message'       => 'Message',
      );
    }

    public function column_userid( $item ) {

        $user_id   = absint( $item['userid'] );
        $user_info = get_userdata( $user_id );

        if ( ! $user_info ) {
            return '<em>Unknown User</em>';
        }

        $roles = implode( ', ', array_map( 'ucfirst', $user_info->roles ) );

        return sprintf(
            '<span class="firstSpan">
                %s
                <span class="secondSpan">
                    <b>Username:</b> %s<br>
                    <b>Email:</b> %s<br>
                    <b>Nickname:</b> %s
                </span>
            </span>',
            esc_html( $roles ),
            esc_html( $user_info->user_login ),
            esc_html( $user_info->user_email ),
            esc_html( $user_info->user_nicename )
        );
    }

    public function column_default( $item, $column_name ) {

        $allowed = array(
            'ip_address',
            'event_time',
            'warning_level',
            'event_type',
            'object_type',
            'message',
        );

        if ( in_array( $column_name, $allowed, true ) ) {
            return $item[ $column_name ];
        }

        return '—';
    }

}

function display_bxft_table() {
    $bxft_table = new BXFT_Table();
    $bxft_table->prepare_items();
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
        <?php 
        $bxft_table->display(); ?>
    </div>
    <?php
}

display_bxft_table();