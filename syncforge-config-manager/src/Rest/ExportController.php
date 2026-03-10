<?php
/**
 * REST API controller for configuration export operations.
 *
 * @package ConfigSync\Rest
 * @since   1.0.0
 */

namespace ConfigSync\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\ConfigManager;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class ExportController
 *
 * Handles REST API endpoints for exporting configuration data
 * from database to YAML files.
 *
 * @since 1.0.0
 */
class ExportController extends WP_REST_Controller {

	/**
	 * Route namespace.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $namespace = 'syncforge/v1';

	/**
	 * Route base.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $rest_base = 'export';

	/**
	 * Config manager instance.
	 *
	 * @since 1.0.0
	 * @var ConfigManager
	 */
	private ConfigManager $config_manager;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param ConfigManager $config_manager Config manager instance.
	 */
	public function __construct( ConfigManager $config_manager ) {
		$this->config_manager = $config_manager;
	}

	/**
	 * Register REST API routes for export operations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'export_all' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<provider>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'export_provider' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'provider' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => __( 'The provider identifier to export.', 'syncforge-config-manager' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Check if the current user has permission to perform export operations.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error True if the user has permission, WP_Error otherwise.
	 */
	public function check_permissions( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_config_sync' ) ) {
			return new WP_Error(
				'config_sync_rest_forbidden',
				esc_html__( 'You do not have permission to perform this action.', 'syncforge-config-manager' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Export configuration from all registered providers.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Export results.
	 */
	public function export_all( WP_REST_Request $request ): WP_REST_Response {
		$result = $this->config_manager->export_all();

		$has_errors = ! empty( $result['errors'] );

		return new WP_REST_Response(
			array(
				'success'   => ! $has_errors,
				'providers' => $result['providers'],
				'errors'    => $result['errors'],
			),
			$has_errors ? 207 : 200
		);
	}

	/**
	 * Export configuration from a single provider.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Export results for the specified provider.
	 */
	public function export_provider( WP_REST_Request $request ): WP_REST_Response {
		$provider_id = sanitize_text_field( $request->get_param( 'provider' ) );

		$result = $this->config_manager->export_provider( $provider_id );

		$has_errors = ! empty( $result['errors'] );

		return new WP_REST_Response(
			array(
				'success'   => ! $has_errors,
				'providers' => $result['providers'],
				'errors'    => $result['errors'],
			),
			$has_errors ? 207 : 200
		);
	}
}
