<?php
/**
 * Unit tests for the AdminPage class.
 *
 * @package ConfigSync\Tests\Unit\Admin
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Admin\AdminPage;
use WP_UnitTestCase;

/**
 * Class AdminPageTest
 *
 * @since   1.0.0
 * @covers  \ConfigSync\Admin\AdminPage
 */
class AdminPageTest extends WP_UnitTestCase {

	/**
	 * AdminPage instance under test.
	 *
	 * @since 1.0.0
	 * @var AdminPage
	 */
	private AdminPage $admin_page;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->admin_page = new AdminPage();
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function tear_down(): void {
		parent::tear_down();
	}

	// ------------------------------------------------------------------
	// Constants
	// ------------------------------------------------------------------

	/**
	 * Test that the menu slug constant is set correctly.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_menu_slug_constant(): void {
		$this->assertSame( 'syncforge-config-manager', AdminPage::MENU_SLUG );
	}

	/**
	 * Test that the script handle constant is set correctly.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_script_handle_constant(): void {
		$this->assertSame( 'syncforge-admin', AdminPage::SCRIPT_HANDLE );
	}

	// ------------------------------------------------------------------
	// register()
	// ------------------------------------------------------------------

	/**
	 * Test register hooks into admin_menu and admin_enqueue_scripts.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_register_adds_hooks(): void {
		$this->admin_page->register();

		$this->assertSame(
			10,
			has_action( 'admin_menu', array( $this->admin_page, 'add_menu_page' ) )
		);
		$this->assertSame(
			10,
			has_action( 'admin_enqueue_scripts', array( $this->admin_page, 'enqueue_assets' ) )
		);
	}

	// ------------------------------------------------------------------
	// add_menu_page()
	// ------------------------------------------------------------------

	/**
	 * Test add_menu_page registers a Tools submenu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_add_menu_page_registers_submenu(): void {
		// Must be an administrator to register menu pages.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->admin_page->add_menu_page();

		$hook_suffix = $this->admin_page->get_hook_suffix();
		$this->assertNotFalse( $hook_suffix, 'Hook suffix should not be false after registration.' );
		$this->assertIsString( $hook_suffix );
	}

	/**
	 * Test hook_suffix is false before registration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_hook_suffix_false_before_registration(): void {
		$this->assertFalse( $this->admin_page->get_hook_suffix() );
	}

	// ------------------------------------------------------------------
	// enqueue_assets()
	// ------------------------------------------------------------------

	/**
	 * Test enqueue_assets does nothing for non-matching hook suffix.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_enqueue_assets_skips_non_matching_page(): void {
		$this->admin_page->enqueue_assets( 'some-other-page' );

		$this->assertFalse( wp_script_is( AdminPage::SCRIPT_HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_style_is( AdminPage::STYLE_HANDLE, 'enqueued' ) );
	}

	// ------------------------------------------------------------------
	// render()
	// ------------------------------------------------------------------

	/**
	 * Test render does nothing without manage_options capability.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_render_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		ob_start();
		$this->admin_page->render();
		$output = ob_get_clean();

		$this->assertEmpty( $output, 'Render should produce no output for subscribers.' );
	}

	/**
	 * Test render outputs the admin page template for admins.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_render_outputs_template_for_admin(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		$this->admin_page->render();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'class="wrap', $output );
		$this->assertStringContainsString( 'syncforge-admin', $output );
		$this->assertStringContainsString( 'syncforge-export', $output );
		$this->assertStringContainsString( 'syncforge-zip-export', $output );
	}
}
