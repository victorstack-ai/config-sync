<?php
/**
 * Main plugin wiring class.
 *
 * Bootstraps all services, hooks, providers, REST routes, and CLI commands.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Central orchestrator that wires the Container, registers providers,
 * hooks into WordPress, and exposes activation/deactivation routines.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Service container instance.
	 *
	 * @since 1.0.0
	 * @var Container
	 */
	private Container $container;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Container $container Service container instance.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Initialize the plugin.
	 *
	 * Called on the `plugins_loaded` hook. Registers providers,
	 * initializes services in the container, and registers hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_providers();
		$this->init_services();
		$this->register_hooks();
	}

	/**
	 * Register all WordPress hooks.
	 *
	 * Wires admin pages, REST routes, text domain, custom capabilities,
	 * and CLI commands into the appropriate WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		$admin_page    = new Admin\AdminPage();
		$settings_page = new Admin\SettingsPage();

		$admin_page->register();
		$settings_page->register();

		$zip_handler = new Admin\ZipHandler( $this->get_config_dir() );
		$zip_handler->register();

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Text domain is auto-loaded since WP 4.6 for plugins hosted on WordPress.org.
		add_action( 'init', array( $this, 'register_capability' ) );
		add_action( 'init', array( $this, 'schedule_audit_prune' ) );

		add_action( 'config_sync_prune_audit_log', array( __CLASS__, 'run_audit_prune' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}
	}

	/**
	 * Register all configuration providers.
	 *
	 * Instantiates the seven built-in providers and applies the
	 * `config_sync_providers` filter so third-party code can add more.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_providers(): void {
		$providers = array(
			new Provider\OptionsProvider(),
			new Provider\RolesProvider(),
			new Provider\MenusProvider(),
			new Provider\WidgetsProvider(),
			new Provider\ThemeModsProvider(),
			new Provider\RewriteProvider(),
			new Provider\BlockPatternsProvider(),
		);

		/**
		 * Filters the list of configuration providers.
		 *
		 * Allows third-party plugins to register additional providers
		 * by appending to the array.
		 *
		 * @since 1.0.0
		 *
		 * @param Provider\ProviderInterface[] $providers Array of provider instances.
		 */
		$providers = apply_filters( 'config_sync_providers', $providers );

		foreach ( $providers as $provider ) {
			$this->container->add_provider( $provider );
		}
	}

	/**
	 * Register all REST API route controllers.
	 *
	 * Instantiates the four REST controllers and calls their
	 * register_routes() methods.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		$config_manager = $this->container->get_config_manager();
		$audit_logger   = $this->container->get_audit_logger();

		$controllers = array(
			new Rest\ExportController( $config_manager ),
			new Rest\ImportController( $config_manager ),
			new Rest\DiffController( $config_manager ),
			new Rest\SnapshotController( $config_manager, $audit_logger ),
			new Rest\DiscoveryController( new Admin\OptionDiscovery() ),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Register all WP-CLI commands.
	 *
	 * Registers the four CLI commands under the `syncforge` namespace.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_cli_commands(): void {
		\WP_CLI::add_command( 'syncforge export', new CLI\ExportCommand( $this->container ) );
		\WP_CLI::add_command( 'syncforge import', new CLI\ImportCommand( $this->container ) );
		\WP_CLI::add_command( 'syncforge diff', new CLI\DiffCommand( $this->container ) );
		\WP_CLI::add_command( 'syncforge status', new CLI\StatusCommand( $this->container ) );
		\WP_CLI::add_command( 'syncforge discover', new CLI\DiscoverCommand() );
	}

	/**
	 * Register the custom `manage_config_sync` capability.
	 *
	 * Adds the capability to the administrator role if it does not
	 * already exist.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_capability(): void {
		$role = get_role( 'administrator' );

		if ( $role && ! $role->has_cap( 'manage_config_sync' ) ) {
			$role->add_cap( 'manage_config_sync' );
		}
	}

	/**
	 * Plugin activation callback.
	 *
	 * Creates database tables, the default config directory, and
	 * adds the custom capability to the administrator role.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function activate(): void {
		self::create_tables();
		self::create_config_directory();

		$role = get_role( 'administrator' );

		if ( $role ) {
			$role->add_cap( 'manage_config_sync' );
		}
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * Cleans up transients and optionally removes the custom capability.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		delete_option( 'config_sync_lock' );
		wp_clear_scheduled_hook( 'config_sync_prune_audit_log' );

		$role = get_role( 'administrator' );

		if ( $role ) {
			$role->remove_cap( 'manage_config_sync' );
		}
	}

	/**
	 * Return the SQL schema for dbDelta.
	 *
	 * Strict formatting is required: two spaces after PRIMARY KEY,
	 * key name in parentheses.
	 *
	 * @since 1.0.0
	 *
	 * @return string SQL statements for creating plugin database tables.
	 */
	public static function get_db_schema(): string {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		return "CREATE TABLE {$wpdb->prefix}config_sync_id_map (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  provider varchar(64) NOT NULL,
  stable_key varchar(255) NOT NULL,
  local_id bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY provider_stable_key (provider, stable_key),
  KEY local_id (local_id)
) {$charset_collate};
CREATE TABLE {$wpdb->prefix}config_sync_audit_log (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  action varchar(20) NOT NULL,
  provider varchar(64) NOT NULL,
  environment varchar(64) NOT NULL DEFAULT '',
  diff_data longtext NOT NULL,
  snapshot_data longtext NOT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY action_provider (action, provider),
  KEY created_at (created_at)
) {$charset_collate};";
	}

	/**
	 * Get the resolved configuration directory path.
	 *
	 * Uses the config_directory setting (relative to wp-content) or default 'syncforge-config-manager'.
	 *
	 * @since 1.0.0
	 *
	 * @return string Absolute path to the config directory.
	 */
	private function get_config_dir(): string {
		$settings = get_option( 'config_sync_settings', array() );
		$subdir   = isset( $settings['config_directory'] ) ? $settings['config_directory'] : 'syncforge-config-manager';
		$subdir   = sanitize_file_name( $subdir );
		if ( '' === $subdir ) {
			$subdir = 'syncforge-config-manager';
		}

		return trailingslashit( WP_CONTENT_DIR ) . $subdir;
	}

	/**
	 * Initialize core services in the container.
	 *
	 * Creates and registers all service instances that the container
	 * exposes (ConfigManager, FileHandler, DiffEngine, etc.).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_services(): void {
		$config_dir = $this->get_config_dir();

		$this->container->set_file_handler( new FileHandler( $config_dir ) );
		$this->container->set_diff_engine( new DiffEngine() );
		$this->container->set_schema_validator( new SchemaValidator() );
		$this->container->set_id_mapper( new IdMapper() );
		$this->container->set_lock( new Lock() );
		$this->container->set_audit_logger( new AuditLogger() );
		$this->container->set_environment_override( new Override\EnvironmentOverride( $this->container->get_file_handler() ) );
		$this->container->set_yaml_sanitizer( new Sanitizer\YamlSanitizer() );
		$this->container->set_config_manager( new ConfigManager( $this->container ) );
	}

	/**
	 * Create the plugin database tables using dbDelta.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function create_tables(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::get_db_schema() );

		update_option( 'config_sync_db_version', CONFIG_SYNC_DB_VERSION );
	}

	/**
	 * Get the default configuration directory path (used at activation).
	 *
	 * @since 1.0.0
	 *
	 * @return string Absolute path to the default config directory.
	 */
	private static function get_default_config_dir(): string {
		$settings = get_option( 'config_sync_settings', array() );
		$subdir   = isset( $settings['config_directory'] ) ? $settings['config_directory'] : 'syncforge-config-manager';
		$subdir   = sanitize_file_name( $subdir );
		if ( '' === $subdir ) {
			$subdir = 'syncforge-config-manager';
		}

		return trailingslashit( WP_CONTENT_DIR ) . $subdir;
	}

	/**
	 * Create the default configuration directory using WP_Filesystem.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function create_config_directory(): void {
		$config_dir = self::get_default_config_dir();

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_Filesystem( false, false, true );
		} else {
			\WP_Filesystem();
		}

		global $wp_filesystem;
		if ( ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
			$wp_filesystem = new \WP_Filesystem_Direct( null );
		}

		if ( ! $wp_filesystem->is_dir( $config_dir ) ) {
			wp_mkdir_p( $config_dir );
		}

		$htaccess_content = "# Apache 2.4\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n\n# Apache 2.2\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n";
		$htaccess         = trailingslashit( $config_dir ) . '.htaccess';
		if ( ! $wp_filesystem->exists( $htaccess ) ) {
			$wp_filesystem->put_contents( $htaccess, $htaccess_content, FS_CHMOD_FILE );
		}

		$index_content = "<?php\n// Silence is golden.\n";
		$index         = trailingslashit( $config_dir ) . 'index.php';
		if ( ! $wp_filesystem->exists( $index ) ) {
			$wp_filesystem->put_contents( $index, $index_content, FS_CHMOD_FILE );
		}
	}

	/**
	 * Schedule the daily audit log prune event if not already scheduled.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function schedule_audit_prune(): void {
		if ( wp_next_scheduled( 'config_sync_prune_audit_log' ) ) {
			return;
		}

		wp_schedule_event( time(), 'daily', 'config_sync_prune_audit_log' );
	}

	/**
	 * Run the audit log prune (called by cron).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function run_audit_prune(): void {
		$container = config_sync();
		$logger    = $container->get_audit_logger();
		$settings  = get_option( 'config_sync_settings', array() );
		$days      = isset( $settings['audit_retention_days'] ) ? max( 1, min( 365, (int) $settings['audit_retention_days'] ) ) : 90;
		$logger->prune( $days );
	}
}
