# Log Manager

**Log Manager** is a WordPress plugin that monitors, records, and displays activity logs across a WordPress website. It enables administrators to track actions performed by users and system processes, providing clear visibility into content changes, authentication events, and overall site activity.

The plugin is designed to help site owners, administrators, and developers audit user behavior, enhance security monitoring, and maintain a reliable history of important system events.

---

## Overview

Log Manager captures and stores detailed logs related to user actions and system-level events within WordPress. All logs are accessible through an intuitive administrative interface, where they can be filtered, searched, and reviewed efficiently.

The plugin supports logging across:
- Default post types
- Custom post types
- Pages
- User authentication workflows

---

## Use Cases

- Monitor user activity on posts, pages, and custom post types
- Track login, logout, and failed authentication attempts
- Audit content changes made by specific users or roles
- Review site activity for security and compliance purposes
- Maintain a historical record of administrative and user actions

---

## Features

### Activity Tracking

#### Posts, Pages & Custom Post Types
Tracks the following actions:
- Publish
- Update
- Restore
- Move to Trash
- Permanent Delete

All default and custom post types are supported automatically.

#### Authentication Activity
- Successful user login
- Failed login attempts
- User logout activity
- Logs both authenticated and anonymous actions where applicable

---

### Log Management

#### Pagination & Record Count
- Logs are displayed using pagination for improved performance
- Total number of log records is shown for easy reference
- Configurable records-per-page option

#### Severity Levels
Each log entry is categorized by severity:
- **Low** – Informational or routine actions
- **Medium** – Important changes requiring attention
- **High** – Critical or security-related actions

Logs can be filtered and sorted by severity level.

---

### Filtering & Search

- Filter logs by:
  - Custom date range
  - Specific users
  - User roles
  - Severity level
- Combine multiple filters (e.g., user + role + date range)
- Search logs using keyword-based queries

---

### Log Export

- Export log records in the following formats:
  - CSV
  - PDF
- Useful for audits, reports, and external analysis

---

### Housekeeping & Data Retention

- Scheduled cleanup using WordPress CRON
- Automatically removes outdated or unnecessary log data
- Helps maintain optimal database performance

---

## Installation

### Manual Installation

1. Download the plugin folder named `log-manager`
2. Upload the folder to `/wp-content/plugins/`
3. Activate the plugin from the WordPress Admin Dashboard

---

## Requirements

- WordPress version 6.0 or higher
- PHP version 7.2 or higher
- MySQL version 5.6 or higher

---

## Intended Audience

- WordPress site administrators
- Developers managing content-driven websites
- Teams requiring activity auditing and monitoring
- Security-conscious website owners
