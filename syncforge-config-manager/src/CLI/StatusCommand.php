<?php
/**
 * WP-CLI status command for Config Sync.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_CLI;
use WP_CLI\Utils;

/**
 * Class StatusCommand
 *
 * Displays the current status of Config Sync: providers, environment,
 * lock state, and recent operations.
 *
 * ## EXAMPLES
 *
 *     $ wp syncforge status
 *
 * @since 1.0.0
 */
class StatusCommand {

	/**
	 * The plugin container instance.
	 *
	 * @since 1.0.0
	 * @var \ConfigSync\Container
	 */
	private \ConfigSync\Container $container;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param \ConfigSync\Container $container Plugin service container.
	 */
	public function __construct( \ConfigSync\Container $container ) {
		$this->container = $container;
	}

	/**
	 * Show Config Sync status overview.
	 *
	 * Displays registered providers, current environment, lock status,
	 * and recent export/import timestamps from the audit log.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp syncforge status
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments (unused).
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$this->show_environment();
		$this->show_lock_status();
		$this->show_providers();
		$this->show_recent_operations();
	}

	/**
	 * Display the current environment.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function show_environment(): void {
		$environment_override = $this->container->get_environment_override();
		$environment          = $environment_override->get_environment();

		WP_CLI::log(
			/* translators: %s: current environment name */
			sprintf( __( 'Environment: %s', 'syncforge-config-manager' ), $environment )
		);
		WP_CLI::log( '' );
	}

	/**
	 * Display the current lock status.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function show_lock_status(): void {
		$lock = $this->container->get_lock();

		if ( $lock->is_locked() ) {
			$info = $lock->get_lock_info();
			WP_CLI::warning(
				sprintf(
					/* translators: 1: operation name, 2: user ID, 3: timestamp */
					__( 'Lock: ACTIVE (operation: %1$s, user: %2$d, since: %3$s)', 'syncforge-config-manager' ),
					isset( $info['operation'] ) ? $info['operation'] : __( 'unknown', 'syncforge-config-manager' ),
					isset( $info['user_id'] ) ? $info['user_id'] : 0,
					isset( $info['time'] ) ? gmdate( 'Y-m-d H:i:s', $info['time'] ) : __( 'unknown', 'syncforge-config-manager' )
				)
			);
		} else {
			WP_CLI::log( __( 'Lock: not active', 'syncforge-config-manager' ) );
		}

		WP_CLI::log( '' );
	}

	/**
	 * Display registered providers and their status.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function show_providers(): void {
		$providers = $this->container->get_providers();

		if ( empty( $providers ) ) {
			WP_CLI::warning( __( 'No providers registered.', 'syncforge-config-manager' ) );
			return;
		}

		WP_CLI::log( __( 'Registered Providers:', 'syncforge-config-manager' ) );

		$table_data = array();

		foreach ( $providers as $provider ) {
			$dependencies = $provider->get_dependencies();

			$table_data[] = array(
				'id'           => $provider->get_id(),
				'label'        => $provider->get_label(),
				'files'        => implode( ', ', $provider->get_config_files() ),
				'dependencies' => ! empty( $dependencies ) ? implode( ', ', $dependencies ) : __( 'none', 'syncforge-config-manager' ),
				'batch_size'   => $provider->get_batch_size(),
			);
		}

		Utils\format_items( 'table', $table_data, array( 'id', 'label', 'files', 'dependencies', 'batch_size' ) );

		WP_CLI::log( '' );
	}

	/**
	 * Display recent export/import operations from the audit log.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function show_recent_operations(): void {
		$audit_logger = $this->container->get_audit_logger();
		$snapshots    = $audit_logger->list_snapshots( 5 );

		if ( empty( $snapshots ) ) {
			WP_CLI::log( __( 'No recent operations found in the audit log.', 'syncforge-config-manager' ) );
			return;
		}

		WP_CLI::log( __( 'Recent Operations:', 'syncforge-config-manager' ) );

		$table_data = array();

		foreach ( $snapshots as $entry ) {
			$table_data[] = array(
				'id'          => $entry['id'],
				'action'      => $entry['action'],
				'provider'    => $entry['provider'],
				'environment' => $entry['environment'],
				'summary'     => $entry['summary'],
				'date'        => $entry['created_at'],
			);
		}

		Utils\format_items( 'table', $table_data, array( 'id', 'action', 'provider', 'environment', 'summary', 'date' ) );
	}
}
