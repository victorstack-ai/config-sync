<?php
/**
 * Mutex lock for preventing concurrent operations.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lock
 *
 * Uses wp_options as a lightweight mutex to prevent concurrent
 * import/export operations.
 *
 * @since 1.0.0
 */
class Lock {

	/**
	 * Option key used for the lock.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const LOCK_KEY = 'config_sync_lock';

	/**
	 * Maximum lock age in seconds before it is considered stale.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const STALE_THRESHOLD = 300;

	/**
	 * Acquire the lock for a given operation.
	 *
	 * Uses INSERT … ON DUPLICATE KEY UPDATE on wp_options to atomically
	 * claim the lock. If the existing lock is stale (older than 5 minutes),
	 * it will be reclaimed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $operation The operation name (e.g. 'import', 'export').
	 * @return bool True if the lock was acquired, false if locked by someone else.
	 */
	public function acquire( string $operation ): bool {
		global $wpdb;

		$current_user_id = get_current_user_id();
		$lock_value      = wp_json_encode(
			array(
				'user_id'   => $current_user_id,
				'operation' => $operation,
				'time'      => time(),
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')
				ON DUPLICATE KEY UPDATE option_value = IF(
					option_value = '' OR option_value IS NULL,
					VALUES(option_value),
					option_value
				)",
				self::LOCK_KEY,
				$lock_value
			)
		);

		// Check if we own the lock.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stored = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				self::LOCK_KEY
			)
		);

		if ( empty( $stored ) ) {
			return false;
		}

		$stored_data = json_decode( $stored, true );

		if ( ! is_array( $stored_data ) ) {
			return false;
		}

		// We own the lock.
		if ( isset( $stored_data['user_id'] ) && (int) $stored_data['user_id'] === $current_user_id ) {
			return true;
		}

		// Check if the lock is stale and reclaim it.
		if ( isset( $stored_data['time'] ) && ( time() - (int) $stored_data['time'] ) > self::STALE_THRESHOLD ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
					$lock_value,
					self::LOCK_KEY,
					$stored
				)
			);

			// Verify we reclaimed it.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$reclaimed = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
					self::LOCK_KEY
				)
			);

			$reclaimed_data = json_decode( $reclaimed, true );

			return is_array( $reclaimed_data )
				&& isset( $reclaimed_data['user_id'] )
				&& (int) $reclaimed_data['user_id'] === $current_user_id;
		}

		return false;
	}

	/**
	 * Release the lock.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function release(): void {
		update_option( self::LOCK_KEY, '' );
	}

	/**
	 * Check if the lock is currently held and not stale.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the lock is active, false otherwise.
	 */
	public function is_locked(): bool {
		$info = $this->get_lock_info();

		if ( null === $info ) {
			return false;
		}

		if ( ! isset( $info['time'] ) ) {
			return false;
		}

		// Stale locks are not considered locked.
		if ( ( time() - (int) $info['time'] ) > self::STALE_THRESHOLD ) {
			return false;
		}

		return true;
	}

	/**
	 * Get information about the current lock.
	 *
	 * @since 1.0.0
	 *
	 * @return array|null Array with user_id, operation, time or null if no lock.
	 */
	public function get_lock_info(): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				self::LOCK_KEY
			)
		);

		if ( empty( $value ) ) {
			return null;
		}

		$data = json_decode( $value, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		return $data;
	}
}
