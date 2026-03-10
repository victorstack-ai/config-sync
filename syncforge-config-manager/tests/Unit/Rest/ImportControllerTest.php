<?php
/**
 * Unit tests for the ImportController class.
 *
 * @package ConfigSync\Tests\Unit\Rest
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\ConfigManager;
use ConfigSync\Rest\ImportController;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use WP_REST_Request;

/**
 * Class ImportControllerTest
 *
 * @since 1.0.0
 */
class ImportControllerTest extends TestCase {

	/**
	 * ImportController instance under test.
	 *
	 * @since 1.0.0
	 * @var ImportController
	 */
	private ImportController $controller;

	/**
	 * Mock config manager.
	 *
	 * @since 1.0.0
	 * @var ConfigManager|\PHPUnit\Framework\MockObject\MockObject
	 */
	private $config_manager;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->config_manager = $this->createMock( ConfigManager::class );
		$this->controller     = new ImportController( $this->config_manager );
	}

	/**
	 * Test that routes are registered correctly.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_register_routes(): void {
		$this->controller->register_routes();

		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/syncforge/v1/import', $routes );
		$this->assertArrayHasKey( '/syncforge/v1/import/(?P<provider>[a-zA-Z0-9_-]+)', $routes );
	}

	/**
	 * Test that the permission callback rejects unauthorized users.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_check_permissions_rejects_unauthorized_users(): void {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/syncforge/v1/import' );
		$result  = $this->controller->check_permissions( $request );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'config_sync_rest_forbidden', $result->get_error_code() );
	}

	/**
	 * Test that the permission callback allows authorized users.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_check_permissions_allows_authorized_users(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'POST', '/syncforge/v1/import' );
		$result  = $this->controller->check_permissions( $request );

		$this->assertTrue( $result );
	}

	/**
	 * Test import_all returns success response format.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_all_returns_success_response(): void {
		$this->config_manager
			->expects( $this->once() )
			->method( 'import_all' )
			->with( false )
			->willReturn(
				array(
					'providers' => array(
						'options' => array(
							'created' => 2,
							'updated' => 3,
							'deleted' => 0,
						),
					),
					'errors'    => array(),
					'dry_run'   => false,
				)
			);

		$request  = new WP_REST_Request( 'POST', '/syncforge/v1/import' );
		$response = $this->controller->import_all( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertFalse( $data['dry_run'] );
		$this->assertArrayHasKey( 'providers', $data );
		$this->assertArrayHasKey( 'errors', $data );
	}

	/**
	 * Test import_all with dry_run parameter.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_all_with_dry_run(): void {
		$this->config_manager
			->expects( $this->once() )
			->method( 'import_all' )
			->with( true )
			->willReturn(
				array(
					'providers' => array(
						'options' => array(
							'created' => 2,
							'updated' => 3,
							'deleted' => 0,
						),
					),
					'errors'    => array(),
					'dry_run'   => true,
				)
			);

		$request = new WP_REST_Request( 'POST', '/syncforge/v1/import' );
		$request->set_param( 'dry_run', true );

		$response = $this->controller->import_all( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['dry_run'] );
	}

	/**
	 * Test import_all returns WP_Error when lock cannot be acquired.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_all_returns_wp_error_when_locked(): void {
		$this->config_manager
			->expects( $this->once() )
			->method( 'import_all' )
			->willReturn(
				new \WP_Error(
					'config_sync_locked',
					'Another import operation is in progress.'
				)
			);

		$request  = new WP_REST_Request( 'POST', '/syncforge/v1/import' );
		$response = $this->controller->import_all( $request );

		$this->assertInstanceOf( \WP_Error::class, $response );
		$this->assertSame( 'config_sync_locked', $response->get_error_code() );
	}

	/**
	 * Test import_all returns partial success with errors.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_all_returns_partial_success_on_errors(): void {
		$this->config_manager
			->expects( $this->once() )
			->method( 'import_all' )
			->willReturn(
				array(
					'providers' => array(
						'options' => array(
							'created' => 1,
							'updated' => 0,
							'deleted' => 0,
						),
					),
					'errors'    => array(
						'roles' => 'Validation failed.',
					),
					'dry_run'   => false,
				)
			);

		$request  = new WP_REST_Request( 'POST', '/syncforge/v1/import' );
		$response = $this->controller->import_all( $request );

		$this->assertSame( 207, $response->get_status() );

		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertNotEmpty( $data['errors'] );
	}

	/**
	 * Test import_provider returns success response format.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_provider_returns_success_response(): void {
		$this->config_manager
			->expects( $this->once() )
			->method( 'import_provider' )
			->with( 'options', false )
			->willReturn(
				array(
					'providers' => array(
						'options' => array(
							'created' => 2,
							'updated' => 1,
							'deleted' => 0,
						),
					),
					'errors'    => array(),
					'dry_run'   => false,
				)
			);

		$request = new WP_REST_Request( 'POST', '/syncforge/v1/import/options' );
		$request->set_url_params( array( 'provider' => 'options' ) );

		$response = $this->controller->import_provider( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test import_provider with dry_run parameter.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_provider_with_dry_run(): void {
		$this->config_manager
			->expects( $this->once() )
			->method( 'import_provider' )
			->with( 'options', true )
			->willReturn(
				array(
					'providers' => array(
						'options' => array(
							'created' => 2,
							'updated' => 1,
							'deleted' => 0,
						),
					),
					'errors'    => array(),
					'dry_run'   => true,
				)
			);

		$request = new WP_REST_Request( 'POST', '/syncforge/v1/import/options' );
		$request->set_url_params( array( 'provider' => 'options' ) );
		$request->set_param( 'dry_run', true );

		$response = $this->controller->import_provider( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['dry_run'] );
	}
}
