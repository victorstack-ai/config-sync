<?php
/**
 * Unit tests for the ConfigManager class.
 *
 * @package ConfigSync\Tests\Unit
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\ConfigManager;
use ConfigSync\Container;
use ConfigSync\DiffEngine;
use ConfigSync\FileHandler;
use ConfigSync\Lock;
use ConfigSync\AuditLogger;
use ConfigSync\SchemaValidator;
use ConfigSync\Override\EnvironmentOverride;
use ConfigSync\Provider\ProviderInterface;
use ConfigSync\Sanitizer\YamlSanitizer;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class ConfigManagerTest
 *
 * @since 1.0.0
 */
class ConfigManagerTest extends TestCase {

	/**
	 * ConfigManager instance under test.
	 *
	 * @since 1.0.0
	 * @var ConfigManager
	 */
	private ConfigManager $manager;

	/**
	 * Mock container.
	 *
	 * @since 1.0.0
	 * @var Container|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $container;

	/**
	 * Mock file handler.
	 *
	 * @since 1.0.0
	 * @var FileHandler|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $file_handler;

	/**
	 * Mock lock.
	 *
	 * @since 1.0.0
	 * @var Lock|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $lock;

	/**
	 * Mock audit logger.
	 *
	 * @since 1.0.0
	 * @var AuditLogger|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $audit_logger;

	/**
	 * Mock schema validator.
	 *
	 * @since 1.0.0
	 * @var SchemaValidator|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $schema_validator;

	/**
	 * Mock environment override.
	 *
	 * @since 1.0.0
	 * @var EnvironmentOverride|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $environment_override;

	/**
	 * YAML sanitizer (real instance).
	 *
	 * @since 1.0.0
	 * @var YamlSanitizer
	 */
	private YamlSanitizer $yaml_sanitizer;

	/**
	 * Diff engine (real instance — lightweight pure logic).
	 *
	 * @since 1.0.0
	 * @var DiffEngine
	 */
	private DiffEngine $diff_engine;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->container        = $this->createMock( Container::class );
		$this->file_handler     = $this->createMock( FileHandler::class );
		$this->lock             = $this->createMock( Lock::class );
		$this->audit_logger     = $this->createMock( AuditLogger::class );
		$this->schema_validator = $this->createMock( SchemaValidator::class );
		$this->diff_engine      = new DiffEngine();

		$this->environment_override = $this->createMock( EnvironmentOverride::class );
		$this->environment_override->method( 'apply_overrides' )->willReturnArgument( 1 );

		$this->yaml_sanitizer = new YamlSanitizer();

		$this->container->method( 'get_file_handler' )->willReturn( $this->file_handler );
		$this->container->method( 'get_lock' )->willReturn( $this->lock );
		$this->container->method( 'get_audit_logger' )->willReturn( $this->audit_logger );
		$this->container->method( 'get_schema_validator' )->willReturn( $this->schema_validator );
		$this->container->method( 'get_diff_engine' )->willReturn( $this->diff_engine );
		$this->container->method( 'get_environment_override' )->willReturn( $this->environment_override );
		$this->container->method( 'get_yaml_sanitizer' )->willReturn( $this->yaml_sanitizer );

