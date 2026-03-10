<?php
/**
 * Unit tests for the RolesProvider class.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Provider\RolesProvider;
use WP_UnitTestCase;

/**
 * Class RolesProviderTest
 *
 * @since 1.0.0
 * @covers \ConfigSync\Provider\RolesProvider
 */
class RolesProviderTest extends WP_UnitTestCase {

	/**
	 * RolesProvider instance under test.
	 *
	 * @since 1.0.0
	 * @var RolesProvider
	 */
	private RolesProvider $provider;

	/**
	 * Set up each test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->provider = new RolesProvider();
	}

	/**
	 * Tear down each test.
	 *
	 * Removes any custom roles added during tests.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_role( 'custom_test_role' );
		remove_role( 'another_test_role' );
		parent::tear_down();
	}

	/**
	 * Test that get_id returns 'roles'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_id_returns_roles(): void {
		$this->assertSame( 'roles', $this->provider->get_id() );
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
	 * Test that export returns all registered roles.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_returns_all_roles(): void {
		$exported = $this->provider->export();

		$this->assertIsArray( $exported );
		$this->assertArrayHasKey( 'administrator', $exported );
		$this->assertArrayHasKey( 'editor', $exported );
		$this->assertArrayHasKey( 'subscriber', $exported );
	}

	/**
	 * Test that export includes capabilities for each role.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_includes_capabilities(): void {
		$exported = $this->provider->export();

		$admin = $exported['administrator'];
		$this->assertArrayHasKey( 'name', $admin );
		$this->assertArrayHasKey( 'capabilities', $admin );
		$this->assertSame( 'Administrator', $admin['name'] );
		$this->assertIsArray( $admin['capabilities'] );
		$this->assertNotEmpty( $admin['capabilities'] );
	}

	/**
	 * Test that exported capabilities are sorted alphabetically.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_sorts_capabilities_alphabetically(): void {
		$exported = $this->provider->export();

		$admin_caps = $exported['administrator']['capabilities'];
		$cap_keys   = array_keys( $admin_caps );
		$sorted     = $cap_keys;
		sort( $sorted );

		$this->assertSame( $sorted, $cap_keys );
	}

	/**
	 * Test that import creates a new role.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_creates_new_role(): void {
		$current_config = $this->provider->export();

		$current_config['custom_test_role'] = array(
			'name'         => 'Custom Test Role',
			'capabilities' => array(
				'read' => true,
			),
		);

		$result = $this->provider->import( $current_config );

		$this->assertSame( 1, $result['created'] );
		$this->assertNotNull( get_role( 'custom_test_role' ) );
		$this->assertTrue( get_role( 'custom_test_role' )->has_cap( 'read' ) );
	}

	/**
	 * Test that import updates capabilities on an existing role.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_updates_existing_role_capabilities(): void {
		// Add a custom role first.
		add_role(
			'custom_test_role',
			'Custom Test Role',
			array( 'read' => true )
		);

		$current_config = $this->provider->export();

		// Modify the custom role capabilities.
		$current_config['custom_test_role']['capabilities'] = array(
			'read'         => true,
			'edit_posts'   => true,
		);

		$result = $this->provider->import( $current_config );

		$this->assertGreaterThanOrEqual( 1, $result['updated'] );

		$role = get_role( 'custom_test_role' );
		$this->assertTrue( $role->has_cap( 'edit_posts' ) );
	}

	/**
	 * Test that import removes a role not present in config.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_removes_deleted_role(): void {
		// Add a role that will not be in the import config.
		add_role(
			'custom_test_role',
			'Custom Test Role',
			array( 'read' => true )
		);

		// Export without the custom role.
		$config = $this->provider->export();
		unset( $config['custom_test_role'] );

		$result = $this->provider->import( $config );

		$this->assertSame( 1, $result['deleted'] );
		$this->assertNull( get_role( 'custom_test_role' ) );
	}

	/**
	 * Test that import never deletes the administrator role.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_never_deletes_administrator(): void {
		$config = $this->provider->export();
		unset( $config['administrator'] );

		$result = $this->provider->import( $config );

		$this->assertNotNull( get_role( 'administrator' ) );
		$this->assertSame( 0, $result['deleted'] );
	}

	/**
	 * Test that get_config_files returns one file per role.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_config_files_returns_role_files(): void {
		$files = $this->provider->get_config_files();

		$this->assertIsArray( $files );
		$this->assertContains( 'roles/administrator.yml', $files );
		$this->assertContains( 'roles/editor.yml', $files );

		// Each file should follow the roles/slug.yml pattern.
		foreach ( $files as $file ) {
			$this->assertMatchesRegularExpression( '/^roles\/[a-z0-9_]+\.yml$/', $file );
		}
	}
}
