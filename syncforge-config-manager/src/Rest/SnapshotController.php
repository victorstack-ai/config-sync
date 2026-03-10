<?php
/**
 * REST API controller for snapshot and rollback operations.
 *
 * @package ConfigSync\Rest
 * @since   1.0.0
 */

namespace ConfigSync\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\AuditLogger;
use ConfigSync\ConfigManager;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class SnapshotController
 *
 * Handles REST API endpoints for listing snapshots and triggering
 * rollback operations to a previous configuration state.
 *
 * @since 1.0.0
 */
class SnapshotController extends WP_REST_Controller {

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
	protected $rest_base = 'snapshots';

	/**
	 * Config manager instance.
	 *
	 * @since 1.0.0
	 * @var ConfigManager
	 */
	private ConfigManager $config_manager;

	/**
	 * Audit logger instance.
	 *
	 * @since 1.0.0
	 * @var AuditLogger
	 */
	private AuditLogger $audit_logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param ConfigManager $config_manager Config manager instance.
	 * @param AuditLogger   $audit_logger   Audit logger instance.
	 */
	public function __construct( ConfigManager $config_manager, AuditLogger $audit_logger ) {
		$this->config_manager = $config_manager;
		$this->audit_logger   = $audit_logger;
	}

	/**
	 * Register REST API routes for snapshot and rollback operations.
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
					'callback'            => array( $this, 'list_snapshots' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'per_page' => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 20,
							'minimum'           => 1,
							'maximum'           => 100,
							'sanitize_callback' => 'absint',
							'description'       => __( 'Maximum number of snapshots to return.', 'syncforge-config-manager' ),
						),
						'page'     => array(
							'required'          => false,
							'type'              => 'integer',
							'default'           => 1,
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
							'description'       => __( 'Current page of the collection.', 'syncforge-config-manager' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/rollback/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'rollback' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
							'description'       => __( 'The snapshot ID to rollback to.', 'syncforge-config-manager' ),
						),
					),
				),
			)
		);
	}

	/**
	 * Check if the current user has permission to manage snapshots.
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
	 * List available snapshots from the audit log.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Paginated list of snapshots.
	 */
	public function list_snapshots( WP_REST_Request $request ): WP_REST_Response {
		$per_page = absint( $request->get_param( 'per_page' ) );
		$page     = absint( $request->get_param( 'page' ) );
		$offset   = ( $page - 1 ) * $per_page;

		$snapshots = $this->audit_logger->list_snapshots( $per_page, $offset );

		return new WP_REST_Response(
			array(
				'snapshots' => $snapshots,
				'page'      => $page,
				'per_page'  => $per_page,
			),
			200
		);
	}

	/**
	 * Rollback to a previous snapshot.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Rollback results or error.
	 */
	public function rollback( WP_REST_Request $request ) {
		$snapshot_id = absint( $request->get_param( 'id' ) );

		$result = $this->config_manager->rollback( $snapshot_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$has_errors = ! empty( $result['errors'] );

		return new WP_REST_Response(
			array(
				'success'     => ! $has_errors,
				'snapshot_id' => $result['snapshot_id'],
				'providers'   => $result['providers'],
				'errors'      => $result['errors'],
			),
			$has_errors ? 207 : 200
		);
	}
}
