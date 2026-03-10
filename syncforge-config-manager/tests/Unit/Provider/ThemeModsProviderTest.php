<?php
/**
 * Unit tests for the ThemeModsProvider class.
 *
 * @package ConfigSync\Tests\Unit\Provider
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Provider\ThemeModsProvider;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class ThemeModsProviderTest
 *
 * @since 1.0.0
 */
class ThemeModsProviderTest extends TestCase {

	/**
	 * ThemeModsProvider instance under test.
	 *
	 * @since 1.0.0
	 * @var ThemeModsProvider
	 */
	private ThemeModsProvider $provider;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->provider = new ThemeModsProvider();
	}

	/**
	 * Test that get_id returns 'theme-mods'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_id_returns_theme_mods(): void {
		$this->assertSame( 'theme-mods', $this->provider->get_id() );
	}

	/**
	 * Test that get_dependencies includes 'options'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_dependencies_includes_options(): void {
		$deps = $this->provider->get_dependencies();

		$this->assertIsArray( $deps );
		$this->assertContains( 'options', $deps );
	}

	/**
	 * Test that export returns theme stylesheet and mods.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_returns_theme_and_mods(): void {
		set_theme_mod( 'header_image', 'http://example.com/header.jpg' );
		set_theme_mod( 'background_color', 'ffffff' );

		$result = $this->provider->export();

		$this->assertArrayHasKey( '_theme', $result );
		$this->assertArrayHasKey( 'mods', $result );
		$this->assertSame( get_stylesheet(), $result['_theme'] );
		$this->assertArrayHasKey( 'header_image', $result['mods'] );
		$this->assertSame( 'http://example.com/header.jpg', $result['mods']['header_image'] );
		$this->assertArrayHasKey( 'background_color', $result['mods'] );
		$this->assertSame( 'ffffff', $result['mods']['background_color'] );
	}

	/**
	 * Test that export excludes nav_menu_locations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_excludes_nav_menu_locations(): void {
		set_theme_mod( 'nav_menu_locations', array( 'primary' => 1 ) );
		set_theme_mod( 'header_image', 'http://example.com/header.jpg' );

		$result = $this->provider->export();

		$this->assertArrayNotHasKey( 'nav_menu_locations', $result['mods'] );
		$this->assertArrayHasKey( 'header_image', $result['mods'] );
	}

	/**
	 * Test that export excludes sidebars_widgets.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_excludes_sidebars_widgets(): void {
		set_theme_mod( 'sidebars_widgets', array( 'sidebar-1' => array( 'widget_1' ) ) );
		set_theme_mod( 'background_color', '000000' );

		$result = $this->provider->export();

		$this->assertArrayNotHasKey( 'sidebars_widgets', $result['mods'] );
		$this->assertArrayHasKey( 'background_color', $result['mods'] );
	}

	/**
	 * Test that import sets theme mods from config.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_sets_theme_mods(): void {
		$config = array(
			'_theme' => get_stylesheet(),
			'mods'   => array(
				'header_image'     => 'http://example.com/new-header.jpg',
				'background_color' => 'ff0000',
			),
		);

		$result = $this->provider->import( $config );

		$this->assertSame( 'http://example.com/new-header.jpg', get_theme_mod( 'header_image' ) );
		$this->assertSame( 'ff0000', get_theme_mod( 'background_color' ) );
		$this->assertArrayHasKey( 'created', $result );
		$this->assertArrayHasKey( 'updated', $result );
		$this->assertArrayHasKey( 'deleted', $result );
		$this->assertArrayHasKey( 'details', $result );
	}

	/**
	 * Test that import warns on theme mismatch.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_warns_on_theme_mismatch(): void {
		$config = array(
			'_theme' => 'some-other-theme',
			'mods'   => array(
				'header_image' => 'http://example.com/header.jpg',
			),
		);

		$result = $this->provider->import( $config );

		$has_warning = false;
		foreach ( $result['details'] as $detail ) {
			if ( strpos( $detail, 'some-other-theme' ) !== false ) {
				$has_warning = true;
				break;
			}
		}

		$this->assertTrue( $has_warning, 'Expected a theme mismatch warning in details.' );
	}

	/**
	 * Test that import removes mods not present in config.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_removes_orphaned_mods(): void {
		// Set a mod that will not be in the incoming config.
		set_theme_mod( 'orphaned_mod', 'should-be-removed' );

		$config = array(
			'_theme' => get_stylesheet(),
			'mods'   => array(
				'header_image' => 'http://example.com/header.jpg',
			),
		);

		$result = $this->provider->import( $config );

		$this->assertFalse( get_theme_mod( 'orphaned_mod', false ), 'Orphaned mod should have been removed.' );
		$this->assertGreaterThanOrEqual( 1, $result['deleted'] );
	}

	/**
	 * Test that get_config_files returns expected file list.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_config_files_returns_theme_mods_yml(): void {
		$files = $this->provider->get_config_files();

		$this->assertSame( array( 'theme-mods.yml' ), $files );
	}

	/**
	 * Test that get_label returns a non-empty string.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_label_returns_non_empty_string(): void {
		$label = $this->provider->get_label();

		$this->assertIsString( $label );
		$this->assertNotEmpty( $label );
	}

	/**
	 * Test that get_batch_size returns 100.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_batch_size_returns_100(): void {
		$this->assertSame( 100, $this->provider->get_batch_size() );
	}

	/**
	 * Test that export excludes custom_css_post_id.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_excludes_custom_css_post_id(): void {
		set_theme_mod( 'custom_css_post_id', 42 );
		set_theme_mod( 'header_image', 'http://example.com/header.jpg' );

		$result = $this->provider->export();

		$this->assertArrayNotHasKey( 'custom_css_post_id', $result['mods'] );
		$this->assertArrayHasKey( 'header_image', $result['mods'] );
	}
}