		$this->manager = new ConfigManager( $this->container );
	}

	/**
	 * Create a mock provider with the given ID, dependencies, config files, and export data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id           Provider identifier.
	 * @param array  $dependencies Provider dependency IDs.
	 * @param array  $config_files Config file paths.
	 * @param array  $export_data  Data returned by export().
	 * @return ProviderInterface|\PHPUnit\Framework\MockObject\MockObject
	 */
	private function create_mock_provider( string $id, array $dependencies = array(), array $config_files = array(), array $export_data = array() ) {
		$provider = $this->createMock( ProviderInterface::class );
		$provider->method( 'get_id' )->willReturn( $id );
		$provider->method( 'get_dependencies' )->willReturn( $dependencies );
		$provider->method( 'get_config_files' )->willReturn( $config_files );
		$provider->method( 'export' )->willReturn( $export_data );

		return $provider;
	}

	/**
	 * Test that export_all iterates providers and writes YAML files.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_all_writes_files_for_each_provider(): void {
		$provider_a = $this->create_mock_provider(
			'alpha',
			array(),
			array( 'alpha/data.yml' ),
			array( 'key' => 'value' )
		);

		$provider_b = $this->create_mock_provider(
			'beta',
			array(),
			array( 'beta/data.yml' ),
			array( 'foo' => 'bar' )
		);

		$this->container->method( 'get_providers' )->willReturn( array(
			'alpha' => $provider_a,
			'beta'  => $provider_b,
		) );

		$this->file_handler->expects( $this->exactly( 2 ) )
			->method( 'write' );

		$this->audit_logger->expects( $this->once() )
			->method( 'log_operation' )
			->with( 'export', 'all', $this->anything(), $this->anything(), $this->anything() );

		$result = $this->manager->export_all();

		$this->assertArrayHasKey( 'providers', $result );
		$this->assertArrayHasKey( 'errors', $result );
		$this->assertCount( 2, $result['providers'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test that export_all collects errors but continues with remaining providers.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_all_collects_errors_and_continues(): void {
		$failing_provider = $this->createMock( ProviderInterface::class );
		$failing_provider->method( 'get_id' )->willReturn( 'failing' );
		$failing_provider->method( 'get_dependencies' )->willReturn( array() );
		$failing_provider->method( 'get_config_files' )->willReturn( array( 'failing/data.yml' ) );
		$failing_provider->method( 'export' )->willThrowException( new \RuntimeException( 'Export failed' ) );

		$good_provider = $this->create_mock_provider(
			'good',
			array(),
			array( 'good/data.yml' ),
			array( 'ok' => true )
		);

		$this->container->method( 'get_providers' )->willReturn( array(
			'failing' => $failing_provider,
			'good'    => $good_provider,
		) );

		$result = $this->manager->export_all();

		$this->assertArrayHasKey( 'failing', $result['errors'] );
		$this->assertArrayHasKey( 'good', $result['providers'] );
	}

	/**
	 * Test that export_provider returns error for unknown provider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_provider_returns_error_for_unknown_provider(): void {
		$this->container->method( 'get_providers' )->willReturn( array() );

		$result = $this->manager->export_provider( 'nonexistent' );

		$this->assertArrayHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'nonexistent', $result['errors'] );
	}

	/**
	 * Test that export_provider exports a single provider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_provider_exports_single_provider(): void {
		$provider = $this->create_mock_provider(
			'options',
			array(),
			array( 'options/general.yml' ),
			array( 'blogname' => 'Test' )
		);

		$this->container->method( 'get_providers' )->willReturn( array(
			'options' => $provider,
		) );

		$this->file_handler->expects( $this->once() )->method( 'write' );

		$result = $this->manager->export_provider( 'options' );

		$this->assertArrayHasKey( 'options', $result['providers'] );
		$this->assertEmpty( $result['errors'] );
	}

	/**
	 * Test that import_all returns WP_Error when lock cannot be acquired.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_all_returns_wp_error_when_locked(): void {
		$this->lock->method( 'acquire' )->willReturn( false );

		$result = $this->manager->import_all();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'config_sync_locked', $result->get_error_code() );
	}

	/**
	 * Test that import_all acquires and releases the lock.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_all_acquires_and_releases_lock(): void {
		$this->lock->method( 'acquire' )->willReturn( true );
		$this->lock->expects( $this->once() )->method( 'release' );

		$provider = $this->create_mock_provider(
			'options',
			array(),
			array( 'options/data.yml' ),
			array()
		);
		$provider->method( 'import' )->willReturn( array(
			'created' => 0,
			'updated' => 0,
			'deleted' => 0,
			'details' => array(),
		) );

		$this->container->method( 'get_providers' )->willReturn( array(
			'options' => $provider,
		) );

		$this->schema_validator->method( 'validate' )->willReturn( true );
		$this->file_handler->method( 'read' )->willReturn( array() );

		$result = $this->manager->import_all();

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'providers', $result );
	}

	/**
	 * Test that import_all releases lock even on failure.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_all_releases_lock_on_failure(): void {
		$this->lock->method( 'acquire' )->willReturn( true );
		$this->lock->expects( $this->once() )->method( 'release' );

		$this->container->method( 'get_providers' )->willThrowException(
			new \RuntimeException( 'Service failure' )
		);

		$this->expectException( \RuntimeException::class );
		$this->manager->import_all();
	}

	/**
	 * Test that import_all calls dry_run when flag is set.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_all_dry_run_calls_provider_dry_run(): void {
		$this->lock->method( 'acquire' )->willReturn( true );

		$provider = $this->create_mock_provider(
			'options',
			array(),
			array( 'options/data.yml' ),
			array()
		);
		$provider->expects( $this->once() )
			->method( 'dry_run' )
			->willReturn( array() );
		$provider->expects( $this->never() )
			->method( 'import' );

		$this->container->method( 'get_providers' )->willReturn( array(
			'options' => $provider,
		) );

		$this->schema_validator->method( 'validate' )->willReturn( true );
		$this->file_handler->method( 'read' )->willReturn( array() );

		$result = $this->manager->import_all( true );

		$this->assertTrue( $result['dry_run'] );
	}

	/**
	 * Test that import_all validates config and reports validation errors.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_all_reports_validation_errors(): void {
		$this->lock->method( 'acquire' )->willReturn( true );

		$provider = $this->create_mock_provider(
			'options',
			array(),
			array( 'options/data.yml' ),
			array()
		);

		$this->container->method( 'get_providers' )->willReturn( array(
			'options' => $provider,
		) );

		$this->schema_validator->method( 'validate' )->willReturn(
			new \WP_Error( 'config_sync_validation_failed', 'Invalid data' )
		);
		$this->file_handler->method( 'read' )->willReturn( array( 'bad' => 'data' ) );

		$result = $this->manager->import_all();

		$this->assertArrayHasKey( 'options', $result['errors'] );
	}

	/**
	 * Test that import_provider returns WP_Error when locked.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_provider_returns_wp_error_when_locked(): void {
		$provider = $this->create_mock_provider( 'options', array(), array( 'options/data.yml' ), array() );
		$this->container->method( 'get_providers' )->willReturn( array(
			'options' => $provider,
		) );
		$this->lock->method( 'acquire' )->willReturn( false );

		$result = $this->manager->import_provider( 'options' );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Test that import_provider returns error for unknown provider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_provider_returns_error_for_unknown_provider(): void {
		$this->container->method( 'get_providers' )->willReturn( array() );

		$result = $this->manager->import_provider( 'nonexistent' );

		$this->assertArrayHasKey( 'errors', $result );
		$this->assertArrayHasKey( 'nonexistent', $result['errors'] );
	}

	/**
	 * Test that import_provider imports a single provider successfully.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_provider_imports_single_provider(): void {
		$this->lock->method( 'acquire' )->willReturn( true );

		$provider = $this->create_mock_provider(
			'options',
			array(),
			array( 'options/data.yml' ),
			array()
		);
		$provider->method( 'import' )->willReturn( array(
			'created' => 1,
			'updated' => 2,
			'deleted' => 0,
			'details' => array(),
		) );

		$this->container->method( 'get_providers' )->willReturn( array(
			'options' => $provider,
		) );

		$this->schema_validator->method( 'validate' )->willReturn( true );
		$this->file_handler->method( 'read' )->willReturn( array( 'key' => 'val' ) );

		$result = $this->manager->import_provider( 'options' );

		$this->assertArrayHasKey( 'options', $result['providers'] );
		$this->assertSame( 1, $result['providers']['options']['created'] );
	}

	/**
	 * Test that diff computes differences between database state and YAML files.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_diff_computes_differences(): void {
		$provider = $this->create_mock_provider(
			'options',
			array(),
			array( 'options/data.yml' ),
			array( 'blogname' => 'Current' )
		);

		$this->container->method( 'get_providers' )->willReturn( array(
			'options' => $provider,
		) );

		$this->file_handler->method( 'read' )->willReturn( array( 'blogname' => 'FromDisk' ) );

		$result = $this->manager->diff();

		$this->assertArrayHasKey( 'options', $result );
		$this->assertCount( 1, $result['options'] );
		$this->assertSame( 'modified', $result['options'][0]['type'] );
	}

	/**
	 * Test that diff returns empty array when database and files match.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_diff_returns_empty_when_no_changes(): void {
		$data     = array( 'blogname' => 'Same' );
		$provider = $this->create_mock_provider(
			'options',
			array(),
			array( 'options/data.yml' ),
			$data
		);

		$this->container->method( 'get_providers' )->willReturn( array(
			'options' => $provider,
		) );

		$this->file_handler->method( 'read' )->willReturn( $data );

		$result = $this->manager->diff();

		$this->assertEmpty( $result );
	}

	/**
	 * Test that rollback returns WP_Error when snapshot is not found.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_rollback_returns_error_when_snapshot_not_found(): void {
		$this->audit_logger->method( 'get_snapshot' )->willReturn( null );

		$result = $this->manager->rollback( 999 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'config_sync_snapshot_not_found', $result->get_error_code() );
	}

	/**
	 * Test that rollback returns WP_Error when locked.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_rollback_returns_error_when_locked(): void {
		$this->audit_logger->method( 'get_snapshot' )->willReturn( array(
			'options' => array( 'blogname' => 'Old' ),
		) );
		$this->lock->method( 'acquire' )->willReturn( false );

		$result = $this->manager->rollback( 1 );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'config_sync_locked', $result->get_error_code() );
	}

	/**
	 * Test that rollback imports snapshot data for each provider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_rollback_imports_snapshot_data(): void {
		$snapshot = array(
			'options' => array( 'blogname' => 'Restored' ),
		);

		$this->audit_logger->method( 'get_snapshot' )->willReturn( $snapshot );
		$this->lock->method( 'acquire' )->willReturn( true );

		$provider = $this->create_mock_provider( 'options', array(), array(), array() );
		$provider->expects( $this->once() )
			->method( 'import' )
			->with( array( 'blogname' => 'Restored' ) )
			->willReturn( array(
				'created' => 0,
				'updated' => 1,
				'deleted' => 0,
				'details' => array(),
			) );

		$this->container->method( 'get_providers' )->willReturn( array(
			'options' => $provider,
		) );

		$result = $this->manager->rollback( 42 );

		$this->assertIsArray( $result );
		$this->assertSame( 42, $result['snapshot_id'] );
		$this->assertArrayHasKey( 'options', $result['providers'] );
	}

	/**
	 * Test that rollback skips providers not found in the container.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_rollback_skips_missing_providers(): void {
		$snapshot = array(
			'deleted_provider' => array( 'key' => 'value' ),
		);

		$this->audit_logger->method( 'get_snapshot' )->willReturn( $snapshot );
		$this->lock->method( 'acquire' )->willReturn( true );
		$this->container->method( 'get_providers' )->willReturn( array() );

		$result = $this->manager->rollback( 1 );

		$this->assertArrayHasKey( 'deleted_provider', $result['errors'] );
	}

	/**
	 * Test that topological sort respects dependency order.
	 *
	 * Uses reflection to test the private method.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_topological_sort_respects_dependencies(): void {
		$provider_a = $this->create_mock_provider( 'a', array(), array(), array() );
		$provider_b = $this->create_mock_provider( 'b', array( 'a' ), array(), array() );
		$provider_c = $this->create_mock_provider( 'c', array( 'b' ), array(), array() );

		$providers = array(
			'c' => $provider_c,
			'a' => $provider_a,
			'b' => $provider_b,
		);

		$method = new \ReflectionMethod( ConfigManager::class, 'topological_sort' );
		$method->setAccessible( true );

		$sorted = $method->invoke( $this->manager, $providers );
		$keys   = array_keys( $sorted );

		// 'a' must come before 'b', and 'b' before 'c'.
		$this->assertLessThan(
			array_search( 'b', $keys, true ),
			array_search( 'a', $keys, true )
		);
		$this->assertLessThan(
			array_search( 'c', $keys, true ),
			array_search( 'b', $keys, true )
		);
	}

	/**
	 * Test that topological sort detects circular dependencies.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_topological_sort_detects_circular_dependencies(): void {
		$provider_a = $this->create_mock_provider( 'a', array( 'b' ), array(), array() );
		$provider_b = $this->create_mock_provider( 'b', array( 'a' ), array(), array() );

		$providers = array(
			'a' => $provider_a,
			'b' => $provider_b,
		);

		$method = new \ReflectionMethod( ConfigManager::class, 'topological_sort' );
		$method->setAccessible( true );

		$this->expectException( \RuntimeException::class );
		$method->invoke( $this->manager, $providers );
	}

	/**
	 * Test that topological sort handles providers with no dependencies.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_topological_sort_handles_no_dependencies(): void {
		$provider_a = $this->create_mock_provider( 'a', array(), array(), array() );
		$provider_b = $this->create_mock_provider( 'b', array(), array(), array() );

		$providers = array(
			'a' => $provider_a,
			'b' => $provider_b,
		);

		$method = new \ReflectionMethod( ConfigManager::class, 'topological_sort' );
		$method->setAccessible( true );

		$sorted = $method->invoke( $this->manager, $providers );

		$this->assertCount( 2, $sorted );
	}

	/**
	 * Test that export_all handles multiple config files per provider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_all_handles_multiple_config_files(): void {
		$provider = $this->create_mock_provider(
			'options',
			array(),
			array( 'options/general.yml', 'options/reading.yml' ),
			array(
				'general' => array( 'blogname' => 'Test' ),
				'reading' => array( 'posts_per_page' => 10 ),
			)
		);

		$this->container->method( 'get_providers' )->willReturn( array(
			'options' => $provider,
		) );

		$this->file_handler->expects( $this->exactly( 2 ) )
			->method( 'write' );

		$result = $this->manager->export_all();

		$this->assertSame( 2, $result['providers']['options']['file_count'] );
	}

	/**
	 * Test that import_all sorts providers by dependencies before importing.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_all_sorts_providers_by_dependencies(): void {
		$this->lock->method( 'acquire' )->willReturn( true );
		$this->schema_validator->method( 'validate' )->willReturn( true );
		$this->file_handler->method( 'read' )->willReturn( array() );

		$import_order = array();

		$provider_a = $this->create_mock_provider( 'a', array(), array( 'a/data.yml' ), array() );
		$provider_a->method( 'import' )->willReturnCallback( function () use ( &$import_order ) {
			$import_order[] = 'a';
			return array( 'created' => 0, 'updated' => 0, 'deleted' => 0, 'details' => array() );
		} );

		$provider_b = $this->create_mock_provider( 'b', array( 'a' ), array( 'b/data.yml' ), array() );
		$provider_b->method( 'import' )->willReturnCallback( function () use ( &$import_order ) {
			$import_order[] = 'b';
			return array( 'created' => 0, 'updated' => 0, 'deleted' => 0, 'details' => array() );
		} );

		// Register in reverse order to prove sorting works.
		$this->container->method( 'get_providers' )->willReturn( array(
			'b' => $provider_b,
			'a' => $provider_a,
		) );

		$this->manager->import_all();

		$this->assertSame( array( 'a', 'b' ), $import_order );
	}
}
