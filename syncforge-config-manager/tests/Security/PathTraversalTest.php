<?php
/**
 * Security tests for path traversal protection in FileHandler.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Security;

use ConfigSync\FileHandler;
use WP_UnitTestCase;

/**
 * Class PathTraversalTest
 *
 * Verifies that FileHandler rejects all path traversal attempts.
 *
 * @since 1.0.0
 */
class PathTraversalTest extends WP_UnitTestCase {

	/**
	 * Temporary config directory for tests.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $config_dir;

	/**
	 * FileHandler instance under test.
	 *
	 * @since 1.0.0
	 * @var FileHandler
	 */
	private FileHandler $handler;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.0.0
	 */
	public function set_up(): void {
		parent::set_up();

		$this->config_dir = sys_get_temp_dir() . '/syncforge-security-' . uniqid() . '/';
		wp_mkdir_p( $this->config_dir );

		$this->handler = new FileHandler( $this->config_dir );

		// Ensure WP_Filesystem is initialized.
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		\WP_Filesystem( false, false, true );
		if ( ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
			$wp_filesystem = new \WP_Filesystem_Direct( null );
		}
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @since 1.0.0
	 */
	public function tear_down(): void {
		global $wp_filesystem;
		if ( $wp_filesystem instanceof \WP_Filesystem_Base && $wp_filesystem->is_dir( $this->config_dir ) ) {
			$wp_filesystem->delete( $this->config_dir, true );
		}

		parent::tear_down();
	}

	/**
	 * Test that ../ path traversal is rejected.
	 *
	 * @since 1.0.0
	 */
	public function test_rejects_dot_dot_slash(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->handler->read( '../../../etc/passwd' );
	}

	/**
	 * Test that ..\ path traversal (backslash) is rejected.
	 *
	 * @since 1.0.0
	 */
	public function test_rejects_dot_dot_backslash(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->handler->read( '..\\..\\etc\\passwd' );
	}

	/**
	 * Test that URL-encoded path traversal is rejected.
	 *
	 * @since 1.0.0
	 */
	public function test_rejects_encoded_traversal(): void {
		$this->expectException( \InvalidArgumentException::class );
		$this->handler->read( '%2e%2e%2fetc%2fpasswd' );
	}

	/**
	 * Test that a symlink escaping the config directory is rejected.
	 *
	 * @since 1.0.0
	 */
	public function test_rejects_symlink_escape(): void {
		$link_path  = $this->config_dir . 'evil-link';
		$target_dir = sys_get_temp_dir();

		// Create a symlink pointing outside the config directory.
		if ( ! @symlink( $target_dir, $link_path ) ) {
			$this->markTestSkipped( 'Could not create symlink for testing.' );
		}

		$this->expectException( \InvalidArgumentException::class );
		$this->handler->read( 'evil-link' );
	}

	/**
	 * Test that a valid nested path within config dir is accepted.
	 *
	 * @since 1.0.0
	 */
	public function test_accepts_valid_nested_path(): void {
		$data = array( 'option' => 'value' );

		$result = $this->handler->write( 'providers/options/general.yml', $data );
		$this->assertTrue( $result );

		$read = $this->handler->read( 'providers/options/general.yml' );
		$this->assertSame( $data, $read );
	}
}
