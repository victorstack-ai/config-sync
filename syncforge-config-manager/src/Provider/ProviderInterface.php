<?php
/**
 * Provider interface.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface ProviderInterface
 *
 * Defines the contract that every configuration provider must implement.
 *
 * @since 1.0.0
 */
interface ProviderInterface {

	/**
	 * Get the unique identifier slug for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string Unique lowercase slug, e.g. 'options'.
	 */
	public function get_id(): string;

	/**
	 * Get the human-readable translated label for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string Translated provider name.
	 */
	public function get_label(): string;

	/**
	 * Get the IDs of providers this provider depends on.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of provider ID strings.
	 */
	public function get_dependencies(): array;

	/**
	 * Export configuration from the database.
	 *
	 * Reads the current configuration state from WordPress and returns it
	 * as an associative array keyed by stable identifiers.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Configuration data keyed by stable identifiers.
	 */
	public function export(): array;

	/**
	 * Import configuration into the database.
	 *
	 * Writes the given configuration data to WordPress, creating, updating,
	 * or deleting entries as needed.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Configuration data to import.
	 * @return array{created: int, updated: int, deleted: int, details: string[]} Import result summary.
	 */
	public function import( array $config ): array;

	/**
	 * Preview changes without applying them.
	 *
	 * Computes the diff between the current database state and the given
	 * configuration without making any modifications.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Configuration data to preview.
	 * @return array Structured diff of changes that would be applied.
	 */
	public function dry_run( array $config ): array;

	/**
	 * Get the relative YAML file paths for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of relative YAML file paths, e.g. array( 'options/general.yml' ).
	 */
	public function get_config_files(): array;

	/**
	 * Validate configuration data against the provider schema.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Configuration data to validate.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public function validate( array $config );

	/**
	 * Get the number of items to process per batch iteration.
	 *
	 * @since 1.0.0
	 *
	 * @return int Items per batch.
	 */
	public function get_batch_size(): int;
}
