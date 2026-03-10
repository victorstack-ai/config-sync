<?php
/**
 * Unit tests for the FileHandler class.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit;

use ConfigSync\FileHandler;
use WP_UnitTestCase;

/**
 * Class FileHandlerTest
 *
 * Tests for FileHandler including reading, writing, existence checks,
 * and secure directory creation.
 *
 * @since 1.0.0
 */
class FileHandlerTest extends WP_UnitTestCase {

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

		$this->config_dir = sys_get_temp_dir() . '/syncforge-test-' . uniqid() . '/';
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
		// Clean up temporary directory.
		global $wp_filesystem;
		if ( $wp_filesystem instanceof \WP_Filesystem_Base && $wp_filesystem->is_dir( $this->config_dir ) ) {
			$wp_filesystem->delete( $this->config_dir, true );
		}

		parent::tear_down();
	}

	/**
	 * Test that read() returns an empty array when the file does not exist.
	 *
	 * @since 1.0.0
	 */
	public function test_read_returns_empty_array_for_missing_file(): void {
		$result = $this->handler->read( 'nonexistent.yml' );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test writing and reading a YAML file round-trips correctly.
	 *
	 * @since 1.0.0
	 */
	public function test_write_and_read_round_trip(): void {
		$data = array(
			'site_title'  => 'Test Site',
			'admin_email' => 'admin@example.com',
			'nested'      => array(
				'key1' => 'value1',
				'key2' => 42,
			),
		);

		$write_result = $this->handler->write( 'options/general.yml', $data );
		$this->assertTrue( $write_result );

		$read_result = $this->handler->read( 'options/general.yml' );
		$this->assertSame( $data, $read_result );
	}

	/**
	 * Test that exists() returns true for an existing file.
	 *
	 * @since 1.0.0
	 */
	public function test_exists_returns_true_for_existing_file(): void {
		$this->handler->write( 'test.yml', array( 'key' => 'value' ) );
		$this->assertTrue( $this->handler->exists( 'test.yml' ) );
	}

	/**
	 * Test that exists() returns false for a missing file.
	 *
	 * @since 1.0.0
	 */
	public function test_exists_returns_false_for_missing_file(): void {
		$this->assertFalse( $this->handler->exists( 'nonexistent.yml' ) );
	}

	/**
	 * Test that create_secure_directory() creates .htaccess.
	 *
	 * @since 1.0.0
	 */
	public function test_create_secure_directory_creates_htaccess(): void {
		$this->handler->create_secure_directory();

		global $wp_filesystem;
		$this->assertTrue( $wp_filesystem->exists( $this->config_dir . '.htaccess' ) );

		$contents = $wp_filesystem->get_contents( $this->config_dir . '.htaccess' );
		$this->assertStringContainsString( 'Require all denied', $contents );
		$this->assertStringContainsString( 'Deny from all', $contents );
	}

	/**
	 * Test that create_secure_directory() creates index.php.
	 *
	 * @since 1.0.0
	 */
	public function test_create_secure_directory_creates_index_php(): void {
		$this->handler->create_secure_directory();

		global $wp_filesystem;
		$this->assertTrue( $wp_filesystem->exists( $this->config_dir . 'index.php' ) );

		$contents = $wp_filesystem->get_contents( $this->config_dir . 'index.php' );
		$this->assertStringContainsString( 'Silence is golden', $contents );
	}

	/**
	 * Test that create_secure_directory() creates web.config.
	 *
	 * @since 1.0.0
	 */
	public function test_create_secure_directory_creates_web_config(): void {
		$this->handler->create_secure_directory();

		global $wp_filesystem;
		$this->assertTrue( $wp_filesystem->exists( $this->config_dir . 'web.config' ) );

		$contents = $wp_filesystem->get_contents( $this->config_dir . 'web.config' );
		$this->assertStringContainsString( 'deny users="*"', $contents );
	}

	/**
	 * Test that create_secure_directory() creates .gitignore with secrets.yml.
	 *
	 * @since 1.0.0
	 */
	public function test_create_secure_directory_creates_gitignore(): void {
		$this->handler->create_secure_directory();

		global $wp_filesystem;
		$this->assertTrue( $wp_filesystem->exists( $this->config_dir . '.gitignore' ) );

		$contents = $wp_filesystem->get_contents( $this->config_dir . '.gitignore' );
		$this->assertStringContainsString( 'secrets.yml', $contents );
	}
}
