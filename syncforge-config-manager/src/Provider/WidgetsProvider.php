<?php
/**
 * Widgets configuration provider.
 *
 * Exports and imports WordPress widget configurations organized by sidebar,
 * including widget instance settings and sidebar-to-widget mappings.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WidgetsProvider
 *
 * Handles export and import of WordPress widget configurations.
 * Widgets are organized by sidebar, with each sidebar containing
 * an array of widget definitions including type, instance number,
 * and settings.
 *
 * @since 1.0.0
 */
class WidgetsProvider extends AbstractProvider {

	/**
	 * Get the unique identifier slug for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider slug.
	 */
	public function get_id(): string {
		return 'widgets';
	}

	/**
	 * Get the human-readable translated label for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string Translated provider name.
	 */
	public function get_label(): string {
		return __( 'Widgets', 'syncforge-config-manager' );
	}

	/**
	 * Get the IDs of providers this provider depends on.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of provider ID strings.
	 */
	public function get_dependencies(): array {
		return array( 'options' );
	}

	/**
	 * Get the number of items to process per batch iteration.
	 *
	 * @since 1.0.0
	 *
	 * @return int Items per batch.
	 */
	public function get_batch_size(): int {
		return 100;
	}

	/**
	 * Export widget configuration from the database.
	 *
	 * Reads all sidebar-to-widget mappings and their associated settings,
	 * skipping the wp_inactive_widgets sidebar and the internal array_version key.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Configuration data keyed by sidebar ID.
	 */
	public function export(): array {
		$sidebars_widgets = get_option( 'sidebars_widgets', array() );
		$export           = array();

		if ( ! is_array( $sidebars_widgets ) ) {
			return $export;
		}

		/**
		 * Filters the sidebar IDs to exclude from widget export.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $excluded_sidebars Sidebar IDs to exclude.
		 */
		$excluded_sidebars = apply_filters(
			'config_sync_widgets_excluded_sidebars',
			array( 'wp_inactive_widgets' )
		);

		foreach ( $sidebars_widgets as $sidebar_id => $widgets ) {
			if ( 'array_version' === $sidebar_id ) {
				continue;
			}

			if ( in_array( $sidebar_id, $excluded_sidebars, true ) ) {
				continue;
			}

			if ( ! is_array( $widgets ) ) {
				continue;
			}

			$sidebar_data = array();

			foreach ( $widgets as $widget_id ) {
				$parsed = $this->parse_widget_id( $widget_id );

				if ( false === $parsed ) {
					continue;
				}

				$widget_type = $parsed['type'];
				$instance_id = $parsed['instance'];
				$instances   = $this->get_widget_instances( $widget_type );

				if ( isset( $instances[ $instance_id ] ) ) {
					$settings = maybe_unserialize( $instances[ $instance_id ] );

					$sidebar_data[] = array(
						'type'     => $widget_type,
						'instance' => $instance_id,
						'settings' => is_array( $settings ) ? $settings : array(),
					);
				}
			}

			$export[ $sidebar_id ] = $sidebar_data;
		}

		/**
		 * Filters the exported widget configuration.
		 *
		 * @since 1.0.0
		 *
		 * @param array $export Exported widget configuration keyed by sidebar ID.
		 */
		$export = apply_filters( 'config_sync_widgets_export', $export );

		return $export;
	}

