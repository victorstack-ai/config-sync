<?php
/**
 * Unit tests for the AuditLogger class.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\AuditLogger;
use WP_UnitTestCase;

/**
 * Class AuditLoggerTest
 *
 * @since 1.0.0
 * @covers \ConfigSync\AuditLogger
 */
class AuditLoggerTest extends WP_UnitTestCase {

	/**
	 * AuditLogger instance under test.
	 *
	 * @since 1.0.0
	 * @var AuditLogger
	 */
	private AuditLogger $logger;

	/**
	 * Set up each test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->logger = new AuditLogger();
		$this->create_audit_table();
	}

	/**
	 * Tear down each test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function tear_down(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}config_sync_audit_log" );
		parent::tear_down();
	}

	/**
	 * Create the audit log table for testing.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function create_audit_table(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}config_sync_audit_log (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				action varchar(20) NOT NULL DEFAULT '',
				provider varchar(64) NOT NULL DEFAULT '',
				environment varchar(64) NOT NULL DEFAULT '',
				diff_data longtext NOT NULL,
				snapshot_data longtext NOT NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				PRIMARY KEY  (id),
				KEY action (action),
				KEY created_at (created_at)
			) {$charset_collate};"
		);
	}

	/**
	 * Test that log_operation inserts a row and returns an ID.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_log_operation_inserts_row(): void {
		wp_set_current_user( 1 );

		$diff     = array(
			array(
				'type' => 'added',
				'key'  => 'blogname',
			),
		);
		$snapshot = array( 'blogname' => 'Test Site' );

		$id = $this->logger->log_operation( 'export', 'options', 'production', $diff, $snapshot );

		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * Test that get_snapshot returns decoded snapshot data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_snapshot_returns_decoded_data(): void {
		wp_set_current_user( 1 );

		$snapshot = array( 'blogname' => 'My Site' );
		$id       = $this->logger->log_operation( 'import', 'options', 'staging', array(), $snapshot );

		$result = $this->logger->get_snapshot( $id );

		$this->assertIsArray( $result );
		$this->assertSame( 'My Site', $result['blogname'] );
	}

	/**
	 * Test that get_snapshot returns null for a missing ID.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_snapshot_returns_null_for_missing_id(): void {
		$result = $this->logger->get_snapshot( 99999 );

		$this->assertNull( $result );
	}

	/**
	 * Test that list_snapshots respects the limit parameter.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_list_snapshots_respects_limit(): void {
		wp_set_current_user( 1 );

		for ( $i = 0; $i < 5; $i++ ) {
			$this->logger->log_operation( 'export', 'options', 'production', array(), array() );
		}

		$results = $this->logger->list_snapshots( 3, 0 );

		$this->assertCount( 3, $results );
		$this->assertArrayHasKey( 'id', $results[0] );
		$this->assertArrayHasKey( 'summary', $results[0] );
	}

	/**
	 * Test that prune deletes entries older than retention period.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_prune_deletes_old_entries(): void {
		global $wpdb;

		wp_set_current_user( 1 );

		$table = $wpdb->prefix . 'config_sync_audit_log';

		// Insert an old entry directly.
		$wpdb->insert(
			$table,
			array(
				'user_id'       => 1,
				'action'        => 'export',
				'provider'      => 'options',
				'environment'   => 'production',
				'diff_data'     => wp_json_encode( array() ),
				'snapshot_data' => wp_json_encode( array() ),
				'created_at'    => gmdate( 'Y-m-d H:i:s', strtotime( '-100 days' ) ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		// Insert a recent entry.
		$this->logger->log_operation( 'import', 'options', 'production', array(), array() );

		$deleted = $this->logger->prune( 90 );

		$this->assertSame( 1, $deleted );

		// Verify only the recent entry remains.
		$remaining = $this->logger->list_snapshots( 100, 0 );
		$this->assertCount( 1, $remaining );
	}

	/**
	 * Test that prune applies the retention filter.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_prune_applies_retention_filter(): void {
		global $wpdb;

		wp_set_current_user( 1 );

		$table = $wpdb->prefix . 'config_sync_audit_log';

		// Insert an entry that is 50 days old.
		$wpdb->insert(
			$table,
			array(
				'user_id'       => 1,
				'action'        => 'export',
				'provider'      => 'options',
				'environment'   => 'production',
				'diff_data'     => wp_json_encode( array() ),
				'snapshot_data' => wp_json_encode( array() ),
				'created_at'    => gmdate( 'Y-m-d H:i:s', strtotime( '-50 days' ) ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		// Filter to reduce retention to 30 days.
		add_filter(
			'config_sync_audit_retention_days',
			function () {
				return 30;
			}
		);

		$deleted = $this->logger->prune( 90 );

		$this->assertSame( 1, $deleted );

		remove_all_filters( 'config_sync_audit_retention_days' );
	}
}
