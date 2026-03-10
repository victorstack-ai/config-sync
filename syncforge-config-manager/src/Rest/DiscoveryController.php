<?php
/**
 * REST controller for option auto-discovery.
 *
 * @package ConfigSync\Rest
 * @since   1.1.0
 */

namespace ConfigSync\Rest;

use ConfigSync\Admin\OptionDiscovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class DiscoveryController
 *
 * Provides REST endpoints for discovering and tracking non-core options.
 *
 * @since 1.1.0
 */
class DiscoveryController extends \WP_REST_Controller {

	/**
	 * Route namespace.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	protected $namespace = 'syncforge/v1';

	/**
	 * Option discovery instance.
	 *
	 * @since 1.1.0
	 * @var OptionDiscovery
	 */
	private OptionDiscovery $discovery;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param OptionDiscovery $discovery Option discovery instance.
	 */
	public function __construct( OptionDiscovery $discovery ) {
		$this->discovery = $discovery;
	}

	/**
	 * Register REST routes.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/discover',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_discovered_options' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'include_values' => array(
							'type'              => 'boolean',
							'default'           => false,
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
						'search'         => array(
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/discover/track',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'track_option' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'option_name' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/discover/untrack',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'untrack_option' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'option_name' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_key',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/discover/track-bulk',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'track_bulk' ),
					'permission_callback' => array( $this, 'check_permissions' ),
					'args'                => array(
						'option_names' => array(
							'type'     => 'array',
							'required' => true,
							'items'    => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Check if the current user has permission.
	 *
	 * @since 1.1.0
	 *
	 * @return bool|\WP_Error
	 */
	public function check_permissions() {
		if ( ! current_user_can( 'manage_config_sync' ) ) {
			return new \WP_Error(
				'config_sync_forbidden',
				__( 'You do not have permission to manage configuration.', 'syncforge-config-manager' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get discovered non-core options.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_discovered_options( \WP_REST_Request $request ): \WP_REST_Response {
		$include_values = $request->get_param( 'include_values' );
		$search         = $request->get_param( 'search' );

		$options = $this->discovery->discover( $include_values );

		// Filter by search term if provided.
		if ( ! empty( $search ) ) {
			$options = array_filter(
				$options,
				static function ( $item ) use ( $search ) {
					return false !== stripos( $item['name'], $search );
				}
			);
		}

		$tracked_count   = count( array_filter( $options, static function ( $item ) {
			return $item['tracked'];
		} ) );
		$untracked_count = count( $options ) - $tracked_count;

		return new \WP_REST_Response(
			array(
				'options'         => array_values( $options ),
				'total'           => count( $options ),
				'tracked_count'   => $tracked_count,
				'untracked_count' => $untracked_count,
			),
			200
		);
	}

	/**
	 * Track a single option.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function track_option( \WP_REST_Request $request ): \WP_REST_Response {
		$option_name = $request->get_param( 'option_name' );

		$this->discovery->track_option( $option_name );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s: option name */
					__( 'Now tracking option: %s', 'syncforge-config-manager' ),
					$option_name
				),
			),
			200
		);
	}

	/**
	 * Untrack a single option.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function untrack_option( \WP_REST_Request $request ): \WP_REST_Response {
		$option_name = $request->get_param( 'option_name' );

		$this->discovery->untrack_option( $option_name );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s: option name */
					__( 'Stopped tracking option: %s', 'syncforge-config-manager' ),
					$option_name
				),
			),
			200
		);
	}

	/**
	 * Bulk track multiple options.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function track_bulk( \WP_REST_Request $request ): \WP_REST_Response {
		$option_names = $request->get_param( 'option_names' );
		$current      = OptionDiscovery::get_tracked_options();
		$merged       = array_unique( array_merge( $current, array_map( 'sanitize_key', $option_names ) ) );

		$this->discovery->save_tracked_options( $merged );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'tracked' => count( $merged ),
				'message' => sprintf(
					/* translators: %d: number of options */
					__( 'Now tracking %d discovered options.', 'syncforge-config-manager' ),
					count( $merged )
				),
			),
			200
		);
	}
}
