<?php
/**
 * Diff engine for comparing configuration states.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DiffEngine
 *
 * Compares current (database) state against incoming (file) state
 * and produces a structured diff suitable for CLI, REST, and UI output.
 *
 * @since 1.0.0
 */
class DiffEngine {

	/**
	 * Compute the diff between current and incoming configuration arrays.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $current     Current configuration state (from database).
	 * @param array  $incoming    Incoming configuration state (from file).
	 * @param string $provider_id Optional provider identifier.
	 * @return array Array of diff items.
	 */
	public function compute( array $current, array $incoming, string $provider_id = '' ): array {
		$diff = array();

		foreach ( $this->compute_generator( $current, $incoming, $provider_id ) as $item ) {
			$diff[] = $item;
		}

		return $diff;
	}

	/**
	 * Compute the diff using a generator for memory efficiency.
	 *
	 * Yields diff items one at a time and unsets processed keys from a
	 * working copy of the current array. This allows processing 50k+
	 * items without a memory spike.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $current     Current configuration state (from database).
	 * @param array  $incoming    Incoming configuration state (from file).
	 * @param string $provider_id Optional provider identifier.
	 * @return \Generator Yields diff item arrays.
	 */
	public function compute_generator( array $current, array $incoming, string $provider_id = '' ): \Generator {
		$current_copy = $current;

		// Detect added and modified keys.
		foreach ( $incoming as $key => $new_value ) {
			if ( ! array_key_exists( $key, $current_copy ) ) {
				yield array(
					'type'     => 'added',
					'key'      => (string) $key,
					'old'      => null,
					'new'      => $new_value,
					'provider' => $provider_id,
				);
			} elseif ( ! $this->values_are_equal( $current_copy[ $key ], $new_value ) ) {
				yield array(
					'type'     => 'modified',
					'key'      => (string) $key,
					'old'      => $current_copy[ $key ],
					'new'      => $new_value,
					'provider' => $provider_id,
				);
				unset( $current_copy[ $key ] );
			} else {
				// Values are identical — remove from working copy.
				unset( $current_copy[ $key ] );
			}
		}

		// Remaining keys in current_copy are removed.
		foreach ( $current_copy as $key => $old_value ) {
			// Skip keys that were already yielded as modified.
			if ( array_key_exists( $key, $incoming ) ) {
				continue;
			}

			yield array(
				'type'     => 'removed',
				'key'      => (string) $key,
				'old'      => $old_value,
				'new'      => null,
				'provider' => $provider_id,
			);
		}
	}

	/**
	 * Summarize a diff array by counting items per type.
	 *
	 * @since 1.0.0
	 *
	 * @param array $diff Array of diff items.
	 * @return array Associative array with 'added', 'modified', and 'removed' counts.
	 */
	public function summarize( array $diff ): array {
		$summary = array(
			'added'    => 0,
			'modified' => 0,
			'removed'  => 0,
		);

		foreach ( $diff as $item ) {
			if ( isset( $summary[ $item['type'] ] ) ) {
				++$summary[ $item['type'] ];
			}
		}

		return $summary;
	}

	/**
	 * Format a diff for WP-CLI output with color codes.
	 *
	 * Uses WP_CLI::colorize() format strings:
	 * - Green (+) for added items
	 * - Yellow (~) for modified items
	 * - Red (-) for removed items
	 *
	 * @since 1.0.0
	 *
	 * @param array $diff Array of diff items.
	 * @return string Formatted string for CLI output.
	 */
	public function format_for_cli( array $diff ): string {
		$lines = array();

		foreach ( $diff as $item ) {
			switch ( $item['type'] ) {
				case 'added':
					$lines[] = sprintf( '%s %s: %s', '%G+%n', 'added', $item['key'] );
					break;

				case 'modified':
					$lines[] = sprintf( '%s %s: %s', '%Y~%n', 'modified', $item['key'] );
					break;

				case 'removed':
					$lines[] = sprintf( '%s %s: %s', '%R-%n', 'removed', $item['key'] );
					break;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Format a diff for REST API output.
	 *
	 * Returns the diff array as-is (already JSON-serializable) with
	 * an added 'summary' key containing the summarized counts.
	 *
	 * @since 1.0.0
	 *
	 * @param array $diff Array of diff items.
	 * @return array The diff with a 'summary' key appended.
	 */
	public function format_for_rest( array $diff ): array {
		return array(
			'items'   => $diff,
			'summary' => $this->summarize( $diff ),
		);
	}

	/**
	 * Deep-compare two values using strict comparison.
	 *
	 * Handles nested arrays recursively. Type differences are significant
	 * (e.g., integer 1 is not equal to string "1").
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $a First value.
	 * @param mixed $b Second value.
	 * @return bool True if values are strictly equal.
	 */
	private function values_are_equal( $a, $b ): bool {
		// Different types are never equal.
		if ( gettype( $a ) !== gettype( $b ) ) {
			return false;
		}

		// Recursively compare arrays.
		if ( is_array( $a ) && is_array( $b ) ) {
			if ( count( $a ) !== count( $b ) ) {
				return false;
			}

			foreach ( $a as $key => $value ) {
				if ( ! array_key_exists( $key, $b ) ) {
					return false;
				}

				if ( ! $this->values_are_equal( $value, $b[ $key ] ) ) {
					return false;
				}
			}

			return true;
		}

		return $a === $b;
	}
}
