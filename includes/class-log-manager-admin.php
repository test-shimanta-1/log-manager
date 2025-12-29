<?php
/**
 * Plugin Admin Class File
 * 
 * 
 * @since 1.0.0
 * @package Log_Manager
 */

class Log_Manager_Admin
{

  /**
   * Constructor
   *  
   * responsible to register plugin's menu pages.
   * 
   * @return void
   */
  public function __construct()
  {
    add_action('admin_menu', [$this, 'register_menu_submenu_pages']);
    register_activation_hook(LOG_MANAGER_FILE, ['Log_Manager_DB', 'init_db']); // initializing plugin table
  }

  /**
   * registering menu and sub-menu pages for plugin
   * 
   * @return void
   */
  public function register_menu_submenu_pages()
  {
    // Parent menu
    add_menu_page(
      __('Log Manager', 'log-manager'),
      __('Log Manager', 'log-manager'),
      'manage_options',
      'log-manager',
      ['Log_Manager_Dashboard', 'sdw_dashboard_render'],
      'dashicons-tide'
    );

    // Dashboard submenu
    add_submenu_page(
      'log-manager',
      __('Dashboard', 'log-manager'),
      __('Dashboard', 'log-manager'),
      'manage_options',
      'log-manager',
      ['Log_Manager_Dashboard', 'sdw_dashboard_render']
    );

  }

}