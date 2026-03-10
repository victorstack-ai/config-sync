<?php
/**
 * Unit tests for the ExportController class.
 *
 * @package ConfigSync\Tests\Unit\Rest
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\ConfigManager;
use ConfigSync\Rest\ExportController;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use WP_REST_Request;

/**
 * Class ExportControllerTest
 *
 * @since 1.0.0
 */
class ExportControllerTest extends TestCase {

	/**
	 * ExportController instance under test.
	 *
	 * @since 1.0.0
	 * @var ExportController
	 */
	private ExportController $controller;

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
		$this->controller     = new ExportController( $this->config_manager );
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

		$this->assertArrayHasKey( '/syncforge/v1/export', $routes );
		$this->assertArrayHasKey( '/syncforge/v1/export/(?P<provider>[a-zA-Z0-9_-]+)', $routes );
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

		$request = new WP_REST_Request( 'POST', '/syncforge/v1/export' );
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

		$request = new WP_REST_Request( 'POST', '/syncforge/v1/export' );
		$result  = $this->controller->check_permissions( $request );

		$this->assertTrue( $result );
	}

	/**
	 * Test export_all returns success response format.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_all_returns_success_response(): void {
		$this->config_manager
			->expects( $this->once() )
			->method( 'export_all' )
			->willReturn(
				array(
					'providers' => array(
						'options' => array(
							'file_count' => 1,
							'item_count' => 10,
						),
					),
					'errors'    => array(),
				)
			);

		$request  = new WP_REST_Request( 'POST', '/syncforge/v1/export' );
		$response = $this->controller->export_all( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'providers', $data );
		$this->assertArrayHasKey( 'errors', $data );
	}

	/**
	 * Test export_all returns partial success with errors.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_all_returns_partial_success_on_errors(): void {
		$this->config_manager
			->expects( $this->once() )
			->method( 'export_all' )
			->willReturn(
				array(
					'providers' => array(
						'options' => array(
							'file_count' => 1,
							'item_count' => 5,
						),
					),
					'errors'    => array(
						'roles' => 'Provider export failed.',
					),
				)
			);

		$request  = new WP_REST_Request( 'POST', '/syncforge/v1/export' );
		$response = $this->controller->export_all( $request );

		$this->assertSame( 207, $response->get_status() );

		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertNotEmpty( $data['errors'] );
	}

	/**
	 * Test export_provider returns success response format.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_provider_returns_success_response(): void {
		$this->config_manager
			->expects( $this->once() )
			->method( 'export_provider' )
			->with( 'options' )
			->willReturn(
				array(
					'providers' => array(
						'options' => array(
							'file_count' => 1,
							'item_count' => 10,
						),
					),
					'errors'    => array(),
				)
			);

		$request = new WP_REST_Request( 'POST', '/syncforge/v1/export/options' );
		$request->set_url_params( array( 'provider' => 'options' ) );

		$response = $this->controller->export_provider( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test export_provider returns error for unknown provider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_provider_returns_error_for_unknown_provider(): void {
		$this->config_manager
			->expects( $this->once() )
			->method( 'export_provider' )
			->with( 'nonexistent' )
			->willReturn(
				array(
					'providers' => array(),
					'errors'    => array(
						'nonexistent' => 'Provider not found.',
					),
				)
			);

		$request = new WP_REST_Request( 'POST', '/syncforge/v1/export/nonexistent' );
		$request->set_url_params( array( 'provider' => 'nonexistent' ) );

		$response = $this->controller->export_provider( $request );

		$this->assertSame( 207, $response->get_status() );

		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
	}
}
