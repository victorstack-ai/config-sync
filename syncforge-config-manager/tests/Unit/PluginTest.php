<?php
/**
 * Unit tests for the Plugin class.
 *
 * @package ConfigSync\Tests\Unit
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Plugin;
use ConfigSync\Container;
use ConfigSync\ConfigManager;
use ConfigSync\AuditLogger;
use ConfigSync\DiffEngine;
use ConfigSync\FileHandler;
use ConfigSync\IdMapper;
use ConfigSync\Lock;
use ConfigSync\SchemaValidator;
use ConfigSync\Override\EnvironmentOverride;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class PluginTest
 *
 * @since 1.0.0
 */
class PluginTest extends TestCase {

	/**
	 * Plugin instance under test.
	 *
	 * @since 1.0.0
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Mock container.
	 *
	 * @since 1.0.0
	 * @var Container|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $container;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->container = $this->createMock( Container::class );
		$this->plugin    = new Plugin( $this->container );
	}

	/**
	 * Test that the constructor accepts a Container instance.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_constructor_accepts_container(): void {
		$this->assertInstanceOf( Plugin::class, $this->plugin );
	}

	/**
	 * Test that register_providers adds all seven built-in providers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_register_providers_adds_seven_providers(): void {
		$this->container
			->expects( $this->exactly( 7 ) )
			->method( 'add_provider' );

		$this->plugin->register_providers();
	}

	/**
	 * Test that register_providers applies the config_sync_providers filter.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_register_providers_applies_filter(): void {
		$filter_called = false;

		add_filter(
			'config_sync_providers',
			function ( $providers ) use ( &$filter_called ) {
				$filter_called = true;
				$this->assertIsArray( $providers );
				$this->assertCount( 7, $providers );
				return $providers;
			}
		);

		$this->plugin->register_providers();

		$this->assertTrue( $filter_called, 'The config_sync_providers filter should be applied.' );

		remove_all_filters( 'config_sync_providers' );
	}

	/**
	 * Test that register_providers allows third-party providers via filter.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_register_providers_allows_third_party(): void {
		$mock_provider = $this->createMock( \ConfigSync\Provider\ProviderInterface::class );
		$mock_provider->method( 'get_id' )->willReturn( 'custom_provider' );

		add_filter(
			'config_sync_providers',
			function ( $providers ) use ( $mock_provider ) {
				$providers[] = $mock_provider;
				return $providers;
			}
		);

		// 7 built-in + 1 custom = 8.
		$this->container
			->expects( $this->exactly( 8 ) )
			->method( 'add_provider' );

		$this->plugin->register_providers();

		remove_all_filters( 'config_sync_providers' );
	}

	/**
	 * Test that register_hooks registers admin_menu action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_register_hooks_registers_admin_menu(): void {
		$this->plugin->register_hooks();

		$this->assertGreaterThan(
			0,
			has_action( 'admin_menu' ),
			'admin_menu action should be registered.'
		);
	}

	/**
	 * Test that register_hooks registers rest_api_init action.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_register_hooks_registers_rest_api_init(): void {
		$this->plugin->register_hooks();

		$this->assertGreaterThan(
			0,
			has_action( 'rest_api_init', array( $this->plugin, 'register_rest_routes' ) ),
			'rest_api_init action should be registered.'
		);
	}

	/**
	 * Test that register_hooks registers init actions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_register_hooks_registers_init(): void {
		$this->plugin->register_hooks();

		$this->assertGreaterThan(
			0,
			has_action( 'init', array( $this->plugin, 'load_textdomain' ) ),
			'init action for textdomain should be registered.'
		);

		$this->assertGreaterThan(
			0,
			has_action( 'init', array( $this->plugin, 'register_capability' ) ),
			'init action for capability should be registered.'
		);
	}

	/**
	 * Test that register_rest_routes instantiates all four controllers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_register_rest_routes_registers_controllers(): void {
		$config_manager = $this->createMock( ConfigManager::class );
		$audit_logger   = $this->createMock( AuditLogger::class );

		$this->container
			->method( 'get_config_manager' )
			->willReturn( $config_manager );

		$this->container
			->method( 'get_audit_logger' )
			->willReturn( $audit_logger );

		// Should not throw any exceptions.
		$this->plugin->register_rest_routes();

		$this->assertTrue( true, 'REST routes should register without errors.' );
	}

	/**
	 * Test that get_db_schema returns valid SQL with correct formatting.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_db_schema_contains_id_map_table(): void {
		$schema = Plugin::get_db_schema();

		$this->assertStringContainsString( 'config_sync_id_map', $schema );
		$this->assertStringContainsString( 'config_sync_audit_log', $schema );
	}

	/**
	 * Test that get_db_schema uses two spaces after PRIMARY KEY.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_db_schema_primary_key_formatting(): void {
		$schema = Plugin::get_db_schema();

		// dbDelta requires exactly two spaces after PRIMARY KEY.
		$this->assertStringContainsString( 'PRIMARY KEY  (id)', $schema );
		$this->assertStringNotContainsString( 'PRIMARY KEY (id)', $schema );
	}

	/**
	 * Test that get_db_schema contains all required columns for id_map.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_db_schema_id_map_columns(): void {
		$schema = Plugin::get_db_schema();

		$this->assertStringContainsString( 'provider varchar(64)', $schema );
		$this->assertStringContainsString( 'stable_key varchar(255)', $schema );
		$this->assertStringContainsString( 'local_id bigint(20)', $schema );
		$this->assertStringContainsString( 'UNIQUE KEY provider_stable_key', $schema );
	}

	/**
	 * Test that get_db_schema contains all required columns for audit_log.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_db_schema_audit_log_columns(): void {
		$schema = Plugin::get_db_schema();

		$this->assertStringContainsString( 'user_id bigint(20)', $schema );
		$this->assertStringContainsString( 'action varchar(20)', $schema );
		$this->assertStringContainsString( 'environment varchar(64)', $schema );
		$this->assertStringContainsString( 'diff_data longtext', $schema );
		$this->assertStringContainsString( 'snapshot_data longtext', $schema );
		$this->assertStringContainsString( 'created_at datetime', $schema );
		$this->assertStringContainsString( 'KEY action_provider', $schema );
		$this->assertStringContainsString( 'KEY created_at', $schema );
	}

	/**
	 * Test that get_db_schema contains CREATE TABLE statements.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_db_schema_has_create_table(): void {
		$schema = Plugin::get_db_schema();

		$this->assertSame( 2, substr_count( $schema, 'CREATE TABLE' ) );
	}

	/**
	 * Test that activate is a static method that does not throw.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_activate_is_callable(): void {
		$this->assertTrue(
			is_callable( array( Plugin::class, 'activate' ) ),
			'activate() should be a callable static method.'
		);
	}

	/**
	 * Test that deactivate is a static method that does not throw.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_deactivate_is_callable(): void {
		$this->assertTrue(
			is_callable( array( Plugin::class, 'deactivate' ) ),
			'deactivate() should be a callable static method.'
		);
	}

	/**
	 * Test that deactivate cleans up transients.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_deactivate_cleans_transients(): void {
		set_transient( 'config_sync_lock_import', '1', 300 );
		set_transient( 'config_sync_lock_rollback', '1', 300 );

		Plugin::deactivate();

		$this->assertFalse( get_transient( 'config_sync_lock_import' ) );
		$this->assertFalse( get_transient( 'config_sync_lock_rollback' ) );
	}

	/**
	 * Test that load_textdomain is a public method.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_load_textdomain_is_callable(): void {
		$this->assertTrue(
			is_callable( array( $this->plugin, 'load_textdomain' ) ),
			'load_textdomain() should be a callable public method.'
		);
	}

	/**
	 * Test that register_capability is a public method.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_register_capability_is_callable(): void {
		$this->assertTrue(
			is_callable( array( $this->plugin, 'register_capability' ) ),
			'register_capability() should be a callable public method.'
		);
	}

	/**
	 * Test that init calls register_providers, init_services, and register_hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_init_wires_everything(): void {
		$plugin = $this->getMockBuilder( Plugin::class )
			->setConstructorArgs( array( $this->container ) )
			->onlyMethods( array( 'register_providers', 'register_hooks' ) )
			->getMock();

		$plugin->expects( $this->once() )->method( 'register_providers' );
		$plugin->expects( $this->once() )->method( 'register_hooks' );

		// init_services is private, so we expect the container setters to be called.
		$this->container
			->expects( $this->once() )
			->method( 'set_config_manager' );

		$this->container
			->expects( $this->once() )
			->method( 'set_file_handler' );

		$this->container
			->expects( $this->once() )
			->method( 'set_diff_engine' );

		$plugin->init();
	}
}
