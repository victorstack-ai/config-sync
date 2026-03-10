<?php
/**
 * WP-CLI import command for Config Sync.
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
 * Class ImportCommand
 *
 * Imports configuration from YAML files into the database.
 *
 * ## EXAMPLES
 *
 *     # Import all providers
 *     $ wp syncforge import
 *
 *     # Dry-run import for a single provider
 *     $ wp syncforge import --provider=options --dry-run
 *
 *     # Import without confirmation prompt
 *     $ wp syncforge import --yes
 *
 * @since 1.0.0
 */
class ImportCommand {

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
	 * Import configuration from YAML files.
	 *
	 * ## OPTIONS
	 *
	 * [--provider=<id>]
	 * : Import only the specified provider. Omit to import all providers.
	 *
	 * [--dry-run]
	 * : Show what would change without applying any modifications.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import all providers
	 *     $ wp syncforge import --yes
	 *     Success: Imported 3 providers.
	 *
	 *     # Dry-run a single provider
	 *     $ wp syncforge import --provider=options --dry-run
	 *
	 * @synopsis [--provider=<id>] [--dry-run] [--yes]
	 *
	 * @since 1.0.0
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$config_manager = $this->container->get_config_manager();
		$diff_engine    = $this->container->get_diff_engine();
		$provider_id    = Utils\get_flag_value( $assoc_args, 'provider', '' );
		$dry_run        = Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$skip_confirm   = Utils\get_flag_value( $assoc_args, 'yes', false );

		// Show diff before importing.
		if ( ! $dry_run && ! $skip_confirm ) {
			$this->show_diff_and_confirm( $config_manager, $diff_engine, $provider_id );
		}

		if ( $dry_run ) {
			$this->run_dry_run( $config_manager, $diff_engine, $provider_id );
			return;
		}

		if ( '' !== $provider_id ) {
			$this->import_single( $config_manager, $provider_id );
			return;
		}

