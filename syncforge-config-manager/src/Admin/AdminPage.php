<?php
/**
 * Admin page registration and rendering.
 *
 * Registers the Config Sync management page under Tools and handles
 * script/style enqueuing for the admin UI.
 *
 * @package ConfigSync\Admin
 * @since   1.0.0
 */

namespace ConfigSync\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminPage
 *
 * Registers the main Config Sync admin page under the Tools menu
 * and enqueues the required assets.
 *
 * @since 1.0.0
 */
class AdminPage {

	/**
	 * Menu slug for the admin page.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const MENU_SLUG = 'syncforge-config-manager';

	/**
	 * Script handle for the admin JS.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SCRIPT_HANDLE = 'syncforge-admin';

	/**
	 * Style handle for the admin CSS.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STYLE_HANDLE = 'syncforge-admin';

	/**
	 * The hook suffix returned by add_management_page().
	 *
	 * @since 1.0.0
	 * @var string|false
	 */
	private $hook_suffix = false;

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add the management page under Tools.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$this->hook_suffix = add_management_page(
			__( 'SyncForge Config Manager', 'syncforge-config-manager' ),
			__( 'SyncForge', 'syncforge-config-manager' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue admin scripts and styles only on this plugin's page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			CONFIG_SYNC_URL . 'assets/admin.js',
			array( 'jquery' ),
			CONFIG_SYNC_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'configSyncAdmin',
			array(
				'restNonce'  => wp_create_nonce( 'wp_rest' ),
				'restUrl'    => esc_url_raw( rest_url( 'syncforge/v1/' ) ),
				'ajaxNonce'  => wp_create_nonce( 'config_sync_zip' ),
				'ajaxUrl'    => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'i18n'       => array(
					'exporting'  => __( 'Exporting...', 'syncforge-config-manager' ),
					'importing'  => __( 'Importing...', 'syncforge-config-manager' ),
					'previewing' => __( 'Loading preview...', 'syncforge-config-manager' ),
					'uploading'  => __( 'Uploading...', 'syncforge-config-manager' ),
					'confirm'    => __( 'This will overwrite database values with YAML file contents. Continue?', 'syncforge-config-manager' ),
				),
			)
		);

		wp_enqueue_style(
			self::STYLE_HANDLE,
			CONFIG_SYNC_URL . 'assets/admin.css',
			array(),
			CONFIG_SYNC_VERSION
		);
	}

	/**
	 * Render the admin page.
	 *
	 * Performs a capability check before including the template.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		include CONFIG_SYNC_DIR . 'templates/admin-page.php';
	}

	/**
	 * Get the hook suffix for this page.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The hook suffix, or false if not yet registered.
	 */
	public function get_hook_suffix() {
		return $this->hook_suffix;
	}
}
