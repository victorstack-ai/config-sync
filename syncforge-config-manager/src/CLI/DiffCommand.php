<?php
/**
 * WP-CLI diff command for Config Sync.
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
 * Class DiffCommand
 *
 * Shows the differences between the database state and the YAML files.
 *
 * ## EXAMPLES
 *
 *     # Show diff for all providers
 *     $ wp syncforge diff
 *
 *     # Show diff for a single provider as JSON
 *     $ wp syncforge diff --provider=options --format=json
 *
 * @since 1.0.0
 */
class DiffCommand {

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
	 * Show configuration diff between database and files.
	 *
	 * ## OPTIONS
	 *
	 * [--provider=<id>]
	 * : Show diff for only the specified provider.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts: table, json, yaml. Default: table.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Show diff for all providers
	 *     $ wp syncforge diff
	 *
	 *     # Show diff for a single provider in JSON format
	 *     $ wp syncforge diff --provider=options --format=json
	 *
	 *     # Show diff in YAML format
	 *     $ wp syncforge diff --format=yaml
	 *
	 * @synopsis [--provider=<id>] [--format=<format>]
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
		$format         = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		$diff_data = $config_manager->diff( $provider_id ?: null );

		$has_changes = false;

		foreach ( $diff_data as $items ) {
			if ( ! empty( $items ) ) {
				$has_changes = true;
				break;
			}
		}

		if ( ! $has_changes ) {
			WP_CLI::success( __( 'No differences found. Database is in sync with files.', 'syncforge-config-manager' ) );
			return;
		}

		switch ( $format ) {
			case 'json':
				$this->output_json( $diff_data, $diff_engine );
				break;

			case 'yaml':
				$this->output_yaml( $diff_data, $diff_engine );
				break;

			default:
				$this->output_table( $diff_data, $diff_engine );
				break;
		}
	}

	/**
	 * Output diff as a CLI table with color codes.
	 *
	 * @since 1.0.0
	 *
	 * @param array                  $diff_data   Per-provider diff arrays.
	 * @param \ConfigSync\DiffEngine $diff_engine Diff engine instance.
	 * @return void
	 */
	private function output_table( array $diff_data, \ConfigSync\DiffEngine $diff_engine ): void {
		foreach ( $diff_data as $provider_id => $items ) {
			if ( empty( $items ) ) {
				continue;
			}

			// Check for error items.
			if ( isset( $items[0]['type'] ) && 'error' === $items[0]['type'] ) {
				WP_CLI::warning(
					sprintf(
						/* translators: 1: provider ID, 2: error message */
						__( 'Provider "%1$s": %2$s', 'syncforge-config-manager' ),
						$provider_id,
						isset( $items[0]['message'] ) ? $items[0]['message'] : __( 'Unknown error', 'syncforge-config-manager' )
					)
				);
				continue;
			}

			WP_CLI::log(
				/* translators: %s: provider ID */
				sprintf( __( '--- Provider: %s ---', 'syncforge-config-manager' ), $provider_id )
			);
			WP_CLI::log( WP_CLI::colorize( $diff_engine->format_for_cli( $items ) ) );

			$summary = $diff_engine->summarize( $items );
			WP_CLI::log(
				sprintf(
					/* translators: 1: added count, 2: modified count, 3: removed count */
					__( 'Summary: %1$d added, %2$d modified, %3$d removed', 'syncforge-config-manager' ),
					$summary['added'],
					$summary['modified'],
					$summary['removed']
				)
			);
			WP_CLI::log( '' );
		}
	}

	/**
	 * Output diff as JSON.
	 *
	 * @since 1.0.0
	 *
	 * @param array                  $diff_data   Per-provider diff arrays.
	 * @param \ConfigSync\DiffEngine $diff_engine Diff engine instance.
	 * @return void
	 */
	private function output_json( array $diff_data, \ConfigSync\DiffEngine $diff_engine ): void {
		$output = array();

		foreach ( $diff_data as $provider_id => $items ) {
			if ( empty( $items ) ) {
				continue;
			}

			$output[ $provider_id ] = $diff_engine->format_for_rest( $items );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		WP_CLI::line( wp_json_encode( $output, JSON_PRETTY_PRINT ) );
	}

	/**
	 * Output diff as YAML.
	 *
	 * @since 1.0.0
	 *
	 * @param array                  $diff_data   Per-provider diff arrays.
	 * @param \ConfigSync\DiffEngine $diff_engine Diff engine instance.
	 * @return void
	 */
	private function output_yaml( array $diff_data, \ConfigSync\DiffEngine $diff_engine ): void {
		$output = array();

		foreach ( $diff_data as $provider_id => $items ) {
			if ( empty( $items ) ) {
				continue;
			}

			$output[ $provider_id ] = $diff_engine->format_for_rest( $items );
		}

		WP_CLI::line( \Symfony\Component\Yaml\Yaml::dump( $output, 4, 2 ) );
	}
}
