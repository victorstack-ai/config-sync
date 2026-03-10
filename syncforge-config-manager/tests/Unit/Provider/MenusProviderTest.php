<?php
/**
 * Unit tests for the MenusProvider class.
 *
 * @package ConfigSync\Tests\Unit\Provider
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Provider\MenusProvider;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class MenusProviderTest
 *
 * @since 1.0.0
 */
class MenusProviderTest extends TestCase {

	/**
	 * MenusProvider instance under test.
	 *
	 * @since 1.0.0
	 * @var MenusProvider
	 */
	private MenusProvider $provider;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->provider = new MenusProvider();
	}

	/**
	 * Test that get_id returns 'menus'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_id_returns_menus(): void {
		$this->assertSame( 'menus', $this->provider->get_id() );
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
	 * Test that export returns a menu structure with the _locations key.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_returns_menu_structure(): void {
		$menu_id = wp_create_nav_menu( 'Test Menu' );

		$result = $this->provider->export();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( '_locations', $result );
		$this->assertArrayHasKey( 'test-menu', $result );
		$this->assertSame( 'Test Menu', $result['test-menu']['name'] );
		$this->assertArrayHasKey( 'items', $result['test-menu'] );

		wp_delete_nav_menu( $menu_id );
	}

	/**
	 * Test that export builds a nested tree from parent-child items.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_builds_nested_tree(): void {
		$menu_id = wp_create_nav_menu( 'Tree Menu' );

		$parent_id = wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'  => 'Parent',
				'menu-item-url'    => 'https://example.com/parent',
				'menu-item-status' => 'publish',
				'menu-item-type'   => 'custom',
			)
		);

		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => 'Child',
				'menu-item-url'       => 'https://example.com/child',
				'menu-item-status'    => 'publish',
				'menu-item-type'      => 'custom',
				'menu-item-parent-id' => $parent_id,
			)
		);

		$result = $this->provider->export();

		$this->assertNotEmpty( $result['tree-menu']['items'] );

		$parent_item = $result['tree-menu']['items'][0];
		$this->assertSame( 'Parent', $parent_item['title'] );
		$this->assertArrayHasKey( 'children', $parent_item );
		$this->assertCount( 1, $parent_item['children'] );
		$this->assertSame( 'Child', $parent_item['children'][0]['title'] );

		wp_delete_nav_menu( $menu_id );
	}

	/**
	 * Test that exported items use slugs instead of numeric IDs.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_uses_slugs_not_ids(): void {
		$menu_id = wp_create_nav_menu( 'Slug Menu' );

		$page_id = wp_insert_post(
			array(
				'post_title'  => 'About Us',
				'post_name'   => 'about-us',
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => 'About',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);

		$result = $this->provider->export();

		$items = $result['slug-menu']['items'];
		$this->assertNotEmpty( $items );

		$item = $items[0];
		$this->assertSame( 'post_type', $item['type'] );
		$this->assertSame( 'page', $item['object'] );
		$this->assertSame( 'about-us', $item['slug'] );
		$this->assertArrayNotHasKey( 'object_id', $item );

		wp_delete_post( $page_id, true );
		wp_delete_nav_menu( $menu_id );
	}

	/**
	 * Test that export includes menu locations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_includes_menu_locations(): void {
		$menu_id = wp_create_nav_menu( 'Primary Menu' );

		register_nav_menus(
			array(
				'primary' => 'Primary Location',
			)
		);

		set_theme_mod(
			'nav_menu_locations',
			array( 'primary' => $menu_id )
		);

		$result = $this->provider->export();

		$this->assertArrayHasKey( '_locations', $result );
		$this->assertArrayHasKey( 'primary', $result['_locations'] );
		$this->assertSame( 'primary-menu', $result['_locations']['primary'] );

		wp_delete_nav_menu( $menu_id );
	}

	/**
	 * Test that import creates a new menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_creates_menu(): void {
		$config = array(
			'new-menu' => array(
				'name'        => 'New Menu',
				'description' => 'A test menu',
				'items'       => array(),
			),
			'_locations' => array(),
		);

		$result = $this->provider->import( $config );

		$this->assertSame( 1, $result['created'] );
		$this->assertSame( 0, $result['updated'] );

		$menu = wp_get_nav_menu_object( 'New Menu' );
		$this->assertNotFalse( $menu );
		$this->assertSame( 'New Menu', $menu->name );

		wp_delete_nav_menu( $menu->term_id );
	}

	/**
	 * Test that import creates menu items from nested config.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_creates_menu_items(): void {
		$config = array(
			'import-menu' => array(
				'name'        => 'Import Menu',
				'description' => '',
				'items'       => array(
					array(
						'title' => 'Home',
						'type'  => 'custom',
						'url'   => 'https://example.com/',
					),
					array(
						'title'    => 'Services',
						'type'     => 'custom',
						'url'      => 'https://example.com/services',
						'children' => array(
							array(
								'title' => 'Web Design',
								'type'  => 'custom',
								'url'   => 'https://example.com/services/web-design',
							),
						),
					),
				),
			),
			'_locations' => array(),
		);

		$result = $this->provider->import( $config );

		$this->assertSame( 1, $result['created'] );

		$menu  = wp_get_nav_menu_object( 'Import Menu' );
		$items = wp_get_nav_menu_items( $menu->term_id );

		$this->assertCount( 3, $items );

		$titles = wp_list_pluck( $items, 'title' );
		$this->assertContains( 'Home', $titles );
		$this->assertContains( 'Services', $titles );
		$this->assertContains( 'Web Design', $titles );

		wp_delete_nav_menu( $menu->term_id );
	}

	/**
	 * Test that get_config_files returns the menus directory.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_config_files(): void {
		$files = $this->provider->get_config_files();

		$this->assertIsArray( $files );
		$this->assertContains( 'menus/', $files );
	}

	/**
	 * Test that get_batch_size returns 50.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_batch_size_returns_fifty(): void {
		$this->assertSame( 50, $this->provider->get_batch_size() );
	}

	/**
	 * Test that get_label returns a non-empty string.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_label_returns_string(): void {
		$label = $this->provider->get_label();

		$this->assertIsString( $label );
		$this->assertNotEmpty( $label );
	}
}
