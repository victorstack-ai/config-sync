<?php
/**
 * Unit tests for the OptionsProvider class.
 *
 * @package ConfigSync\Tests\Unit\Provider
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Provider\OptionsProvider;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class OptionsProviderTest
 *
 * @since 1.0.0
 */
class OptionsProviderTest extends TestCase {

	/**
	 * OptionsProvider instance under test.
	 *
	 * @since 1.0.0
	 * @var OptionsProvider
	 */
	private OptionsProvider $provider;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->provider = new OptionsProvider();
	}

	/**
	 * Test that get_id returns 'options'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_id_returns_options(): void {
		$this->assertSame( 'options', $this->provider->get_id() );
	}

	/**
	 * Test that get_label returns the translated string.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_label_returns_translated_string(): void {
		$this->assertSame( 'Options', $this->provider->get_label() );
	}

	/**
	 * Test that get_dependencies returns an empty array.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_dependencies_returns_empty(): void {
		$this->assertSame( array(), $this->provider->get_dependencies() );
	}

	/**
	 * Test that get_batch_size returns 200.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_batch_size_returns_200(): void {
		$this->assertSame( 200, $this->provider->get_batch_size() );
	}

	/**
	 * Test that export returns a grouped structure.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_returns_grouped_structure(): void {
		// Seed some core options that are tracked.
		update_option( 'blogname', 'Test Site' );
		update_option( 'blogdescription', 'Just another WordPress site' );
		update_option( 'posts_per_page', 10 );

		$result = $this->provider->export();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'general', $result );
		$this->assertArrayHasKey( 'blogname', $result['general'] );
		$this->assertSame( 'Test Site', $result['general']['blogname'] );
	}

	/**
	 * Test that export excludes transients.
	 *
	 * Transients match the wildcard pattern transient_* and should never
	 * appear in the exported data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_excludes_transients(): void {
		// Even if somehow a transient were in the option_groups,
		// the exclusion filter would remove it.  We verify via the
		// config_sync_tracked_options filter.
		add_filter(
			'config_sync_tracked_options',
			function () {
				return array( '_transient_test_value' );
			}
		);

		$result = $this->provider->export();

		if ( isset( $result['extra'] ) ) {
			$this->assertArrayNotHasKey( '_transient_test_value', $result['extra'] );
		} else {
			// Extra group was not created, meaning the transient was excluded.
			$this->assertArrayNotHasKey( 'extra', $result );
		}

		remove_all_filters( 'config_sync_tracked_options' );
	}

	/**
	 * Test that export excludes config_sync_ prefixed options.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_excludes_config_sync_options(): void {
		update_option( 'config_sync_settings', array( 'key' => 'value' ) );

		add_filter(
			'config_sync_tracked_options',
			function () {
				return array( 'config_sync_settings' );
			}
		);

		$result = $this->provider->export();

		if ( isset( $result['extra'] ) ) {
			$this->assertArrayNotHasKey( 'config_sync_settings', $result['extra'] );
		} else {
			$this->assertArrayNotHasKey( 'extra', $result );
		}

		remove_all_filters( 'config_sync_tracked_options' );
		delete_option( 'config_sync_settings' );
	}

	/**
	 * Test that import updates options and returns a change summary.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_updates_options(): void {
		update_option( 'blogname', 'Old Name' );

		$config = array(
			'general' => array(
				'blogname' => 'New Name',
			),
		);

		$result = $this->provider->import( $config );

		$this->assertSame( 'New Name', get_option( 'blogname' ) );
		$this->assertSame( 1, $result['updated'] );
	}

	/**
	 * Test that import returns the correct change summary structure.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_returns_change_summary(): void {
		// Ensure option does not exist so it gets created.
		delete_option( 'config_sync_test_import_option' );

		$config = array(
			'general' => array(
				'config_sync_test_import_option' => 'hello',
			),
		);

		$result = $this->provider->import( $config );

		$this->assertArrayHasKey( 'created', $result );
		$this->assertArrayHasKey( 'updated', $result );
		$this->assertArrayHasKey( 'deleted', $result );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertIsArray( $result['details'] );
		$this->assertSame( 1, $result['created'] );

		delete_option( 'config_sync_test_import_option' );
	}

	/**
	 * Test that get_config_files returns all group file paths.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_config_files_returns_all_groups(): void {
		$files = $this->provider->get_config_files();

		$this->assertCount( 6, $files );
		$this->assertContains( 'options/general.yml', $files );
		$this->assertContains( 'options/reading.yml', $files );
		$this->assertContains( 'options/writing.yml', $files );
		$this->assertContains( 'options/discussion.yml', $files );
		$this->assertContains( 'options/media.yml', $files );
		$this->assertContains( 'options/permalinks.yml', $files );
	}

	/**
	 * Test that the config_sync_excluded_options filter is applied during export.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_excluded_options_filter_is_applied(): void {
		update_option( 'blogname', 'Filter Test' );
		update_option( 'blogdescription', 'Should be excluded' );

		add_filter(
			'config_sync_excluded_options',
			function ( $exclusions ) {
				$exclusions[] = 'blogdescription';
				return $exclusions;
			}
		);

		$result = $this->provider->export();

		$this->assertArrayHasKey( 'blogname', $result['general'] );
		$this->assertArrayNotHasKey( 'blogdescription', $result['general'] );

		remove_all_filters( 'config_sync_excluded_options' );
	}

	/**
	 * Test that the config_sync_tracked_options filter adds extra options.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_tracked_options_filter_adds_extra(): void {
		update_option( 'my_custom_plugin_option', 'custom_value' );

		add_filter(
			'config_sync_tracked_options',
			function () {
				return array( 'my_custom_plugin_option' );
			}
		);

		$result = $this->provider->export();

		$this->assertArrayHasKey( 'extra', $result );
		$this->assertArrayHasKey( 'my_custom_plugin_option', $result['extra'] );
		$this->assertSame( 'custom_value', $result['extra']['my_custom_plugin_option'] );

		remove_all_filters( 'config_sync_tracked_options' );
		delete_option( 'my_custom_plugin_option' );
	}
}
