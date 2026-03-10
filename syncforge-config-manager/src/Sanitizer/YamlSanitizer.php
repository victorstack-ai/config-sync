<?php
/**
 * YAML content sanitizer.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class YamlSanitizer
 *
 * Sanitizes parsed YAML data to prevent injection attacks and
 * filter sensitive values.
 *
 * @since 1.0.0
 */
class YamlSanitizer {

	/**
	 * Sanitize parsed YAML data.
	 *
	 * Recursively walks all values, applies type-appropriate sanitization,
	 * rejects serialized PHP objects, and filters secret patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data        The parsed YAML data.
	 * @param string $provider_id The provider identifier for context.
	 * @return array The sanitized data.
	 */
	public function sanitize( array $data, string $provider_id ): array {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			// Use sanitize_text_field to preserve case in option names.
			// sanitize_key() lowercases everything, breaking case-sensitive
			// WordPress options like options_Careers_cta.
			$sanitized_key = is_string( $key ) ? sanitize_text_field( $key ) : $key;

			$sanitized[ $sanitized_key ] = $this->sanitize_recursive( $value, (string) $sanitized_key );
		}

		return $sanitized;
	}

	/**
	 * Check if a key matches a secret pattern.
	 *
	 * Secret keys are stripped during sanitization to prevent accidental
	 * exposure of credentials in configuration files.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The key to check.
	 * @return bool True if the key matches a secret pattern.
	 */
	public function is_secret( string $key ): bool {
		$defaults = array(
			'*_api_key',
			'*_password',
			'*_secret',
			'*_token',
			'auth_*',
			'*_salt',
		);

		/**
		 * Filters the list of secret key patterns.
		 *
		 * Patterns use wildcard (*) matching. Keys matching these patterns
		 * will be excluded from sanitized output.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $patterns Array of wildcard patterns.
		 */
		$patterns = apply_filters( 'config_sync_secret_patterns', $defaults );

		foreach ( $patterns as $pattern ) {
			$regex = '/^' . str_replace( '\*', '.*', preg_quote( $pattern, '/' ) ) . '$/i';
			if ( preg_match( $regex, $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Redact secret values in an array for safe audit log storage.
	 *
	 * Recursively walks the array and replaces values for keys matching
	 * secret patterns with a placeholder. Uses the same patterns as is_secret().
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Data to redact (e.g. snapshot or diff).
	 * @return array Copy of the data with secret values redacted.
	 */
	public static function redact_for_audit( array $data ): array {
		$sanitizer = new self();

		return $sanitizer->redact_recursive( $data );
	}

	/**
	 * Recursively redact values for keys matching secret patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Data to redact.
	 * @return array Redacted copy.
	 */
	private function redact_recursive( array $data ): array {
		$redacted = array();

		foreach ( $data as $key => $value ) {
			$key_str = is_string( $key ) ? $key : (string) $key;

			if ( $this->is_secret( strtolower( $key_str ) ) ) {
				$redacted[ $key ] = '[REDACTED]';
				continue;
			}

			if ( is_array( $value ) ) {
				$redacted[ $key ] = $this->redact_recursive( $value );
			} else {
				$redacted[ $key ] = $value;
			}
		}

		return $redacted;
	}

	/**
	 * Recursively sanitize a value.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $value The value to sanitize.
	 * @param string $key   The key associated with this value.
	 * @return mixed The sanitized value.
	 */
	private function sanitize_recursive( $value, string $key = '' ) {
		if ( is_array( $value ) ) {
			$sanitized = array();
			foreach ( $value as $sub_key => $sub_value ) {
				// Preserve case in keys using sanitize_text_field.
				$sanitized_sub_key = is_string( $sub_key ) ? sanitize_text_field( $sub_key ) : $sub_key;

				$sanitized[ $sanitized_sub_key ] = $this->sanitize_recursive( $sub_value, (string) $sanitized_sub_key );
			}
			return $sanitized;
		}

		if ( is_string( $value ) ) {
			// Reject serialized PHP objects.
			if ( $this->contains_serialized_object( $value ) ) {
				return '';
			}

			// Use wp_kses_post to preserve HTML in option values (e.g. banner
			// templates, widget content) while stripping dangerous tags/attributes.
			// sanitize_text_field strips ALL HTML which corrupts these values.
			return wp_kses_post( $value );
		}

		if ( is_int( $value ) ) {
			return (int) $value;
		}

		if ( is_float( $value ) ) {
			return (float) $value;
		}

		if ( is_bool( $value ) ) {
			return (bool) $value;
		}

		if ( null === $value ) {
			return null;
		}

		return '';
	}

	/**
	 * Check if a string contains a serialized PHP object.
	 *
	 * Detects patterns like O:8:"ClassName":... that indicate serialized
	 * PHP objects which could be exploited via deserialization attacks.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The string to check.
	 * @return bool True if serialized PHP object is detected.
	 */
	private function contains_serialized_object( string $value ): bool {
		// Detect serialized PHP objects: O:<number>:"<classname>"
		if ( preg_match( '/O:\d+:"[^"]+":/', $value ) ) {
			return true;
		}

		// Detect serialized objects embedded in serialized arrays.
		if ( preg_match( '/a:\d+:\{.*O:\d+:"[^"]+":/', $value ) ) {
			return true;
		}

		return false;
	}
}
