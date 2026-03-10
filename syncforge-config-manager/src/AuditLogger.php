<?php
/**
 * Audit logger for tracking configuration operations.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync;

use ConfigSync\Sanitizer\YamlSanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AuditLogger
 *
 * Records export, import, and rollback operations with diff and snapshot
 * data for auditability and rollback support.
 *
 * @since 1.0.0
 */
class AuditLogger {

	/**
	 * Log a configuration operation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action      The action performed: 'export', 'import', or 'rollback'.
	 * @param string $provider_id The provider ID or 'all'.
	 * @param string $environment The environment name.
	 * @param array  $diff        The diff data to record.
	 * @param array  $snapshot    The pre-operation snapshot data.
	 * @return int The inserted row ID.
	 */
	public function log_operation( string $action, string $provider_id, string $environment, array $diff, array $snapshot ): int {
		global $wpdb;

		// Redact secrets before storing to avoid persisting credentials.
		$diff     = YamlSanitizer::redact_for_audit( $diff );
		$snapshot = YamlSanitizer::redact_for_audit( $snapshot );

		$table = $wpdb->prefix . 'config_sync_audit_log';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			$table,
			array(
				'user_id'       => get_current_user_id(),
				'action'        => $action,
				'provider'      => $provider_id,
				'environment'   => $environment,
				'diff_data'     => wp_json_encode( $diff ),
				'snapshot_data' => wp_json_encode( $snapshot ),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get the snapshot data for a specific audit log entry.
	 *
	 * @since 1.0.0
	 *
	 * @param int $log_id The audit log row ID.
	 * @return array|null Decoded snapshot data or null if not found.
	 */
	public function get_snapshot( int $log_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT snapshot_data FROM %i WHERE id = %d',
				$wpdb->prefix . 'config_sync_audit_log',
				$log_id
			)
		);

		if ( null === $row ) {
			return null;
		}

		$data = json_decode( $row->snapshot_data, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		return $data;
	}

	/**
	 * List audit log snapshots with pagination.
	 *
	 * @since 1.0.0
	 *
	 * @param int $limit  Maximum number of rows to return. Default 20.
	 * @param int $offset Number of rows to skip. Default 0.
	 * @return array Array of audit log entries with summary data.
	 */
	public function list_snapshots( int $limit = 20, int $offset = 0 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, user_id, action, provider, environment, diff_data, created_at FROM %i ORDER BY created_at DESC LIMIT %d OFFSET %d',
				$wpdb->prefix . 'config_sync_audit_log',
				$limit,
				$offset
			)
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$results = array();

		foreach ( $rows as $row ) {
			$diff    = json_decode( $row->diff_data, true );
			$summary = $this->summarize_diff( is_array( $diff ) ? $diff : array() );

			$results[] = array(
				'id'          => (int) $row->id,
				'user_id'     => (int) $row->user_id,
				'action'      => $row->action,
				'provider'    => $row->provider,
				'environment' => $row->environment,
				'created_at'  => $row->created_at,
				'summary'     => $summary,
			);
		}

		return $results;
	}

	/**
	 * Delete audit log entries older than the retention period.
	 *
	 * @since 1.0.0
	 *
	 * @param int $retention_days Number of days to retain entries. Default 90.
	 * @return int Number of rows deleted.
	 */
	public function prune( int $retention_days = 90 ): int {
		global $wpdb;

		/**
		 * Filters the number of days to retain audit log entries.
		 *
		 * @since 1.0.0
		 *
		 * @param int $retention_days Number of days to keep entries.
		 */
		$retention_days = apply_filters( 'config_sync_audit_retention_days', $retention_days );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE created_at < DATE_SUB(%s, INTERVAL %d DAY)',
				$wpdb->prefix . 'config_sync_audit_log',
				current_time( 'mysql', true ),
				$retention_days
			)
		);

		return (int) $wpdb->rows_affected;
	}

	/**
	 * Summarize a diff array into a human-readable string.
	 *
	 * @since 1.0.0
	 *
	 * @param array $diff The diff data array.
	 * @return string Summary of changes.
	 */
	private function summarize_diff( array $diff ): string {
		$added    = 0;
		$modified = 0;
		$removed  = 0;

		foreach ( $diff as $item ) {
			if ( ! isset( $item['type'] ) ) {
				continue;
			}

			switch ( $item['type'] ) {
				case 'added':
					++$added;
					break;
				case 'modified':
					++$modified;
					break;
				case 'removed':
					++$removed;
					break;
			}
		}

		$parts = array();

		if ( $added > 0 ) {
			/* translators: %d: number of items added */
			$parts[] = sprintf( _n( '%d added', '%d added', $added, 'syncforge-config-manager' ), $added );
		}

		if ( $modified > 0 ) {
			/* translators: %d: number of items modified */
			$parts[] = sprintf( _n( '%d modified', '%d modified', $modified, 'syncforge-config-manager' ), $modified );
		}

		if ( $removed > 0 ) {
			/* translators: %d: number of items removed */
			$parts[] = sprintf( _n( '%d removed', '%d removed', $removed, 'syncforge-config-manager' ), $removed );
		}

		if ( empty( $parts ) ) {
			return __( 'No changes', 'syncforge-config-manager' );
		}

		return implode( ', ', $parts );
	}
}