	/**
	 * Import widget configuration into the database.
	 *
	 * For each sidebar in the configuration, updates the widget instance
	 * options and rebuilds the sidebars_widgets mapping. Widget types that
	 * are not registered on the target site are skipped with a warning.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Configuration data to import.
	 * @return array{created: int, updated: int, deleted: int, details: string[]} Import result summary.
	 */
	public function import( array $config ): array {
		$result = array(
			'created' => 0,
			'updated' => 0,
			'deleted' => 0,
			'details' => array(),
		);

		$new_sidebars_widgets = array();
		$widget_instances     = array();
		$registered_widgets   = $this->get_registered_widget_types();

		foreach ( $config as $sidebar_id => $widgets ) {
			$sidebar_id = sanitize_key( $sidebar_id );

			if ( ! is_array( $widgets ) ) {
				continue;
			}

			$sidebar_widget_ids = array();

			foreach ( $widgets as $widget ) {
				if ( ! isset( $widget['type'], $widget['instance'], $widget['settings'] ) ) {
					continue;
				}

				$widget_type = sanitize_key( $widget['type'] );
				$instance_id = absint( $widget['instance'] );
				$settings    = $this->sanitize_widget_settings( $widget['settings'] );

				// Skip widget types not registered on the target site.
				if ( ! empty( $registered_widgets ) && ! in_array( $widget_type, $registered_widgets, true ) ) {
					$result['details'][] = sprintf(
						/* translators: 1: widget type, 2: sidebar ID */
						__( 'Skipped widget type "%1$s" in sidebar "%2$s": widget type not registered on this site.', 'syncforge-config-manager' ),
						esc_html( $widget_type ),
						esc_html( $sidebar_id )
					);
					continue;
				}

				if ( ! isset( $widget_instances[ $widget_type ] ) ) {
					$widget_instances[ $widget_type ] = $this->get_widget_instances( $widget_type );
				}

				$is_new = ! isset( $widget_instances[ $widget_type ][ $instance_id ] );

				$widget_instances[ $widget_type ][ $instance_id ] = $settings;

				$widget_id            = $widget_type . '-' . $instance_id;
				$sidebar_widget_ids[] = $widget_id;

				if ( $is_new ) {
					++$result['created'];
					$result['details'][] = sprintf(
						/* translators: 1: widget ID, 2: sidebar ID */
						__( 'Created widget %1$s in %2$s.', 'syncforge-config-manager' ),
						esc_html( $widget_id ),
						esc_html( $sidebar_id )
					);
				} else {
					++$result['updated'];
					$result['details'][] = sprintf(
						/* translators: 1: widget ID, 2: sidebar ID */
						__( 'Updated widget %1$s in %2$s.', 'syncforge-config-manager' ),
						esc_html( $widget_id ),
						esc_html( $sidebar_id )
					);
				}
			}

			$new_sidebars_widgets[ $sidebar_id ] = $sidebar_widget_ids;
		}

		// Detect deleted widgets by comparing current sidebars with incoming config.
		$current_sidebars = get_option( 'sidebars_widgets', array() );

		if ( is_array( $current_sidebars ) ) {
			foreach ( $current_sidebars as $current_sidebar_id => $current_widgets ) {
				if ( 'array_version' === $current_sidebar_id ) {
					continue;
				}

				if ( 'wp_inactive_widgets' === $current_sidebar_id ) {
					continue;
				}

				if ( ! is_array( $current_widgets ) ) {
					continue;
				}

				$new_widgets = isset( $new_sidebars_widgets[ $current_sidebar_id ] )
					? $new_sidebars_widgets[ $current_sidebar_id ]
					: array();

				foreach ( $current_widgets as $current_widget_id ) {
					if ( ! in_array( $current_widget_id, $new_widgets, true ) ) {
						++$result['deleted'];
						$result['details'][] = sprintf(
							/* translators: 1: widget ID, 2: sidebar ID */
							__( 'Removed widget %1$s from %2$s.', 'syncforge-config-manager' ),
							esc_html( $current_widget_id ),
							esc_html( $current_sidebar_id )
						);
					}
				}
			}
		}

		foreach ( $widget_instances as $widget_type => $instances ) {
			$this->set_widget_instances( $widget_type, $instances );
		}

		update_option( 'sidebars_widgets', $new_sidebars_widgets );

		/**
		 * Fires after widgets have been imported.
		 *
		 * @since 1.0.0
		 *
		 * @param array $config Configuration data that was imported.
		 * @param array $result Import result summary.
		 */
		do_action( 'config_sync_widgets_imported', $config, $result );

		return $result;
	}

