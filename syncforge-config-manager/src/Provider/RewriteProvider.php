<?php
/**
 * Rewrite rules configuration provider.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RewriteProvider
 *
 * Exports and imports WordPress permalink / rewrite settings:
 * permalink_structure, category_base, and tag_base.
 *
 * @since 1.0.0
 */
class RewriteProvider extends AbstractProvider {

	/**
	 * Get the unique provider identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider ID.
	 */
	public function get_id(): string {
		return 'rewrite';
	}

	/**
	 * Get the translated human-readable label.
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider label.
	 */
	public function get_label(): string {
		return __( 'Rewrite Rules', 'syncforge-config-manager' );
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
		return 10;
	}

	/**
	 * Export the current rewrite configuration from the database.
	 *
	 * @since 1.0.0
	 *
	 * @return array Rewrite configuration keyed by option name.
	 */
	public function export(): array {
		return array(
			'permalink_structure' => get_option( 'permalink_structure' ),
			'category_base'       => get_option( 'category_base' ),
			'tag_base'            => get_option( 'tag_base' ),
		);
	}

	/**
	 * Import rewrite configuration and flush rewrite rules if changes occur.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Configuration data to import.
	 * @return array{created: int, updated: int, deleted: int, details: string[]} Change summary.
	 */
	public function import( array $config ): array {
		$result = array(
			'created' => 0,
			'updated' => 0,
			'deleted' => 0,
			'details' => array(),
		);

		$changed = false;

		$keys = array( 'permalink_structure', 'category_base', 'tag_base' );

		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $config ) ) {
				continue;
			}

			$new_value     = $this->sanitize_value( $config[ $key ], 'string' );
			$current_value = get_option( $key );

			if ( $current_value === $new_value ) {
				continue;
			}

			update_option( $key, $new_value );
			$changed = true;

			++$result['updated'];
			$result['details'][] = sprintf(
				/* translators: 1: option name, 2: old value, 3: new value */
				__( 'Updated %1$s from "%2$s" to "%3$s".', 'syncforge-config-manager' ),
				$key,
				$current_value,
				$new_value
			);
		}

		if ( $changed ) {
			flush_rewrite_rules();
		}

		return $result;
	}

	/**
	 * Get the list of configuration file paths for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Relative file paths.
	 */
	public function get_config_files(): array {
		return array( 'rewrite.yml' );
	}
}
