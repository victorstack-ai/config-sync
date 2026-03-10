<?php
/**
 * Security tests for YAML injection protection in FileHandler.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Security;

use ConfigSync\FileHandler;
use WP_UnitTestCase;

/**
 * Class YamlInjectionTest
 *
 * Verifies that FileHandler safely parses YAML without allowing
 * PHP object injection or invalid types.
 *
 * @since 1.0.0
 */
class YamlInjectionTest extends WP_UnitTestCase {

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

		$this->config_dir = sys_get_temp_dir() . '/syncforge-yaml-' . uniqid() . '/';
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
	 * Test that YAML containing a PHP object tag is rejected.
	 *
	 * @since 1.0.0
	 */
	public function test_rejects_php_object_yaml_tag(): void {
		global $wp_filesystem;

		$malicious_yaml = "exploit: !php/object 'O:8:\"stdClass\":0:{}'\n";
		$file_path      = $this->config_dir . 'malicious.yml';

		$wp_filesystem->put_contents( $file_path, $malicious_yaml, FS_CHMOD_FILE );

		$this->expectException( \RuntimeException::class );
		$this->handler->read( 'malicious.yml' );
	}

	/**
	 * Test that YAML with invalid types triggers an exception.
	 *
	 * @since 1.0.0
	 */
	public function test_rejects_invalid_yaml_types(): void {
		global $wp_filesystem;

		// Binary data tag should cause PARSE_EXCEPTION_ON_INVALID_TYPE to fire.
		$invalid_yaml = "data: !!binary |\n  R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7\n";
		$file_path    = $this->config_dir . 'invalid-type.yml';

		$wp_filesystem->put_contents( $file_path, $invalid_yaml, FS_CHMOD_FILE );

		$this->expectException( \RuntimeException::class );
		$this->handler->read( 'invalid-type.yml' );
	}

	/**
	 * Test that valid YAML is parsed correctly and safely.
	 *
	 * @since 1.0.0
	 */
	public function test_parses_valid_yaml_safely(): void {
		$data = array(
			'site_title'  => 'My Safe Site',
			'description' => 'A simple test',
			'settings'    => array(
				'timezone'    => 'UTC',
				'date_format' => 'Y-m-d',
			),
		);

		$this->handler->write( 'safe.yml', $data );
		$result = $this->handler->read( 'safe.yml' );

		$this->assertSame( $data, $result );
		$this->assertIsArray( $result );
		$this->assertSame( 'My Safe Site', $result['site_title'] );
	}
}
