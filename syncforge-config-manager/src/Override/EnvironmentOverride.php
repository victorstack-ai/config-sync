<?php
/**
 * Environment-specific configuration overrides.
 *
 * Allows per-environment config overrides (e.g., different settings
 * for staging vs production).
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Override;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\FileHandler;

/**
 * Class EnvironmentOverride
 *
 * Detects the current environment and applies environment-specific
 * configuration overrides by deep-merging YAML files from
 * config-dir/environments/{environment}/ on top of the base config.
 *
 * @since 1.0.0
 */
class EnvironmentOverride {

	/**
	 * Sentinel value used to mark keys that should be removed.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const REMOVE_SENTINEL = '__remove__';

	/**
	 * File handler instance.
	 *
	 * @since 1.0.0
	 * @var FileHandler
	 */
	private FileHandler $file_handler;

	/**
	 * Cached environment string.
	 *
	 * @since 1.0.0
	 * @var string|null
	 */
	private ?string $cached_environment = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param FileHandler $file_handler File handler instance for YAML I/O.
	 */
	public function __construct( FileHandler $file_handler ) {
		$this->file_handler = $file_handler;
	}

	/**
	 * Get the current environment string.
	 *
	 * Priority:
	 * 1. `config_sync_environment` filter
	 * 2. `CONFIG_SYNC_ENVIRONMENT` constant
	 * 3. `wp_get_environment_type()` (WordPress 5.5+)
	 * 4. Falls back to 'production'
	 *
	 * @since 1.0.0
	 *
	 * @return string Current environment identifier.
	 */
	public function get_environment(): string {
		if ( null !== $this->cached_environment ) {
			return $this->cached_environment;
		}

		$environment = 'production';

		if ( function_exists( 'wp_get_environment_type' ) ) {
			$environment = wp_get_environment_type();
		}

		if ( defined( 'CONFIG_SYNC_ENVIRONMENT' ) ) {
			$environment = CONFIG_SYNC_ENVIRONMENT;
		}

		/**
		 * Filters the current environment identifier.
		 *
		 * @since 1.0.0
		 *
		 * @param string $environment The detected environment string.
		 */
		$environment = apply_filters( 'config_sync_environment', $environment );

		$this->cached_environment = sanitize_key( $environment );

		return $this->cached_environment;
	}

	/**
	 * Get the relative path to the environment-specific config directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string Relative path, e.g. 'environments/staging/'.
	 */
	public function get_override_path(): string {
		return 'environments/' . $this->get_environment() . '/';
	}

	/**
	 * Apply environment overrides to a provider's configuration.
	 *
	 * Reads the environment-specific YAML file for the given provider
	 * and deep-merges it on top of the base config.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider_id The provider identifier slug.
	 * @param array  $config      Base configuration data.
	 * @return array Merged configuration with environment overrides applied.
	 */
	public function apply_overrides( string $provider_id, array $config ): array {
		$provider_id = sanitize_key( $provider_id );

		if ( ! $this->has_overrides( $provider_id ) ) {
			return $config;
		}

		$override_file = $this->get_override_path() . $provider_id . '.yml';
		$overrides     = $this->file_handler->read( $override_file );

		if ( empty( $overrides ) ) {
			return $config;
		}

		/**
		 * Filters the environment overrides before merging.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $overrides   The override data from the environment file.
		 * @param string $provider_id The provider identifier slug.
		 * @param string $environment The current environment string.
		 */
		$overrides = apply_filters(
			'config_sync_environment_overrides',
			$overrides,
			$provider_id,
			$this->get_environment()
		);

		return $this->deep_merge( $config, $overrides );
	}

	/**
	 * Check if environment override files exist for a provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider_id The provider identifier slug.
	 * @return bool True if an override file exists, false otherwise.
	 */
	public function has_overrides( string $provider_id ): bool {
		$provider_id   = sanitize_key( $provider_id );
		$override_file = $this->get_override_path() . $provider_id . '.yml';

		return $this->file_handler->exists( $override_file );
	}

	/**
	 * Export environment-specific overrides for a provider.
	 *
	 * Only writes values that differ from the base config. Uses the
	 * `__remove__` sentinel for keys present in base but absent in the
	 * override target.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider_id The provider identifier slug.
	 * @param array  $config      The full environment-specific configuration.
	 * @param array  $base_config The base configuration to diff against.
	 * @return bool True on success, false on failure.
	 */
	public function export_overrides( string $provider_id, array $config, array $base_config = array() ): bool {
		$provider_id = sanitize_key( $provider_id );
		$diff        = $this->compute_diff( $base_config, $config );

		if ( empty( $diff ) ) {
			$override_file = $this->get_override_path() . $provider_id . '.yml';

			if ( $this->file_handler->exists( $override_file ) ) {
				return $this->file_handler->delete( $override_file );
			}

			return true;
		}

		/**
		 * Filters the environment override data before writing.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $diff        The computed diff to write.
		 * @param string $provider_id The provider identifier slug.
		 * @param string $environment The current environment string.
		 */
		$diff = apply_filters(
			'config_sync_export_environment_overrides',
			$diff,
			$provider_id,
			$this->get_environment()
		);

		$override_file = $this->get_override_path() . $provider_id . '.yml';

		return $this->file_handler->write( $override_file, $diff );
	}

	/**
	 * Deep merge two arrays recursively.
	 *
	 * Override values replace base values. When both values are arrays,
	 * they are merged recursively. The `__remove__` sentinel causes
	 * the corresponding key to be deleted from the result.
	 *
	 * @since 1.0.0
	 *
	 * @param array $base     Base configuration array.
	 * @param array $override Override configuration array.
	 * @return array Merged result.
	 */
	public function deep_merge( array $base, array $override ): array {
		$merged = $base;

		foreach ( $override as $key => $value ) {
			if ( self::REMOVE_SENTINEL === $value ) {
				unset( $merged[ $key ] );
				continue;
			}

			if ( is_array( $value ) && isset( $merged[ $key ] ) && is_array( $merged[ $key ] ) ) {
				$merged[ $key ] = $this->deep_merge( $merged[ $key ], $value );
				continue;
			}

			$merged[ $key ] = $value;
		}

		return $merged;
	}

	/**
	 * Compute the diff between base and target configurations.
	 *
	 * Returns only the entries that differ. Keys present in base but
	 * absent in target are marked with the `__remove__` sentinel.
	 *
	 * @since 1.0.0
	 *
	 * @param array $base   The base configuration.
	 * @param array $target The target (environment) configuration.
	 * @return array The diff containing only changed or removed entries.
	 */
	private function compute_diff( array $base, array $target ): array {
		$diff = array();

		// Find changed or added keys.
		foreach ( $target as $key => $value ) {
			if ( ! array_key_exists( $key, $base ) ) {
				$diff[ $key ] = $value;
				continue;
			}

			if ( is_array( $value ) && is_array( $base[ $key ] ) ) {
				$sub_diff = $this->compute_diff( $base[ $key ], $value );

				if ( ! empty( $sub_diff ) ) {
					$diff[ $key ] = $sub_diff;
				}
				continue;
			}

			if ( $base[ $key ] !== $value ) {
				$diff[ $key ] = $value;
			}
		}

		// Find removed keys.
		foreach ( $base as $key => $value ) {
			if ( ! array_key_exists( $key, $target ) ) {
				$diff[ $key ] = self::REMOVE_SENTINEL;
			}
		}

		return $diff;
	}
}
