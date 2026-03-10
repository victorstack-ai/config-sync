<?php
/**
 * Theme Mods provider.
 *
 * Exports and imports theme_mod settings for the active theme.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ThemeModsProvider
 *
 * Handles export and import of WordPress theme modifications (theme_mods).
 * Records the active theme stylesheet so that theme mismatches can be
 * detected during import.
 *
 * @since 1.0.0
 */
class ThemeModsProvider extends AbstractProvider {

	/**
	 * Keys that should be excluded from export because they are managed
	 * by other providers or are not true configuration.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private const EXCLUDED_KEYS = array(
		0,
		'nav_menu_locations',
		'sidebars_widgets',
		'custom_css_post_id',
	);

	/**
	 * Get the unique provider identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider ID.
	 */
	public function get_id(): string {
		return 'theme-mods';
	}

	/**
	 * Get the translated human-readable label.
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider label.
	 */
	public function get_label(): string {
		return __( 'Theme Mods', 'syncforge-config-manager' );
	}

	/**
	 * Get the IDs of providers this provider depends on.
	 *
	 * Theme mods require the active theme (set via options) to be known.
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
	 * Export the current theme modifications.
	 *
	 * Returns an array containing the active theme stylesheet and all
	 * theme mods, excluding keys managed by other providers.
	 *
	 * @since 1.0.0
	 *
	 * @return array Exported configuration with '_theme' and 'mods' keys.
	 */
	public function export(): array {
		$stylesheet = get_stylesheet();
		$mods       = get_theme_mods();

		if ( ! is_array( $mods ) ) {
			$mods = array();
		}

		foreach ( self::EXCLUDED_KEYS as $key ) {
			unset( $mods[ $key ] );
		}

		/**
		 * Filters the theme mods before export.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $mods       Theme modifications to export.
		 * @param string $stylesheet Active theme stylesheet.
		 */
		$mods = apply_filters( 'config_sync_export_theme_mods', $mods, $stylesheet );

		return array(
			'_theme' => $stylesheet,
			'mods'   => $mods,
		);
	}

	/**
	 * Import theme modifications from configuration.
	 *
	 * If the configuration was exported from a different theme, a warning
	 * is logged but the import proceeds. Mods present in the database but
	 * absent from the config are removed.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Configuration data with '_theme' and 'mods' keys.
	 * @return array{created: int, updated: int, deleted: int, details: string[]} Change summary.
	 */
	public function import( array $config ): array {
		$result = array(
			'created' => 0,
			'updated' => 0,
			'deleted' => 0,
			'details' => array(),
		);

		$current_theme = get_stylesheet();
		$config_theme  = isset( $config['_theme'] ) ? $config['_theme'] : '';

		if ( $config_theme !== $current_theme ) {
			$result['details'][] = sprintf(
				/* translators: 1: config theme stylesheet, 2: active theme stylesheet */
				__( 'Warning: config was exported from theme "%1$s" but active theme is "%2$s".', 'syncforge-config-manager' ),
				$config_theme,
				$current_theme
			);
		}

		$incoming_mods = isset( $config['mods'] ) && is_array( $config['mods'] ) ? $config['mods'] : array();
		$current_mods  = get_theme_mods();

		if ( ! is_array( $current_mods ) ) {
			$current_mods = array();
		}

		// Remove excluded keys from current mods for comparison.
		foreach ( self::EXCLUDED_KEYS as $key ) {
			unset( $current_mods[ $key ] );
		}

		// Set or update mods from the config.
		foreach ( $incoming_mods as $key => $value ) {
			if ( ! array_key_exists( $key, $current_mods ) ) {
				set_theme_mod( $key, $value );
				++$result['created'];
				$result['details'][] = sprintf(
					/* translators: %s: theme mod key */
					__( 'Created theme mod "%s".', 'syncforge-config-manager' ),
					$key
				);
			} elseif ( $current_mods[ $key ] !== $value ) {
				set_theme_mod( $key, $value );
				++$result['updated'];
				$result['details'][] = sprintf(
					/* translators: %s: theme mod key */
					__( 'Updated theme mod "%s".', 'syncforge-config-manager' ),
					$key
				);
			}
		}

		// Remove mods present in DB but not in config.
		foreach ( $current_mods as $key => $value ) {
			if ( ! array_key_exists( $key, $incoming_mods ) ) {
				remove_theme_mod( $key );
				++$result['deleted'];
				$result['details'][] = sprintf(
					/* translators: %s: theme mod key */
					__( 'Removed theme mod "%s".', 'syncforge-config-manager' ),
					$key
				);
			}
		}

		return $result;
	}

	/**
	 * Get the configuration file paths managed by this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Relative file paths.
	 */
	public function get_config_files(): array {
		return array( 'theme-mods.yml' );
	}
}
