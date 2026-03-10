<?php
/**
 * Settings page registration and rendering.
 *
 * Registers the Config Sync settings page under Settings using the
 * WordPress Settings API.
 *
 * @package ConfigSync\Admin
 * @since   1.0.0
 */

namespace ConfigSync\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsPage
 *
 * Manages plugin settings via the WordPress Settings API.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Menu slug for the settings page.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const MENU_SLUG = 'syncforge-settings';

	/**
	 * Option name stored in the wp_options table.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_NAME = 'config_sync_settings';

	/**
	 * Settings group name used with register_setting().
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SETTINGS_GROUP = 'config_sync_settings_group';

	/**
	 * Settings section ID.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SECTION_ID = 'config_sync_general';

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the settings page under the Settings menu.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'SyncForge Config Manager', 'syncforge-config-manager' ),
			__( 'SyncForge', 'syncforge-config-manager' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			self::SETTINGS_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);

		add_settings_section(
			self::SECTION_ID,
			__( 'General Settings', 'syncforge-config-manager' ),
			array( $this, 'render_section_description' ),
			self::MENU_SLUG
		);

		add_settings_field(
			'config_directory',
			__( 'Config Directory', 'syncforge-config-manager' ),
			array( $this, 'render_config_directory_field' ),
			self::MENU_SLUG,
			self::SECTION_ID,
			array( 'label_for' => 'config_sync_config_directory' )
		);

		add_settings_field(
			'environment',
			__( 'Environment Name', 'syncforge-config-manager' ),
			array( $this, 'render_environment_field' ),
			self::MENU_SLUG,
			self::SECTION_ID,
			array( 'label_for' => 'config_sync_environment' )
		);

		add_settings_field(
			'excluded_options',
			__( 'Excluded Options', 'syncforge-config-manager' ),
			array( $this, 'render_excluded_options_field' ),
			self::MENU_SLUG,
			self::SECTION_ID,
			array( 'label_for' => 'config_sync_excluded_options' )
		);

		add_settings_field(
			'audit_retention_days',
			__( 'Audit Retention (Days)', 'syncforge-config-manager' ),
			array( $this, 'render_audit_retention_field' ),
			self::MENU_SLUG,
			self::SECTION_ID,
			array( 'label_for' => 'config_sync_audit_retention_days' )
		);
	}

	/**
	 * Get default settings values.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Default settings.
	 */
	public static function get_defaults(): array {
		return array(
			'config_directory'     => 'syncforge-config-manager',
			'environment'          => 'production',
			'excluded_options'     => '',
			'audit_retention_days' => 30,
		);
	}

	/**
	 * Sanitize all settings before saving.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input Raw input from the settings form.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public function sanitize_settings( $input ): array {
		$defaults  = self::get_defaults();
		$sanitized = array();

		$sanitized['config_directory'] = isset( $input['config_directory'] )
			? sanitize_file_name( $input['config_directory'] )
			: $defaults['config_directory'];

		$sanitized['environment'] = isset( $input['environment'] )
			? sanitize_key( $input['environment'] )
			: $defaults['environment'];

		$sanitized['excluded_options'] = isset( $input['excluded_options'] )
			? sanitize_textarea_field( $input['excluded_options'] )
			: $defaults['excluded_options'];

		$retention = isset( $input['audit_retention_days'] )
			? absint( $input['audit_retention_days'] )
			: $defaults['audit_retention_days'];

		$sanitized['audit_retention_days'] = max( 1, min( 365, $retention ) );

		return $sanitized;
	}

	/**
	 * Render the settings section description.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_section_description(): void {
		echo '<p>' . esc_html__( 'Configure how SyncForge Config Manager manages your site configuration.', 'syncforge-config-manager' ) . '</p>';
	}

	/**
	 * Render the config directory field.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_config_directory_field(): void {
		$options = get_option( self::OPTION_NAME, self::get_defaults() );
		$value   = $options['config_directory'] ?? self::get_defaults()['config_directory'];

		printf(
			'<input type="text" id="config_sync_config_directory" name="%s[config_directory]" value="%s" class="regular-text" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'Directory name relative to wp-content where config files are stored.', 'syncforge-config-manager' ) . '</p>';
	}

	/**
	 * Render the environment name field.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_environment_field(): void {
		$options = get_option( self::OPTION_NAME, self::get_defaults() );
		$value   = $options['environment'] ?? self::get_defaults()['environment'];

		printf(
			'<input type="text" id="config_sync_environment" name="%s[environment]" value="%s" class="regular-text" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'Current environment name (e.g., production, staging, development).', 'syncforge-config-manager' ) . '</p>';
	}

	/**
	 * Render the excluded options textarea field.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_excluded_options_field(): void {
		$options = get_option( self::OPTION_NAME, self::get_defaults() );
		$value   = $options['excluded_options'] ?? self::get_defaults()['excluded_options'];

		printf(
			'<textarea id="config_sync_excluded_options" name="%s[excluded_options]" rows="6" cols="50" class="large-text">%s</textarea>',
			esc_attr( self::OPTION_NAME ),
			esc_textarea( $value )
		);
		echo '<p class="description">' . esc_html__( 'One option name per line. These options will be excluded from export and import.', 'syncforge-config-manager' ) . '</p>';
	}

	/**
	 * Render the audit retention days field.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_audit_retention_field(): void {
		$options = get_option( self::OPTION_NAME, self::get_defaults() );
		$value   = $options['audit_retention_days'] ?? self::get_defaults()['audit_retention_days'];

		printf(
			'<input type="number" id="config_sync_audit_retention_days" name="%s[audit_retention_days]" value="%s" min="1" max="365" class="small-text" />',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $value )
		);
		echo '<p class="description">' . esc_html__( 'Number of days to retain audit log entries (1-365).', 'syncforge-config-manager' ) . '</p>';
	}

	/**
	 * Render the settings page.
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

		include CONFIG_SYNC_DIR . 'templates/settings-page.php';
	}
}
