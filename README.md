# SyncForge Config Manager

**Contributors:** victorjimenezdev
**Tags:** configuration, sync, export, import, deployment
**Requires at least:** WordPress 6.4
**Tested up to:** 6.9
**Requires PHP:** 8.0
**Stable tag:** 1.0.0
**License:** GPLv2 or later

Export, import, and sync WordPress site configuration as YAML files across environments.

## Description

SyncForge Config Manager provides full configuration management for WordPress, allowing you to export your site configuration as YAML files and deploy changes across environments with confidence.

### Key Features

- **Export and import configuration as YAML** — Store your site settings in version-controlled YAML files for consistent deployments.
- **Per-plugin YAML files** — Options are automatically grouped by plugin (e.g. `options/wpseo.yml`, `options/wp-rocket.yml`) for clean, organized exports.
- **Plugin manifest** — Exports a `plugins.yml` listing all installed plugins with their active/inactive status and version.
- **ZIP export and import** — Download all configuration as a ZIP archive or upload a ZIP to import configuration from another environment.
- **Heuristic option discovery** — Automatically classifies database options as configuration vs. runtime state using keyword/suffix/prefix heuristics, without hardcoding plugin-specific exclusion lists.
- **Environment overrides** — Define environment-specific settings that automatically apply per environment without modifying the base configuration.
- **WP-CLI support** — Full command-line interface for export, import, diff, discover, and status operations.
- **REST API** — Programmatic access to all configuration management operations via authenticated REST endpoints.
- **Rollback and snapshots** — Create point-in-time snapshots of your configuration and roll back to any previous state.
- **7 built-in providers** — Out-of-the-box support for options, roles, nav menus, widgets, theme mods, rewrite rules, and block patterns.
- **Diff and preview** — Review pending configuration changes before applying them to your site.
- **Admin dashboard** — Visual interface under Tools > SyncForge for export, import, diff preview, and ZIP transfer.

SyncForge Config Manager is designed for teams and agencies that manage multiple WordPress environments and need a reliable, repeatable deployment workflow for site configuration.

## Installation

1. Upload the `syncforge-config-manager` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Tools > SyncForge** to begin managing your configuration.
4. Optionally configure settings under **Settings > SyncForge Config Manager**.

**Requirements:**

- WordPress 6.4 or later
- PHP 8.0 or later
- PHP ZipArchive extension (for ZIP export/import)

## Frequently Asked Questions

### What types of configuration can I sync?

SyncForge Config Manager supports 7 providers out of the box: options, roles, navigation menus, widgets, theme mods, rewrite rules, and block patterns. You can also register custom providers via the `config_sync_providers` filter.

### How are options organized in the export?

Options are automatically grouped by the plugin that owns them. For example, Yoast SEO options go into `options/wpseo.yml`, WP Rocket settings into `options/wp-rocket.yml`, and so on. Options that do not match any installed plugin are grouped into `options/misc.yml`.

### How does option discovery work?

Instead of hardcoding plugin-specific exclusion lists, SyncForge uses heuristic classification. Options containing keywords like `_version`, `_nonce`, `_count`, `_migration` are classified as runtime state and excluded. Options containing `settings`, `options`, `config`, or `_key` are always kept as configuration. All heuristic lists are filterable.

### Is it safe to use in production?

Yes. SyncForge Config Manager includes snapshot and rollback functionality, audit logging, and a diff preview so you can review changes before applying them. Always test imports in a staging environment first.

### Does it work with WP-CLI?

Yes. Available commands: `wp syncforge export`, `wp syncforge import`, `wp syncforge diff`, `wp syncforge discover`, and `wp syncforge status`.

### Can I export and import via ZIP?

Yes. The admin dashboard under Tools > SyncForge has "Download ZIP" and "Upload ZIP" buttons. You can download all YAML configuration files as a ZIP archive from one environment and upload it to another.

### Can I use environment-specific overrides?

Yes. You can define per-environment configuration overrides that are automatically applied based on the current environment, without modifying the base configuration files.

### Where is the configuration stored?

Exported configuration is stored as YAML files in a configurable directory within `wp-content` (default: `wp-content/config-sync/`). The directory is protected with `.htaccess` deny rules. This makes it easy to commit configuration to version control.

## Changelog

### 1.0.0

- Initial release.
- Export and import configuration as YAML files.
- 7 built-in configuration providers.
- Environment override support.
- WP-CLI commands for export, import, diff, discover, and status.
- REST API endpoints for all operations.
- Snapshot and rollback functionality.
- Audit logging for all configuration changes.
- Per-plugin YAML file grouping for options export.
- Plugin manifest with active/inactive status and versions.
- Admin dashboard under Tools > SyncForge.
- ZIP export and import.
- Heuristic-based option classification.
