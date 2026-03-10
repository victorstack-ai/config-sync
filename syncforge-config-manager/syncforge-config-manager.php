<?php
/**
 * Plugin Name: SyncForge Config Manager
 * Description: Export, import, and sync WordPress site configuration as YAML files across environments.
 * Version: 1.0.0
 * Requires PHP: 8.0
 * Requires at least: 6.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: syncforge-config-manager
 * Domain Path: /languages
 * Author: victorjimenezdev
 *
 * @package ConfigSync
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @since 1.0.0
 * @var string
 */
define( 'CONFIG_SYNC_VERSION', '1.0.0' );

/**
 * Database schema version.
 *
 * @since 1.0.0
 * @var string
 */
define( 'CONFIG_SYNC_DB_VERSION', '1.0.0' );

/**
 * Plugin main file path.
 *
 * @since 1.0.0
 * @var string
 */
define( 'CONFIG_SYNC_FILE', __FILE__ );

/**
 * Plugin directory path.
 *
 * @since 1.0.0
 * @var string
 */
define( 'CONFIG_SYNC_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 *
 * @since 1.0.0
 * @var string
 */
define( 'CONFIG_SYNC_URL', plugin_dir_url( __FILE__ ) );

/**
 * Require Composer autoloader.
 */
if ( file_exists( CONFIG_SYNC_DIR . 'vendor/autoload.php' ) ) {
	require_once CONFIG_SYNC_DIR . 'vendor/autoload.php';
}

/**
 * Return the plugin Container singleton.
 *
 * @since 1.0.0
 *
 * @return \ConfigSync\Container
 */
function config_sync(): \ConfigSync\Container {
	static $container = null;

	if ( null === $container ) {
		$container = new \ConfigSync\Container();
	}

	return $container;
}

/*
 * Activation and deactivation hooks.
 */
register_activation_hook( CONFIG_SYNC_FILE, array( \ConfigSync\Plugin::class, 'activate' ) );
register_deactivation_hook( CONFIG_SYNC_FILE, array( \ConfigSync\Plugin::class, 'deactivate' ) );

/*
 * Bootstrap the plugin on plugins_loaded.
 */
add_action(
	'plugins_loaded',
	static function () {
		$plugin = new \ConfigSync\Plugin( config_sync() );
		$plugin->init();
	}
);
