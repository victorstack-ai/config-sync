<?php
/**
 * Abstract provider base class.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbstractProvider
 *
 * Provides default implementations for common ProviderInterface methods.
 * Concrete providers should extend this class and implement the remaining
 * abstract methods.
 *
 * @since 1.0.0
 */
abstract class AbstractProvider implements ProviderInterface {

	/**
	 * Get the IDs of providers this provider depends on.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of provider ID strings. Empty by default.
	 */
	public function get_dependencies(): array {
		return array();
	}

	/**
	 * Get the number of items to process per batch iteration.
	 *
	 * @since 1.0.0
	 *
	 * @return int Items per batch. Defaults to 100.
	 */
	public function get_batch_size(): int {
		return 100;
	}

	/**
	 * Preview changes without applying them.
	 *
	 * Computes the diff between the current database state and the given
	 * configuration by delegating to the DiffEngine service.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Configuration data to preview.
	 * @return array Structured diff of changes that would be applied.
	 */
	public function dry_run( array $config ): array {
		$current = $this->export();

		return config_sync()->get_diff_engine()->compute( $current, $config, $this->get_id() );
	}

	/**
	 * Validate configuration data against the provider schema.
	 *
	 * Delegates to the SchemaValidator service using this provider's ID.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Configuration data to validate.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function validate( array $config ) {
		return config_sync()->get_schema_validator()->validate( $this->get_id(), $config );
	}

	/**
	 * Sanitize a value based on its expected type.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value The value to sanitize.
	 * @param string $type  The expected type. Accepts 'string', 'int', 'bool', 'url', 'html', 'array'.
	 * @return mixed The sanitized value.
	 */
	protected function sanitize_value( $value, string $type = 'string' ) {
		switch ( $type ) {
			case 'int':
				return absint( $value );

			case 'bool':
				return (bool) $value;

			case 'url':
				return sanitize_url( $value );

			case 'html':
				return wp_kses_post( $value );

			case 'array':
				return array_map( 'sanitize_text_field', (array) $value );

			case 'string':
			default:
				return sanitize_text_field( $value );
		}
	}
}