	/**
	 * Get the relative YAML file paths for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of relative YAML file paths.
	 */
	public function get_config_files(): array {
		return array( 'widgets/' );
	}

	/**
	 * Get widget instances for a given widget type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $widget_type Widget type slug (e.g. 'text', 'custom_html').
	 * @return array Widget instances keyed by instance number.
	 */
	private function get_widget_instances( string $widget_type ): array {
		$option_name = 'widget_' . sanitize_key( $widget_type );
		$instances   = get_option( $option_name, array() );

		if ( ! is_array( $instances ) ) {
			return array();
		}

		return $instances;
	}

	/**
	 * Set widget instances for a given widget type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $widget_type Widget type slug.
	 * @param array  $instances   Widget instances keyed by instance number.
	 * @return void
	 */
	private function set_widget_instances( string $widget_type, array $instances ): void {
		$option_name = 'widget_' . sanitize_key( $widget_type );
		update_option( $option_name, $instances );
	}

	/**
	 * Parse a widget ID into its type and instance number.
	 *
	 * Widget IDs follow the pattern "type-instance", e.g. "text-2".
	 *
	 * @since 1.0.0
	 *
	 * @param string $widget_id The widget ID string.
	 * @return array{type: string, instance: int}|false Parsed data or false on failure.
	 */
	private function parse_widget_id( string $widget_id ) {
		$last_dash = strrpos( $widget_id, '-' );

		if ( false === $last_dash ) {
			return false;
		}

		$type     = substr( $widget_id, 0, $last_dash );
		$instance = substr( $widget_id, $last_dash + 1 );

		if ( '' === $type || ! is_numeric( $instance ) ) {
			return false;
		}

		return array(
			'type'     => $type,
			'instance' => (int) $instance,
		);
	}

	/**
	 * Get a list of registered widget type base IDs.
	 *
	 * Returns an array of widget base IDs from the global wp_widget_factory,
	 * or an empty array if the factory is not available (allowing all types).
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of registered widget type base IDs.
	 */
	private function get_registered_widget_types(): array {
		global $wp_widget_factory;

		if ( ! isset( $wp_widget_factory ) || ! is_object( $wp_widget_factory ) ) {
			return array();
		}

		if ( ! isset( $wp_widget_factory->widgets ) || ! is_array( $wp_widget_factory->widgets ) ) {
			return array();
		}

		$types = array();

		foreach ( $wp_widget_factory->widgets as $widget_object ) {
			if ( isset( $widget_object->id_base ) ) {
				$types[] = $widget_object->id_base;
			}
		}

		/**
		 * Filters the list of recognized widget types during import.
		 *
		 * Allows adding custom widget type base IDs that may not be registered
		 * via the standard widget factory.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $types Array of widget type base IDs.
		 */
		$types = apply_filters( 'config_sync_widgets_registered_types', $types );

		return $types;
	}

	/**
	 * Sanitize widget settings recursively.
	 *
	 * Walks through the settings array and sanitizes string values.
	 * Non-array, non-string values (int, bool, float) are preserved.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $settings Widget settings to sanitize.
	 * @return array Sanitized settings array.
	 */
	private function sanitize_widget_settings( $settings ): array {
		if ( ! is_array( $settings ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $settings as $key => $value ) {
			$safe_key = is_int( $key ) ? $key : sanitize_text_field( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $safe_key ] = $this->sanitize_widget_settings( $value );
			} elseif ( is_int( $value ) || is_float( $value ) || is_bool( $value ) ) {
				$sanitized[ $safe_key ] = $value;
			} else {
				$sanitized[ $safe_key ] = sanitize_text_field( (string) $value );
			}
		}

		return $sanitized;
	}
}
