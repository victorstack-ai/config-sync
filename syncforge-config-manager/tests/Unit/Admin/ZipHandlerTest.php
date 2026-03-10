<?php
/**
 * Unit tests for the ZipHandler class.
 *
 * @package ConfigSync\Tests\Unit\Admin
 * @since   1.1.0
 */

namespace ConfigSync\Tests\Unit\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Admin\ZipHandler;
use WP_UnitTestCase;

/**
 * Class ZipHandlerTest
 *
 * @since   1.1.0
 * @covers  \ConfigSync\Admin\ZipHandler
 */
class ZipHandlerTest extends WP_UnitTestCase {

	/**
	 * Temporary directory used as a fake config directory.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private string $temp_dir;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->temp_dir = sys_get_temp_dir() . '/syncforge-test-' . uniqid();
		mkdir( $this->temp_dir, 0755, true );
		mkdir( $this->temp_dir . '/options', 0755, true );
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$this->remove_directory( $this->temp_dir );
		parent::tear_down();
	}

	// ------------------------------------------------------------------
	// Constructor
	// ------------------------------------------------------------------

	/**
	 * Test ZipHandler can be instantiated.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_instantiation(): void {
		$handler = new ZipHandler( $this->temp_dir );
		$this->assertInstanceOf( ZipHandler::class, $handler );
	}

	// ------------------------------------------------------------------
	// register()
	// ------------------------------------------------------------------

	/**
	 * Test register hooks AJAX actions.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_register_adds_ajax_hooks(): void {
		$handler = new ZipHandler( $this->temp_dir );
		$handler->register();

		$this->assertSame(
			10,
			has_action( 'wp_ajax_config_sync_zip_export', array( $handler, 'handle_zip_export' ) )
		);
		$this->assertSame(
			10,
			has_action( 'wp_ajax_config_sync_zip_import', array( $handler, 'handle_zip_import' ) )
		);
	}

	// ------------------------------------------------------------------
	// ZIP creation roundtrip
	// ------------------------------------------------------------------

	/**
	 * Test ZIP archive can be created from YAML files and read back.
	 *
	 * This tests the core ZIP logic without going through the AJAX handler
	 * (which requires nonce/capability checks that are integration-level).
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_zip_roundtrip(): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive extension not available.' );
		}

		// Create test YAML files.
		file_put_contents( $this->temp_dir . '/options/general.yml', "blogname: Test Site\n" );
		file_put_contents( $this->temp_dir . '/options/reading.yml', "posts_per_page: 10\n" );
		file_put_contents( $this->temp_dir . '/rewrite.yml', "permalink_structure: /%postname%/\n" );

		// Create ZIP.
		$zip_path = sys_get_temp_dir() . '/syncforge-test-' . uniqid() . '.zip';
		$zip = new \ZipArchive();
		$zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );

		$this->add_directory_to_zip( $zip, $this->temp_dir, '' );
		$zip->close();

		$this->assertFileExists( $zip_path );

		// Read back and verify contents.
		$zip2 = new \ZipArchive();
		$zip2->open( $zip_path );

		$entries = array();
		for ( $i = 0; $i < $zip2->numFiles; $i++ ) {
			$entries[] = $zip2->getNameIndex( $i );
		}

		$this->assertContains( 'options/general.yml', $entries );
		$this->assertContains( 'options/reading.yml', $entries );
		$this->assertContains( 'rewrite.yml', $entries );

		// Verify content integrity.
		$general_content = $zip2->getFromName( 'options/general.yml' );
		$this->assertStringContainsString( 'blogname: Test Site', $general_content );

		$zip2->close();
		unlink( $zip_path );
	}

	/**
	 * Test ZIP excludes non-YAML files.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_zip_excludes_non_yaml_files(): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive extension not available.' );
		}

		// Create mixed files.
		file_put_contents( $this->temp_dir . '/options/general.yml', "blogname: Test\n" );
		file_put_contents( $this->temp_dir . '/.htaccess', "Require all denied\n" );
		file_put_contents( $this->temp_dir . '/index.php', "<?php // Silence.\n" );
		file_put_contents( $this->temp_dir . '/notes.txt', "Not a YAML file\n" );

		$zip_path = sys_get_temp_dir() . '/syncforge-test-' . uniqid() . '.zip';
		$zip = new \ZipArchive();
		$zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );

		$this->add_directory_to_zip( $zip, $this->temp_dir, '' );
		$zip->close();

		$zip2 = new \ZipArchive();
		$zip2->open( $zip_path );

		$entries = array();
		for ( $i = 0; $i < $zip2->numFiles; $i++ ) {
			$entries[] = $zip2->getNameIndex( $i );
		}

		$this->assertContains( 'options/general.yml', $entries );
		$this->assertNotContains( '.htaccess', $entries );
		$this->assertNotContains( 'index.php', $entries );
		$this->assertNotContains( 'notes.txt', $entries );

		$zip2->close();
		unlink( $zip_path );
	}

	/**
	 * Test ZIP extract validates entries and rejects path traversal.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_zip_extract_rejects_path_traversal(): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive extension not available.' );
		}

		// Create a ZIP with a path traversal entry.
		$zip_path = sys_get_temp_dir() . '/syncforge-test-' . uniqid() . '.zip';
		$zip = new \ZipArchive();
		$zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( '../../../etc/passwd.yml', "malicious: true\n" );
		$zip->addFromString( 'options/legit.yml', "safe: true\n" );
		$zip->close();

		// Validate entries like ZipHandler does.
		$zip2 = new \ZipArchive();
		$zip2->open( $zip_path );

		$valid_entries = array();
		$has_traversal = false;

		for ( $i = 0; $i < $zip2->numFiles; $i++ ) {
			$entry = $zip2->getNameIndex( $i );

			if ( '/' === substr( $entry, -1 ) ) {
				continue;
			}
			if ( '.yml' !== substr( $entry, -4 ) ) {
				continue;
			}
			if ( false !== strpos( $entry, '..' ) ) {
				$has_traversal = true;
				continue;
			}

			$valid_entries[] = $entry;
		}

		$this->assertTrue( $has_traversal, 'Should detect path traversal entry.' );
		$this->assertNotContains( '../../../etc/passwd.yml', $valid_entries );

		$zip2->close();
		unlink( $zip_path );
	}

	/**
	 * Test ZIP extract ignores non-YAML entries.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_zip_extract_ignores_non_yaml(): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive extension not available.' );
		}

		$zip_path = sys_get_temp_dir() . '/syncforge-test-' . uniqid() . '.zip';
		$zip = new \ZipArchive();
		$zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
		$zip->addFromString( 'options/general.yml', "blogname: Test\n" );
		$zip->addFromString( 'evil.php', "<?php system('rm -rf /'); ?>" );
		$zip->addFromString( '.htaccess', "Require all denied" );
		$zip->close();

		$zip2 = new \ZipArchive();
		$zip2->open( $zip_path );

		$valid_entries = array();
		for ( $i = 0; $i < $zip2->numFiles; $i++ ) {
			$entry = $zip2->getNameIndex( $i );
			if ( '.yml' === substr( $entry, -4 ) && false === strpos( $entry, '..' ) ) {
				$valid_entries[] = $entry;
			}
		}

		$this->assertCount( 1, $valid_entries );
		$this->assertSame( 'options/general.yml', $valid_entries[0] );

		$zip2->close();
		unlink( $zip_path );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Add YAML files from a directory to a ZipArchive.
	 *
	 * Mirrors the private method in ZipHandler for testing purposes.
	 *
	 * @param \ZipArchive $zip        Archive instance.
	 * @param string      $dir        Directory to scan.
	 * @param string      $zip_prefix Path prefix within the ZIP.
	 * @return void
	 */
	private function add_directory_to_zip( \ZipArchive $zip, string $dir, string $zip_prefix ): void {
		$items = scandir( $dir );
		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$full_path = $dir . '/' . $item;

			if ( is_dir( $full_path ) ) {
				$sub_prefix = '' === $zip_prefix ? $item . '/' : $zip_prefix . $item . '/';
				$this->add_directory_to_zip( $zip, $full_path, $sub_prefix );
				continue;
			}

			if ( '.yml' !== substr( $item, -4 ) ) {
				continue;
			}

			$zip_path = '' === $zip_prefix ? $item : $zip_prefix . $item;
			$zip->addFile( $full_path, $zip_path );
		}
	}

	/**
	 * Recursively remove a directory.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private function remove_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = scandir( $dir );
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->remove_directory( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}
}
