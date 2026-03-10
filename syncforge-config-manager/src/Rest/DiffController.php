<?php
/**
 * REST API controller for configuration diff operations.
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
 * Class DiffController
 *
 * Handles the REST API endpoint for computing the diff between
 * the current database state and YAML configuration files on disk.
 *
 * @since 1.0.0
 */
class DiffController extends WP_REST_Controller {

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
	protected $rest_base = 'diff';

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
	 * Register REST API routes for diff operations.
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
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_diff' ),
					'permission_callback' => array( $this, 'check_permissions' ),
				),
			)
		);
	}

	/**
	 * Check if the current user has permission to view configuration diffs.
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
	 * Compute and return the diff between database and YAML files.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Structured diff data keyed by provider.
	 */
	public function get_diff( WP_REST_Request $request ): WP_REST_Response {
		$diff = $this->config_manager->diff();

		$has_changes = ! empty( $diff );

		return new WP_REST_Response(
			array(
				'has_changes' => $has_changes,
				'providers'   => $diff,
			),
			200
		);
	}
}
