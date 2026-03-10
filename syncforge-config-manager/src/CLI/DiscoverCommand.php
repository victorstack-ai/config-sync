<?php
/**
 * WP-CLI discover command for Config Sync.
 *
 * @package ConfigSync
 * @since   1.1.0
 */

namespace ConfigSync\CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Admin\OptionDiscovery;
use WP_CLI;
use WP_CLI\Utils;

/**
 * Class DiscoverCommand
 *
 * Discovers non-core options in the database and optionally tracks them.
 *
 * ## EXAMPLES
 *
 *     # List all discoverable options
 *     $ wp syncforge discover
 *
 *     # Track all discovered options for export
 *     $ wp syncforge discover --track-all
 *
 *     # Show option values
 *     $ wp syncforge discover --show-values
 *
 * @since 1.1.0
 */
class DiscoverCommand {

	/**
	 * Discover non-core options in the database.
	 *
	 * Scans wp_options for plugin and theme options not already handled
	 * by the core OptionsProvider. Use --track-all to mark them all for
	 * export, or --track=<name> to track a specific option.
	 *
	 * ## OPTIONS
	 *
	 * [--track-all]
	 * : Track all discovered options for export.
	 *
	 * [--track=<name>]
	 * : Track a specific option by name.
	 *
	 * [--untrack=<name>]
	 * : Remove a specific option from tracking.
	 *
	 * [--show-values]
	 * : Include option values in the output.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, csv, json, yaml. Default: table.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List all discoverable plugin/theme options
	 *     $ wp syncforge discover
	 *
	 *     # Track all for export
	 *     $ wp syncforge discover --track-all
	 *
	 *     # Track a specific option
	 *     $ wp syncforge discover --track=wpseo_titles
	 *
	 * @synopsis [--track-all] [--track=<name>] [--untrack=<name>] [--show-values] [--format=<format>]
	 *
	 * @since 1.1.0
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$discovery    = new OptionDiscovery();
		$track_all    = Utils\get_flag_value( $assoc_args, 'track-all', false );
		$track_name   = Utils\get_flag_value( $assoc_args, 'track', '' );
		$untrack_name = Utils\get_flag_value( $assoc_args, 'untrack', '' );
		$show_values  = Utils\get_flag_value( $assoc_args, 'show-values', false );
		$format       = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		// Handle single track.
		if ( '' !== $track_name ) {
			$discovery->track_option( $track_name );
			WP_CLI::success(
				/* translators: %s: option name */
				sprintf( __( 'Now tracking option: %s', 'syncforge-config-manager' ), $track_name )
			);
			return;
		}

		// Handle single untrack.
		if ( '' !== $untrack_name ) {
			$discovery->untrack_option( $untrack_name );
			WP_CLI::success(
				/* translators: %s: option name */
				sprintf( __( 'Stopped tracking option: %s', 'syncforge-config-manager' ), $untrack_name )
			);
			return;
		}

		$discovered = $discovery->discover( $show_values );

		if ( empty( $discovered ) ) {
			WP_CLI::log( __( 'No additional options discovered.', 'syncforge-config-manager' ) );
			return;
		}

		// Track all if requested.
		if ( $track_all ) {
			$all_names = array_keys( $discovered );
			$discovery->save_tracked_options( $all_names );
			WP_CLI::success(
				sprintf(
					/* translators: %d: number of options tracked */
					_n(
						'Tracked %d option for export.',
						'Tracked %d options for export.',
						count( $all_names ),
						'syncforge-config-manager'
					),
					count( $all_names )
				)
			);
			WP_CLI::log( __( 'Run "wp syncforge export" to export them.', 'syncforge-config-manager' ) );
			return;
		}

		// Display discovered options.
		$table_data = array();
		$columns    = array( 'name', 'autoload', 'tracked' );

		if ( $show_values ) {
			$columns[] = 'value';
		}

		foreach ( $discovered as $name => $item ) {
			$row = array(
				'name'     => $name,
				'autoload' => $item['autoload'],
				'tracked'  => $item['tracked'] ? 'yes' : 'no',
			);

			if ( $show_values ) {
				$value = isset( $item['value'] ) ? $item['value'] : '';
				if ( is_array( $value ) || is_object( $value ) ) {
					$value = wp_json_encode( $value );
				}
				if ( is_string( $value ) && strlen( $value ) > 100 ) {
					$value = substr( $value, 0, 97 ) . '...';
				}
				$row['value'] = $value;
			}

			$table_data[] = $row;
		}

		Utils\format_items( $format, $table_data, $columns );

		$tracked_count = count( array_filter( $discovered, function ( $item ) {
			return $item['tracked'];
		} ) );

		WP_CLI::log(
			sprintf(
				/* translators: 1: number of discovered options, 2: number of tracked options */
				__( 'Found %1$d discoverable options (%2$d tracked). Use --track-all to track all.', 'syncforge-config-manager' ),
				count( $discovered ),
				$tracked_count
			)
		);
	}
}
