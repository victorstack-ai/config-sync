<?php
/**
 * SyncForge Config Manager Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Drop custom database tables.
 */
global $wpdb;

$config_sync_tables = array(
	$wpdb->prefix . 'config_sync_id_map',
	$wpdb->prefix . 'config_sync_audit_log',
);

foreach ( $config_sync_tables as $config_sync_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare( 'DROP TABLE IF EXISTS %i', $config_sync_table )
	);
}

/**
 * Delete plugin options.
 */
$config_sync_options = array(
	'config_sync_settings',
	'config_sync_lock',
	'config_sync_db_version',
	'config_sync_block_patterns',
	'config_sync_discovered_options',
);

foreach ( $config_sync_options as $config_sync_option ) {
	delete_option( $config_sync_option );
}

/**
 * Remove custom capability from all roles.
 */
$config_sync_roles = wp_roles();

foreach ( $config_sync_roles->role_objects as $config_sync_role ) {
	$config_sync_role->remove_cap( 'manage_config_sync' );
}

/**
 * Clean up transients with config_sync_ prefix.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$config_sync_transient_keys = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_config_sync_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_config_sync_' ) . '%'
	)
);

foreach ( $config_sync_transient_keys as $config_sync_key ) {
	// Strip the _transient_ prefix to get the transient name.
	if ( str_starts_with( $config_sync_key, '_transient_timeout_' ) ) {
		continue;
	}

	$config_sync_transient_name = substr( $config_sync_key, strlen( '_transient_' ) );
	delete_transient( $config_sync_transient_name );
}
