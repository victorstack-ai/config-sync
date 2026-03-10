<?php
/**
 * WP-CLI export command for Config Sync.
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
 * Class ExportCommand
 *
 * Exports configuration from the database to YAML files.
 *
 * ## EXAMPLES
 *
 *     # Export all providers
 *     $ wp syncforge export
 *
 *     # Export a single provider
 *     $ wp syncforge export --provider=options
 *
 * @since 1.0.0
 */
class ExportCommand {

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
	 * Export configuration to YAML files.
	 *
	 * ## OPTIONS
	 *
	 * [--provider=<id>]
	 * : Export only the specified provider. Omit to export all providers.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export all providers
	 *     $ wp syncforge export
	 *     Success: Exported 3 providers.
	 *
	 *     # Export a single provider
	 *     $ wp syncforge export --provider=options
	 *     Success: Exported provider "options".
	 *
	 * @synopsis [--provider=<id>]
	 *
	 * @since 1.0.0
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$config_manager = $this->container->get_config_manager();
		$provider_id    = Utils\get_flag_value( $assoc_args, 'provider', '' );

		if ( '' !== $provider_id ) {
			$this->export_single( $config_manager, $provider_id );
			return;
		}

		$this->export_all( $config_manager );
	}

	/**
	 * Export all registered providers.
	 *
	 * @since 1.0.0
	 *
	 * @param \ConfigSync\ConfigManager $config_manager Config manager instance.
	 * @return void
	 */
	private function export_all( \ConfigSync\ConfigManager $config_manager ): void {
		WP_CLI::log( __( 'Exporting all providers...', 'syncforge-config-manager' ) );

		$result = $config_manager->export_all();

		if ( ! empty( $result['errors'] ) ) {
			foreach ( $result['errors'] as $id => $error ) {
				WP_CLI::warning(
					/* translators: 1: provider ID, 2: error message */
					sprintf( __( 'Provider "%1$s" failed: %2$s', 'syncforge-config-manager' ), $id, $error )
				);
			}
		}

		if ( empty( $result['providers'] ) ) {
			WP_CLI::warning( __( 'No providers were exported.', 'syncforge-config-manager' ) );
			return;
		}

		$table_data = array();

		foreach ( $result['providers'] as $id => $stats ) {
			$table_data[] = array(
				'provider' => $id,
				'items'    => isset( $stats['items'] ) ? $stats['items'] : 0,
				'files'    => isset( $stats['files'] ) ? $stats['files'] : 0,
			);
		}

		Utils\format_items( 'table', $table_data, array( 'provider', 'items', 'files' ) );

		WP_CLI::success(
			sprintf(
				/* translators: %d: number of providers exported */
				_n( 'Exported %d provider.', 'Exported %d providers.', count( $result['providers'] ), 'syncforge-config-manager' ),
				count( $result['providers'] )
			)
		);
	}

	/**
	 * Export a single provider by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param \ConfigSync\ConfigManager $config_manager Config manager instance.
	 * @param string                    $provider_id    Provider identifier.
	 * @return void
	 */
	private function export_single( \ConfigSync\ConfigManager $config_manager, string $provider_id ): void {
		WP_CLI::log(
			/* translators: %s: provider ID */
			sprintf( __( 'Exporting provider "%s"...', 'syncforge-config-manager' ), $provider_id )
		);

		$result = $config_manager->export_provider( $provider_id );

		if ( ! empty( $result['errors'] ) ) {
			WP_CLI::error(
				sprintf(
					/* translators: 1: provider ID, 2: error message */
					__( 'Failed to export provider "%1$s": %2$s', 'syncforge-config-manager' ),
					$provider_id,
					implode( '; ', $result['errors'] )
				)
			);
		}

		if ( isset( $result['providers'][ $provider_id ] ) ) {
			$stats      = $result['providers'][ $provider_id ];
			$table_data = array(
				array(
					'provider' => $provider_id,
					'items'    => isset( $stats['items'] ) ? $stats['items'] : 0,
					'files'    => isset( $stats['files'] ) ? $stats['files'] : 0,
				),
			);

			Utils\format_items( 'table', $table_data, array( 'provider', 'items', 'files' ) );
		}

		WP_CLI::success(
			/* translators: %s: provider ID */
			sprintf( __( 'Exported provider "%s".', 'syncforge-config-manager' ), $provider_id )
		);
	}
}