		$this->import_all( $config_manager );
	}

	/**
	 * Show the diff and ask for user confirmation before importing.
	 *
	 * @since 1.0.0
	 *
	 * @param \ConfigSync\ConfigManager $config_manager Config manager instance.
	 * @param \ConfigSync\DiffEngine    $diff_engine    Diff engine instance.
	 * @param string                    $provider_id    Optional provider ID filter.
	 * @return void
	 */
	private function show_diff_and_confirm(
		\ConfigSync\ConfigManager $config_manager,
		\ConfigSync\DiffEngine $diff_engine,
		string $provider_id
	): void {
		$diff_data = $config_manager->diff( $provider_id ?: null );

		$has_changes = false;

		foreach ( $diff_data as $id => $items ) {
			if ( ! empty( $items ) ) {
				$has_changes = true;
				WP_CLI::log(
					/* translators: %s: provider ID */
					sprintf( __( '--- Provider: %s ---', 'syncforge-config-manager' ), $id )
				);
				WP_CLI::log( WP_CLI::colorize( $diff_engine->format_for_cli( $items ) ) );
				WP_CLI::log( '' );
			}
		}

		if ( ! $has_changes ) {
			WP_CLI::success( __( 'No changes to import. Database is in sync.', 'syncforge-config-manager' ) );
			exit( 0 );
		}

		WP_CLI::confirm( __( 'Apply these changes?', 'syncforge-config-manager' ) );
	}

	/**
	 * Perform a dry-run import and display what would change.
	 *
	 * @since 1.0.0
	 *
	 * @param \ConfigSync\ConfigManager $config_manager Config manager instance.
	 * @param \ConfigSync\DiffEngine    $diff_engine    Diff engine instance.
	 * @param string                    $provider_id    Optional provider ID filter.
	 * @return void
	 */
	private function run_dry_run(
		\ConfigSync\ConfigManager $config_manager,
		\ConfigSync\DiffEngine $diff_engine,
		string $provider_id
	): void {
		WP_CLI::log( __( 'Performing dry-run...', 'syncforge-config-manager' ) );

		if ( '' !== $provider_id ) {
			$result = $config_manager->import_provider( $provider_id, true );
		} else {
			$result = $config_manager->import_all( true );
		}

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		if ( ! empty( $result['errors'] ) ) {
			foreach ( $result['errors'] as $id => $error ) {
				WP_CLI::warning(
					/* translators: 1: provider ID, 2: error message */
					sprintf( __( 'Provider "%1$s" failed: %2$s', 'syncforge-config-manager' ), $id, $error )
				);
			}
		}

		$diff_data = $config_manager->diff( $provider_id ?: null );

		$has_changes = false;

		foreach ( $diff_data as $id => $items ) {
			if ( ! empty( $items ) ) {
				$has_changes = true;
				WP_CLI::log(
					/* translators: %s: provider ID */
					sprintf( __( '--- Provider: %s ---', 'syncforge-config-manager' ), $id )
				);
				WP_CLI::log( WP_CLI::colorize( $diff_engine->format_for_cli( $items ) ) );
				WP_CLI::log( '' );
			}
		}

		if ( ! $has_changes ) {
			WP_CLI::success( __( 'No changes detected. Database is in sync.', 'syncforge-config-manager' ) );
			return;
		}

		WP_CLI::success( __( 'Dry-run complete. No changes were applied.', 'syncforge-config-manager' ) );
	}

	/**
	 * Import all registered providers.
	 *
	 * @since 1.0.0
	 *
	 * @param \ConfigSync\ConfigManager $config_manager Config manager instance.
	 * @return void
	 */
	private function import_all( \ConfigSync\ConfigManager $config_manager ): void {
		WP_CLI::log( __( 'Importing all providers...', 'syncforge-config-manager' ) );

		$result = $config_manager->import_all();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$this->display_import_results( $result );
	}

	/**
	 * Import a single provider by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param \ConfigSync\ConfigManager $config_manager Config manager instance.
	 * @param string                    $provider_id    Provider identifier.
	 * @return void
	 */
	private function import_single( \ConfigSync\ConfigManager $config_manager, string $provider_id ): void {
		WP_CLI::log(
			/* translators: %s: provider ID */
			sprintf( __( 'Importing provider "%s"...', 'syncforge-config-manager' ), $provider_id )
		);

		$result = $config_manager->import_provider( $provider_id );

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		$this->display_import_results( $result );
	}

	/**
	 * Display import results as a table.
	 *
	 * @since 1.0.0
	 *
	 * @param array $result Import result array from ConfigManager.
	 * @return void
	 */
	private function display_import_results( array $result ): void {
		if ( ! empty( $result['errors'] ) ) {
			foreach ( $result['errors'] as $id => $error ) {
				WP_CLI::warning(
					/* translators: 1: provider ID, 2: error message */
					sprintf( __( 'Provider "%1$s" failed: %2$s', 'syncforge-config-manager' ), $id, $error )
				);
			}
		}

		if ( empty( $result['providers'] ) ) {
			WP_CLI::warning( __( 'No providers were imported.', 'syncforge-config-manager' ) );
			return;
		}

		$table_data = array();

		foreach ( $result['providers'] as $id => $stats ) {
			$table_data[] = array(
				'provider' => $id,
				'created'  => isset( $stats['created'] ) ? $stats['created'] : 0,
				'updated'  => isset( $stats['updated'] ) ? $stats['updated'] : 0,
				'deleted'  => isset( $stats['deleted'] ) ? $stats['deleted'] : 0,
			);
		}

		Utils\format_items( 'table', $table_data, array( 'provider', 'created', 'updated', 'deleted' ) );

		WP_CLI::success(
			sprintf(
				/* translators: %d: number of providers imported */
				_n( 'Imported %d provider.', 'Imported %d providers.', count( $result['providers'] ), 'syncforge-config-manager' ),
				count( $result['providers'] )
			)
		);
	}
}
