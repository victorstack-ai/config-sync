<?php
/**
 * Unit tests for the WidgetsProvider class.
 *
 * @package ConfigSync\Tests\Unit\Provider
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Provider\WidgetsProvider;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class WidgetsProviderTest
 *
 * @since 1.0.0
 */
class WidgetsProviderTest extends TestCase {

	/**
	 * WidgetsProvider instance under test.
	 *
	 * @since 1.0.0
	 * @var WidgetsProvider
	 */
	private WidgetsProvider $provider;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->provider = new WidgetsProvider();
	}

	/**
	 * Tear down test fixtures and clean up widget options.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		delete_option( 'sidebars_widgets' );
		delete_option( 'widget_text' );
		delete_option( 'widget_custom_html' );
		delete_option( 'widget_nav_menu' );
		parent::tear_down();
	}

	/**
	 * Test that get_id returns 'widgets'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_id_returns_widgets(): void {
		$this->assertSame( 'widgets', $this->provider->get_id() );
	}

	/**
	 * Test that get_label returns a translated string.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_label_returns_translated_string(): void {
		$this->assertSame( 'Widgets', $this->provider->get_label() );
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
	 * Test that get_config_files returns the widgets directory path.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_config_files_returns_widgets_directory(): void {
		$this->assertSame( array( 'widgets/' ), $this->provider->get_config_files() );
	}

	/**
	 * Test that export returns sidebar structure with widget data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_returns_sidebar_structure(): void {
		update_option(
			'sidebars_widgets',
			array(
				'sidebar-1' => array( 'text-2' ),
			)
		);
		update_option(
			'widget_text',
			array(
				2 => array(
					'title' => 'Hello',
					'text'  => 'World',
				),
			)
		);

		$export = $this->provider->export();

		$this->assertArrayHasKey( 'sidebar-1', $export );
		$this->assertCount( 1, $export['sidebar-1'] );
		$this->assertSame( 'text', $export['sidebar-1'][0]['type'] );
		$this->assertSame( 2, $export['sidebar-1'][0]['instance'] );
	}

	/**
	 * Test that export skips the wp_inactive_widgets sidebar.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_skips_inactive_widgets(): void {
		update_option(
			'sidebars_widgets',
			array(
				'wp_inactive_widgets' => array( 'text-1' ),
				'sidebar-1'          => array( 'text-2' ),
			)
		);
		update_option(
			'widget_text',
			array(
				1 => array( 'title' => 'Inactive' ),
				2 => array( 'title' => 'Active' ),
			)
		);

		$export = $this->provider->export();

		$this->assertArrayNotHasKey( 'wp_inactive_widgets', $export );
		$this->assertArrayHasKey( 'sidebar-1', $export );
	}

	/**
	 * Test that export skips the array_version key.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_skips_array_version(): void {
		update_option(
			'sidebars_widgets',
			array(
				'array_version' => 3,
				'sidebar-1'     => array( 'text-1' ),
			)
		);
		update_option(
			'widget_text',
			array(
				1 => array( 'title' => 'Test' ),
			)
		);

		$export = $this->provider->export();

		$this->assertArrayNotHasKey( 'array_version', $export );
		$this->assertArrayHasKey( 'sidebar-1', $export );
	}

	/**
	 * Test that export includes widget settings from the option.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_includes_widget_settings(): void {
		$settings = array(
			'title'   => 'My Widget',
			'content' => '<p>Some HTML</p>',
		);

		update_option(
			'sidebars_widgets',
			array(
				'sidebar-1' => array( 'custom_html-3' ),
			)
		);
		update_option(
			'widget_custom_html',
			array(
				3 => $settings,
			)
		);

		$export = $this->provider->export();

		$this->assertSame( $settings, $export['sidebar-1'][0]['settings'] );
		$this->assertSame( 'custom_html', $export['sidebar-1'][0]['type'] );
		$this->assertSame( 3, $export['sidebar-1'][0]['instance'] );
	}

	/**
	 * Test that export returns empty array when sidebars_widgets is not an array.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_returns_empty_when_sidebars_not_array(): void {
		update_option( 'sidebars_widgets', 'not-an-array' );

		$export = $this->provider->export();

		$this->assertSame( array(), $export );
	}

	/**
	 * Test that export handles multiple sidebars with different widget types.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_handles_multiple_sidebars(): void {
		update_option(
			'sidebars_widgets',
			array(
				'sidebar-1' => array( 'text-1' ),
				'sidebar-2' => array( 'custom_html-1', 'nav_menu-1' ),
			)
		);
		update_option( 'widget_text', array( 1 => array( 'title' => 'A' ) ) );
		update_option( 'widget_custom_html', array( 1 => array( 'content' => '<b>B</b>' ) ) );
		update_option( 'widget_nav_menu', array( 1 => array( 'nav_menu' => 5 ) ) );

		$export = $this->provider->export();

		$this->assertCount( 1, $export['sidebar-1'] );
		$this->assertCount( 2, $export['sidebar-2'] );
		$this->assertSame( 'text', $export['sidebar-1'][0]['type'] );
		$this->assertSame( 'custom_html', $export['sidebar-2'][0]['type'] );
		$this->assertSame( 'nav_menu', $export['sidebar-2'][1]['type'] );
	}

	/**
	 * Test that export skips widgets with unparseable IDs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_skips_unparseable_widget_ids(): void {
		update_option(
			'sidebars_widgets',
			array(
				'sidebar-1' => array( 'nodashnumber', 'text-2' ),
			)
		);
		update_option( 'widget_text', array( 2 => array( 'title' => 'Valid' ) ) );

		$export = $this->provider->export();

		$this->assertCount( 1, $export['sidebar-1'] );
		$this->assertSame( 'text', $export['sidebar-1'][0]['type'] );
	}

	/**
	 * Test that export handles a sidebar with no matching widget instances.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_handles_missing_widget_instances(): void {
		update_option(
			'sidebars_widgets',
			array(
				'sidebar-1' => array( 'text-99' ),
			)
		);
		update_option( 'widget_text', array() );

		$export = $this->provider->export();

		$this->assertArrayHasKey( 'sidebar-1', $export );
		$this->assertSame( array(), $export['sidebar-1'] );
	}

	/**
	 * Test that export skips sidebars with non-array widget lists.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_skips_non_array_widget_lists(): void {
		update_option(
			'sidebars_widgets',
			array(
				'sidebar-1' => 'broken',
				'sidebar-2' => array( 'text-1' ),
			)
		);
		update_option( 'widget_text', array( 1 => array( 'title' => 'OK' ) ) );

		$export = $this->provider->export();

		$this->assertArrayNotHasKey( 'sidebar-1', $export );
		$this->assertArrayHasKey( 'sidebar-2', $export );
	}

	/**
	 * Test that import updates widget option values.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_updates_widget_options(): void {
		update_option( 'widget_text', array() );

		$config = array(
			'sidebar-1' => array(
				array(
					'type'     => 'text',
					'instance' => 2,
					'settings' => array(
						'title' => 'Imported',
						'text'  => 'Content',
					),
				),
			),
		);

		$result = $this->provider->import( $config );

		$instances = get_option( 'widget_text' );

		$this->assertIsArray( $instances );
		$this->assertArrayHasKey( 2, $instances );
		$this->assertSame( 'Imported', $instances[2]['title'] );
		$this->assertSame( 1, $result['created'] );
	}

	/**
	 * Test that import updates the sidebars_widgets option.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_updates_sidebars_widgets(): void {
		update_option( 'sidebars_widgets', array() );
		update_option( 'widget_text', array() );

		$config = array(
			'sidebar-1' => array(
				array(
					'type'     => 'text',
					'instance' => 1,
					'settings' => array( 'title' => 'First' ),
				),
				array(
					'type'     => 'text',
					'instance' => 2,
					'settings' => array( 'title' => 'Second' ),
				),
			),
			'sidebar-2' => array(
				array(
					'type'     => 'custom_html',
					'instance' => 1,
					'settings' => array( 'content' => '<p>Hi</p>' ),
				),
			),
		);

		$this->provider->import( $config );

		$sidebars = get_option( 'sidebars_widgets' );

		$this->assertArrayHasKey( 'sidebar-1', $sidebars );
		$this->assertArrayHasKey( 'sidebar-2', $sidebars );
		$this->assertSame( array( 'text-1', 'text-2' ), $sidebars['sidebar-1'] );
		$this->assertSame( array( 'custom_html-1' ), $sidebars['sidebar-2'] );
	}

	/**
	 * Test that import returns correct result structure.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_returns_result_structure(): void {
		$config = array(
			'sidebar-1' => array(
				array(
					'type'     => 'text',
					'instance' => 1,
					'settings' => array( 'title' => 'Test' ),
				),
			),
		);

		$result = $this->provider->import( $config );

		$this->assertArrayHasKey( 'created', $result );
		$this->assertArrayHasKey( 'updated', $result );
		$this->assertArrayHasKey( 'deleted', $result );
		$this->assertArrayHasKey( 'details', $result );
		$this->assertIsInt( $result['created'] );
		$this->assertIsInt( $result['updated'] );
		$this->assertIsInt( $result['deleted'] );
		$this->assertIsArray( $result['details'] );
	}

	/**
	 * Test that import counts updated widgets correctly.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_counts_updated_widgets(): void {
		update_option(
			'widget_text',
			array(
				1 => array( 'title' => 'Old Title' ),
			)
		);
		update_option(
			'sidebars_widgets',
			array(
				'sidebar-1' => array( 'text-1' ),
			)
		);

		$config = array(
			'sidebar-1' => array(
				array(
					'type'     => 'text',
					'instance' => 1,
					'settings' => array( 'title' => 'New Title' ),
				),
			),
		);

		$result = $this->provider->import( $config );

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 1, $result['updated'] );
	}

	/**
	 * Test that import detects deleted widgets.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_detects_deleted_widgets(): void {
		update_option(
			'widget_text',
			array(
				1 => array( 'title' => 'Existing' ),
				2 => array( 'title' => 'To Remove' ),
			)
		);
		update_option(
			'sidebars_widgets',
			array(
				'sidebar-1' => array( 'text-1', 'text-2' ),
			)
		);

		$config = array(
			'sidebar-1' => array(
				array(
					'type'     => 'text',
					'instance' => 1,
					'settings' => array( 'title' => 'Existing' ),
				),
			),
		);

		$result = $this->provider->import( $config );

		$this->assertSame( 1, $result['deleted'] );
	}

	/**
	 * Test that import skips entries missing required keys.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_skips_entries_missing_keys(): void {
		$config = array(
			'sidebar-1' => array(
				array(
					'type' => 'text',
				),
				array(
					'type'     => 'text',
					'instance' => 1,
					'settings' => array( 'title' => 'Valid' ),
				),
			),
		);

		$result = $this->provider->import( $config );

		$this->assertSame( 1, $result['created'] );
	}

	/**
	 * Test that import skips non-array sidebar values.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_skips_non_array_sidebar_values(): void {
		$config = array(
			'sidebar-1' => 'not-an-array',
			'sidebar-2' => array(
				array(
					'type'     => 'text',
					'instance' => 1,
					'settings' => array( 'title' => 'OK' ),
				),
			),
		);

		$result = $this->provider->import( $config );

		$this->assertSame( 1, $result['created'] );

		$sidebars = get_option( 'sidebars_widgets' );
		$this->assertArrayNotHasKey( 'sidebar-1', $sidebars );
		$this->assertArrayHasKey( 'sidebar-2', $sidebars );
	}

	/**
	 * Test that import skips unregistered widget types with a warning.
	 *
	 * When the global widget factory is available with registered widgets,
	 * types not in that list should be skipped with a warning in details.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_skips_unregistered_widget_types(): void {
		global $wp_widget_factory;

		// Set up a mock widget factory with only 'text' registered.
		$original_factory = isset( $wp_widget_factory ) ? $wp_widget_factory : null;

		$wp_widget_factory          = new \stdClass();
		$text_widget                = new \stdClass();
		$text_widget->id_base       = 'text';
		$wp_widget_factory->widgets = array( $text_widget );

		$config = array(
			'sidebar-1' => array(
				array(
					'type'     => 'text',
					'instance' => 1,
					'settings' => array( 'title' => 'Valid' ),
				),
				array(
					'type'     => 'nonexistent_widget',
					'instance' => 1,
					'settings' => array( 'title' => 'Invalid' ),
				),
			),
		);

		$result = $this->provider->import( $config );

		// Restore original factory.
		$wp_widget_factory = $original_factory;

		$this->assertSame( 1, $result['created'] );

		$has_skip_warning = false;
		foreach ( $result['details'] as $detail ) {
			if ( false !== strpos( $detail, 'nonexistent_widget' ) && false !== strpos( $detail, 'Skipped' ) ) {
				$has_skip_warning = true;
				break;
			}
		}
		$this->assertTrue( $has_skip_warning, 'Expected a skip warning for unregistered widget type.' );
	}

	/**
	 * Test that import allows all types when widget factory is unavailable.
	 *
	 * When the global widget factory is not set (e.g. in CLI context),
	 * all widget types should be accepted.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_allows_all_types_when_no_factory(): void {
		global $wp_widget_factory;

		$original_factory  = isset( $wp_widget_factory ) ? $wp_widget_factory : null;
		$wp_widget_factory = null;

		$config = array(
			'sidebar-1' => array(
				array(
					'type'     => 'any_custom_type',
					'instance' => 1,
					'settings' => array( 'title' => 'Works' ),
				),
			),
		);

		$result = $this->provider->import( $config );

		$wp_widget_factory = $original_factory;

		$this->assertSame( 1, $result['created'] );
	}

	/**
	 * Test that import populates details messages.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_populates_details_messages(): void {
		update_option( 'widget_text', array() );

		$config = array(
			'sidebar-1' => array(
				array(
					'type'     => 'text',
					'instance' => 1,
					'settings' => array( 'title' => 'Hello' ),
				),
			),
		);

		$result = $this->provider->import( $config );

		$this->assertNotEmpty( $result['details'] );
		$this->assertStringContainsString( 'text-1', $result['details'][0] );
		$this->assertStringContainsString( 'sidebar-1', $result['details'][0] );
	}
}
