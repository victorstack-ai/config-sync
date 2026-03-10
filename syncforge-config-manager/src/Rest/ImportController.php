<?php
/**
 * REST API controller for configuration import operations.
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
 * Class ImportController
 *
 * Handles REST API endpoints for importing configuration data
 * from YAML files into the database.
 *
 * @since 1.0.0
 */
class ImportController extends WP_REST_Controller {

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
	protected $rest_base = 'import';

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
	 * Register REST API routes for import operations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$dry_run_arg = array(
			'dry_run' => array(
				'required'          => false,
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'description'       => __( 'If true, preview changes without applying them.', 'syncforge-config-manager' ),
			),
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_all' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => $dry_run_arg,
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<provider>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_provider' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array_merge(
						$dry_run_arg,
						array(
							'provider' => array(
								'required'          => true,
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_text_field',
								'description'       => __( 'The provider identifier to import.', 'syncforge-config-manager' ),
							),
						)
					),
				),
			)
		);
	}

	/**
	 * Check if the current user has permission to perform import operations.
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
	 * Import configuration for all registered providers.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Import results or error.
	 */
	public function import_all( WP_REST_Request $request ) {
		$dry_run = (bool) $request->get_param( 'dry_run' );

		$result = $this->config_manager->import_all( $dry_run );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$has_errors = ! empty( $result['errors'] );

		return new WP_REST_Response(
			array(
				'success'   => ! $has_errors,
				'providers' => $result['providers'],
				'errors'    => $result['errors'],
				'dry_run'   => $result['dry_run'],
			),
			$has_errors ? 207 : 200
		);
	}

	/**
	 * Import configuration for a single provider.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Import results or error.
	 */
	public function import_provider( WP_REST_Request $request ) {
		$provider_id = sanitize_text_field( $request->get_param( 'provider' ) );
		$dry_run     = (bool) $request->get_param( 'dry_run' );

		$result = $this->config_manager->import_provider( $provider_id, $dry_run );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$has_errors = ! empty( $result['errors'] );

		return new WP_REST_Response(
			array(
				'success'   => ! $has_errors,
				'providers' => $result['providers'],
				'errors'    => $result['errors'],
				'dry_run'   => $result['dry_run'],
			),
			$has_errors ? 207 : 200
		);
	}
}
