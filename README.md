## Wordpress hooks table info.

| Category              | Hook Name                       | When It Fires             | What to Log                       |
| --------------------- | ------------------------------- | ------------------------- | --------------------------------- |
| **Login**             | `wp_login`                      | User successfully logs in | user_id, username, IP, user_agent |
| **Login Fail**        | `wp_login_failed`               | Invalid login attempt     | username, IP, user_agent          |
| **Logout**            | `wp_logout`                     | User logs out             | user_id, IP                       |
| **Auth Check**        | `authenticate`                  | During login validation   | username, success/fail            |
| **User Register**     | `user_register`                 | New user created          | user_id, role, creator            |
| **Password Reset**    | `password_reset`                | Password reset completed  | user_id, IP                       |
| **Profile Update**    | `profile_update`                | User profile updated      | user_id, changed_fields           |
| **Role Change**       | `set_user_role`                 | User role changed         | user_id, old_role, new_role       |
| **Post Save**         | `save_post`                     | Post/page/CPT saved       | post_id, post_type, status        |
| **Post Insert**       | `wp_insert_post`                | Post created or updated   | post_id, user_id                  |
| **Status Change**     | `transition_post_status`        | Post status changed       | post_id, old_status, new_status   |
| **Post Delete**       | `before_delete_post`            | Post deleted permanently  | post_id, post_type                |
| **Post Trash**        | `trashed_post`                  | Post moved to trash       | post_id                           |
| **Media Upload**      | `add_attachment`                | Media file uploaded       | attachment_id, file_name          |
| **Media Delete**      | `delete_attachment`             | Media file deleted        | attachment_id                     |
| **Comment Add**       | `comment_post`                  | New comment added         | comment_id, post_id, IP           |
| **Comment Edit**      | `edit_comment`                  | Comment edited            | comment_id                        |
| **Comment Status**    | `wp_set_comment_status`         | Comment approved/spam     | comment_id, status                |
| **Plugin Activate**   | `activated_plugin`              | Plugin activated          | plugin_name, user_id              |
| **Plugin Deactivate** | `deactivated_plugin`            | Plugin deactivated        | plugin_name                       |
| **Plugin Delete**     | `deleted_plugin`                | Plugin deleted            | plugin_name                       |
| **Theme Switch**      | `switch_theme`                  | Theme changed             | old_theme, new_theme              |
| **Theme Delete**      | `delete_theme`                  | Theme deleted             | theme_name                        |
| **Update Complete**   | `upgrader_process_complete`     | Plugin/theme/core updated | type, name, version               |
| **Auto Update**       | `automatic_updates_complete`    | Auto updates finished     | updated_items                     |
| **Option Update**     | `update_option`                 | Site setting changed      | option_name, old, new             |
| **Option Add**        | `added_option`                  | New option added          | option_name                       |
| **Option Delete**     | `deleted_option`                | Option removed            | option_name                       |
| **REST Request**      | `rest_request_before_callbacks` | REST API called           | route, method, user               |
| **AJAX Auth**         | `wp_ajax_*`                     | Authenticated AJAX        | action, user_id                   |
| **AJAX Public**       | `wp_ajax_nopriv_*`              | Public AJAX               | action, IP                        |
| **XML-RPC**           | `xmlrpc_call`                   | XML-RPC request           | method, IP                        |
| **Fatal Error**       | `shutdown`                      | Script ends (fatal error) | error_message, file, line         |
| **Die Event**         | `wp_die`                        | Forced termination        | message, user                     |
| **Admin Load**        | `admin_init`                    | Admin dashboard load      | user_id, screen                   |
| **Front Load**        | `init`                          | WordPress initializes     | request_type                      |




## different warning level
There have multiple diferent warning level that we can integrate. error, bug, notice, published, modified, trashed, deleted, Login, Login Failed, Logout, 