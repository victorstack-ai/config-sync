<?php
/**
 * Unit tests for the Lock class.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Lock;
use WP_UnitTestCase;

/**
 * Class LockTest
 *
 * @since 1.0.0
 * @covers \ConfigSync\Lock
 */
class LockTest extends WP_UnitTestCase {

	/**
	 * Lock instance under test.
	 *
	 * @since 1.0.0
	 * @var Lock
	 */
	private Lock $lock;

	/**
	 * Set up each test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->lock = new Lock();
		delete_option( 'config_sync_lock' );
	}

	/**
	 * Tear down each test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function tear_down(): void {
		delete_option( 'config_sync_lock' );
		parent::tear_down();
	}

	/**
	 * Test that acquire returns true when no lock is held.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_acquire_returns_true_when_unlocked(): void {
		wp_set_current_user( 1 );

		$result = $this->lock->acquire( 'import' );

		$this->assertTrue( $result );
	}

	/**
	 * Test that release clears the lock.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_release_clears_lock(): void {
		wp_set_current_user( 1 );
		$this->lock->acquire( 'import' );

		$this->lock->release();

		$this->assertFalse( $this->lock->is_locked() );
		$this->assertNull( $this->lock->get_lock_info() );
	}

	/**
	 * Test that is_locked returns false when no lock exists.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_is_locked_returns_false_when_no_lock(): void {
		$this->assertFalse( $this->lock->is_locked() );
	}

	/**
	 * Test that get_lock_info returns null when no lock exists.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_lock_info_returns_null_when_no_lock(): void {
		$this->assertNull( $this->lock->get_lock_info() );
	}

	/**
	 * Test that get_lock_info returns an array when a lock is held.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_lock_info_returns_array_when_locked(): void {
		wp_set_current_user( 1 );
		$this->lock->acquire( 'export' );

		$info = $this->lock->get_lock_info();

		$this->assertIsArray( $info );
		$this->assertArrayHasKey( 'user_id', $info );
		$this->assertArrayHasKey( 'operation', $info );
		$this->assertArrayHasKey( 'time', $info );
		$this->assertSame( 1, $info['user_id'] );
		$this->assertSame( 'export', $info['operation'] );
	}

	/**
	 * Test that a stale lock (older than 5 minutes) is reclaimed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_stale_lock_is_reclaimed(): void {
		// Simulate a stale lock from another user.
		$stale_value = wp_json_encode(
			array(
				'user_id'   => 999,
				'operation' => 'import',
				'time'      => time() - 600, // 10 minutes ago.
			)
		);
		update_option( 'config_sync_lock', $stale_value );

		wp_set_current_user( 1 );

		$result = $this->lock->acquire( 'export' );

		$this->assertTrue( $result );

		$info = $this->lock->get_lock_info();
		$this->assertSame( 1, $info['user_id'] );
		$this->assertSame( 'export', $info['operation'] );
	}
}
