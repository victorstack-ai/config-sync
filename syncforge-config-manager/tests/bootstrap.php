<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$config_sync_wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $config_sync_wp_tests_dir ) {
	$config_sync_wp_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give access to tests_add_filter() function.
require_once $config_sync_wp_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
tests_add_filter(
	'muplugins_loaded',
	function () {
		require dirname( __DIR__ ) . '/syncforge-config-manager.php';
	}
);

// Start up the WP testing environment.
require $config_sync_wp_tests_dir . '/includes/bootstrap.php';
