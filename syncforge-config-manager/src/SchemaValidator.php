<?php
/**
 * Schema validator for configuration data.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SchemaValidator
 *
 * Validates configuration data against JSON schemas for each provider.
 *
 * @since 1.0.0
 */
class SchemaValidator {

	/**
	 * Registered schemas mapped by provider ID.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $schemas = array();

	/**
	 * Whether default schemas have been loaded.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $defaults_loaded = false;

	/**
	 * Validate configuration data against the schema for a provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider_id The provider identifier.
	 * @param array  $data        The configuration data to validate.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function validate( string $provider_id, array $data ) {
		if ( empty( $provider_id ) ) {
			return new \WP_Error(
				'config_sync_validation_failed',
				__( 'Provider ID must not be empty.', 'syncforge-config-manager' )
			);
		}

		if ( ! $this->defaults_loaded ) {
			$this->load_default_schemas();
		}

		$schema = $this->get_schema( $provider_id );

		if ( null === $schema ) {
			// No schema registered — allow data through.
			return true;
		}

		$errors = array();

		// Validate top-level type.
		if ( isset( $schema['type'] ) && 'object' === $schema['type'] && ! is_array( $data ) ) {
			$errors[] = __( 'Data must be an object.', 'syncforge-config-manager' );
		}

		// Validate required properties.
		if ( isset( $schema['required'] ) && is_array( $schema['required'] ) ) {
			foreach ( $schema['required'] as $required_key ) {
				if ( ! array_key_exists( $required_key, $data ) ) {
					$errors[] = sprintf(
						/* translators: %s: required field name */
						__( 'Missing required field: %s.', 'syncforge-config-manager' ),
						$required_key
					);
				}
			}
		}

		// Validate properties with defined types.
		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $key => $property_schema ) {
				if ( ! array_key_exists( $key, $data ) ) {
					continue;
				}

				$type_error = $this->validate_type( $data[ $key ], $property_schema, $key );
				if ( null !== $type_error ) {
					$errors[] = $type_error;
				}
			}
		}

		// Validate pattern properties.
		if ( isset( $schema['patternProperties'] ) && is_array( $schema['patternProperties'] ) ) {
			foreach ( $data as $key => $value ) {
				// Skip keys already validated via properties.
				if ( isset( $schema['properties'][ $key ] ) ) {
					continue;
				}

				foreach ( $schema['patternProperties'] as $pattern => $property_schema ) {
					if ( preg_match( '/' . $pattern . '/', $key ) ) {
						$type_error = $this->validate_type( $value, $property_schema, $key );
						if ( null !== $type_error ) {
							$errors[] = $type_error;
						}

						// Validate nested required and properties.
						if ( isset( $property_schema['type'] ) && 'object' === $property_schema['type'] && is_array( $value ) ) {
							if ( isset( $property_schema['required'] ) && is_array( $property_schema['required'] ) ) {
								foreach ( $property_schema['required'] as $required_key ) {
									if ( ! array_key_exists( $required_key, $value ) ) {
										$errors[] = sprintf(
											/* translators: 1: required field name, 2: parent key */
											__( 'Missing required field: %1$s in %2$s.', 'syncforge-config-manager' ),
											$required_key,
											$key
										);
									}
								}
							}
						}
						break;
					}
				}

				// Check additionalProperties.
				if ( isset( $schema['additionalProperties'] ) && false === $schema['additionalProperties'] ) {
					$matched = false;
					if ( isset( $schema['properties'][ $key ] ) ) {
						$matched = true;
					}
					if ( ! $matched && isset( $schema['patternProperties'] ) ) {
						foreach ( $schema['patternProperties'] as $pattern => $property_schema ) {
							if ( preg_match( '/' . $pattern . '/', $key ) ) {
								$matched = true;
								break;
							}
						}
					}
					if ( ! $matched ) {
						$errors[] = sprintf(
							/* translators: %s: field name */
							__( 'Unexpected field: %s.', 'syncforge-config-manager' ),
							$key
						);
					}
				}
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'config_sync_validation_failed',
				implode( ' ', $errors ),
				array( 'errors' => $errors )
			);
		}

		return true;
	}

	/**
	 * Register a JSON schema for a provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider_id The provider identifier.
	 * @param string $schema_path Absolute path to the JSON schema file.
	 * @return void
	 */
	public function register_schema( string $provider_id, string $schema_path ): void {
		$real_path = realpath( $schema_path );

		if ( false === $real_path ) {
			return;
		}

		// Containment check: schema must be within plugin directory.
		$plugin_dir = realpath( dirname( __DIR__ ) );
		if ( false === $plugin_dir || 0 !== strpos( $real_path, $plugin_dir ) ) {
			return;
		}

		$contents = file_get_contents( $real_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading bundled schema file, not user content.
		if ( false === $contents ) {
			return;
		}

		$schema = json_decode( $contents, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $schema ) ) {
			return;
		}

		$this->schemas[ $provider_id ] = $schema;
	}

	/**
	 * Get the schema for a provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider_id The provider identifier.
	 * @return array|null The schema array or null if not registered.
	 */
	public function get_schema( string $provider_id ): ?array {
		return isset( $this->schemas[ $provider_id ] ) ? $this->schemas[ $provider_id ] : null;
	}

	/**
	 * Auto-load default schemas from the Schema directory.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function load_default_schemas(): void {
		$this->defaults_loaded = true;

		$schema_dir = dirname( __FILE__ ) . '/Schema';
		if ( ! is_dir( $schema_dir ) ) {
			return;
		}

		$schema_map = array(
			'options'        => 'options.json',
			'roles'          => 'roles.json',
			'menus'          => 'menus.json',
			'widgets'        => 'widgets.json',
			'theme_mods'     => 'theme-mods.json',
			'rewrite'        => 'rewrite.json',
			'block_patterns' => 'block-patterns.json',
		);

		foreach ( $schema_map as $provider_id => $filename ) {
			$path = $schema_dir . '/' . $filename;
			if ( file_exists( $path ) ) {
				$this->register_schema( $provider_id, $path );
			}
		}
	}

	/**
	 * Validate a value against a type definition.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value           The value to validate.
	 * @param array  $property_schema The property schema definition.
	 * @param string $key             The property key (for error messages).
	 * @return string|null Error message string or null if valid.
	 */
	private function validate_type( $value, array $property_schema, string $key ): ?string {
		if ( ! isset( $property_schema['type'] ) ) {
			return null;
		}

		$types = (array) $property_schema['type'];
		$valid = false;

		foreach ( $types as $type ) {
			switch ( $type ) {
				case 'string':
					if ( is_string( $value ) ) {
						$valid = true;
					}
					break;

				case 'integer':
					if ( is_int( $value ) ) {
						$valid = true;
					}
					break;

				case 'boolean':
					if ( is_bool( $value ) ) {
						$valid = true;
					}
					break;

				case 'array':
					if ( is_array( $value ) && wp_is_numeric_array( $value ) ) {
						$valid = true;
					}
					// Also accept any array for flexibility.
					if ( is_array( $value ) ) {
						$valid = true;
					}
					break;

				case 'object':
					if ( is_array( $value ) && ! wp_is_numeric_array( $value ) ) {
						$valid = true;
					}
					// Accept empty arrays as objects too.
					if ( is_array( $value ) && empty( $value ) ) {
						$valid = true;
					}
					break;

				case 'null':
					if ( null === $value ) {
						$valid = true;
					}
					break;
			}

			if ( $valid ) {
				break;
			}
		}

		if ( ! $valid ) {
			return sprintf(
				/* translators: 1: field name, 2: expected type(s) */
				__( 'Field %1$s must be of type %2$s.', 'syncforge-config-manager' ),
				$key,
				implode( '|', $types )
			);
		}

		return null;
	}
}
