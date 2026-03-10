<?php
/**
 * ID Mapper — maps stable identifiers to local WordPress IDs.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class IdMapper
 *
 * Translates provider-specific stable keys (slugs) to local auto-increment IDs
 * and vice-versa. Used during import to resolve cross-environment ID differences.
 *
 * @since 1.0.0
 */
class IdMapper {

	/**
	 * Insert or update a mapping between a stable key and a local ID.
	 *
	 * If a mapping already exists for the given provider and stable_key,
	 * the local_id is updated. Otherwise a new row is inserted.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider   Provider identifier.
	 * @param string $stable_key Stable slug-based identifier.
	 * @param int    $local_id   WordPress local auto-increment ID.
	 * @return void
	 */
	public function set_mapping( string $provider, string $stable_key, int $local_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i ( provider, stable_key, local_id ) VALUES ( %s, %s, %d )
				ON DUPLICATE KEY UPDATE local_id = VALUES( local_id )',
				$this->get_table_name(),
				$provider,
				$stable_key,
				$local_id
			)
		);
	}

	/**
	 * Get the local WordPress ID for a given stable key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider   Provider identifier.
	 * @param string $stable_key Stable slug-based identifier.
	 * @return int|null Local ID or null if no mapping exists.
	 */
	public function get_local_id( string $provider, string $stable_key ): ?int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT local_id FROM %i WHERE provider = %s AND stable_key = %s',
				$this->get_table_name(),
				$provider,
				$stable_key
			)
		);

		return null !== $result ? (int) $result : null;
	}

	/**
	 * Get the stable key for a given local WordPress ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider Provider identifier.
	 * @param int    $local_id WordPress local auto-increment ID.
	 * @return string|null Stable key or null if no mapping exists.
	 */
	public function get_stable_key( string $provider, int $local_id ): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT stable_key FROM %i WHERE provider = %s AND local_id = %d',
				$this->get_table_name(),
				$provider,
				$local_id
			)
		);

		return null !== $result ? (string) $result : null;
	}

	/**
	 * Delete a single mapping by provider and stable key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider   Provider identifier.
	 * @param string $stable_key Stable slug-based identifier.
	 * @return void
	 */
	public function delete_mapping( string $provider, string $stable_key ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE provider = %s AND stable_key = %s',
				$this->get_table_name(),
				$provider,
				$stable_key
			)
		);
	}

	/**
	 * Remove all mappings for a given provider.
	 *
	 * Useful during a full re-import of a provider's configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider Provider identifier.
	 * @return void
	 */
	public function clear_provider( string $provider ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE provider = %s',
				$this->get_table_name(),
				$provider
			)
		);
	}

	/**
	 * Get the fully-prefixed table name.
	 *
	 * @since 1.0.0
	 *
	 * @return string Table name with WordPress prefix.
	 */
	private function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'config_sync_id_map';
	}
}
