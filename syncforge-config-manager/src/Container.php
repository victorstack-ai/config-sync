<?php
/**
 * Service container (service locator).
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Container
 *
 * Holds references to all plugin services and providers.
 * Accessed globally via config_sync().
 *
 * @since 1.0.0
 */
class Container {

	/**
	 * Config manager instance.
	 *
	 * @since 1.0.0
	 * @var ConfigManager|null
	 */
	private ?ConfigManager $config_manager = null;

	/**
	 * Diff engine instance.
	 *
	 * @since 1.0.0
	 * @var DiffEngine|null
	 */
	private ?DiffEngine $diff_engine = null;

	/**
	 * File handler instance.
	 *
	 * @since 1.0.0
	 * @var FileHandler|null
	 */
	private ?FileHandler $file_handler = null;

	/**
	 * Schema validator instance.
	 *
	 * @since 1.0.0
	 * @var SchemaValidator|null
	 */
	private ?SchemaValidator $schema_validator = null;

	/**
	 * ID mapper instance.
	 *
	 * @since 1.0.0
	 * @var IdMapper|null
	 */
	private ?IdMapper $id_mapper = null;

	/**
	 * Lock instance.
	 *
	 * @since 1.0.0
	 * @var Lock|null
	 */
	private ?Lock $lock = null;

	/**
	 * Audit logger instance.
	 *
	 * @since 1.0.0
	 * @var AuditLogger|null
	 */
	private ?AuditLogger $audit_logger = null;

	/**
	 * Environment override instance.
	 *
	 * @since 1.0.0
	 * @var Override\EnvironmentOverride|null
	 */
	private ?Override\EnvironmentOverride $environment_override = null;

	/**
	 * YAML sanitizer instance.
	 *
	 * @since 1.0.0
	 * @var Sanitizer\YamlSanitizer|null
	 */
	private ?Sanitizer\YamlSanitizer $yaml_sanitizer = null;

	/**
	 * Registered providers.
	 *
	 * @since 1.0.0
	 * @var Provider\ProviderInterface[]
	 */
	private array $providers = array();

	/**
	 * Set the config manager service.
	 *
	 * @since 1.0.0
	 *
	 * @param ConfigManager $config_manager Config manager instance.
	 * @return void
	 */
	public function set_config_manager( ConfigManager $config_manager ): void {
		$this->config_manager = $config_manager;
	}

	/**
	 * Get the config manager service.
	 *
	 * @since 1.0.0
	 *
	 * @return ConfigManager
	 *
	 * @throws \RuntimeException If the service has not been registered.
	 */
	public function get_config_manager(): ConfigManager {
		if ( null === $this->config_manager ) {
			throw new \RuntimeException(
				esc_html__( 'ConfigManager service has not been registered.', 'syncforge-config-manager' )
			);
		}

		return $this->config_manager;
	}

	/**
	 * Set the diff engine service.
	 *
	 * @since 1.0.0
	 *
	 * @param DiffEngine $diff_engine Diff engine instance.
	 * @return void
	 */
	public function set_diff_engine( DiffEngine $diff_engine ): void {
		$this->diff_engine = $diff_engine;
	}

	/**
	 * Get the diff engine service.
	 *
	 * @since 1.0.0
	 *
	 * @return DiffEngine
	 *
	 * @throws \RuntimeException If the service has not been registered.
	 */
	public function get_diff_engine(): DiffEngine {
		if ( null === $this->diff_engine ) {
			throw new \RuntimeException(
				esc_html__( 'DiffEngine service has not been registered.', 'syncforge-config-manager' )
			);
		}

		return $this->diff_engine;
	}

	/**
	 * Set the file handler service.
	 *
	 * @since 1.0.0
	 *
	 * @param FileHandler $file_handler File handler instance.
	 * @return void
	 */
	public function set_file_handler( FileHandler $file_handler ): void {
		$this->file_handler = $file_handler;
	}

	/**
	 * Get the file handler service.
	 *
	 * @since 1.0.0
	 *
	 * @return FileHandler
	 *
	 * @throws \RuntimeException If the service has not been registered.
	 */
	public function get_file_handler(): FileHandler {
		if ( null === $this->file_handler ) {
			throw new \RuntimeException(
				esc_html__( 'FileHandler service has not been registered.', 'syncforge-config-manager' )
			);
		}

		return $this->file_handler;
	}

	/**
	 * Set the schema validator service.
	 *
	 * @since 1.0.0
	 *
	 * @param SchemaValidator $schema_validator Schema validator instance.
	 * @return void
	 */
	public function set_schema_validator( SchemaValidator $schema_validator ): void {
		$this->schema_validator = $schema_validator;
	}

	/**
	 * Get the schema validator service.
	 *
	 * @since 1.0.0
	 *
	 * @return SchemaValidator
	 *
	 * @throws \RuntimeException If the service has not been registered.
	 */
	public function get_schema_validator(): SchemaValidator {
		if ( null === $this->schema_validator ) {
			throw new \RuntimeException(
				esc_html__( 'SchemaValidator service has not been registered.', 'syncforge-config-manager' )
			);
		}

		return $this->schema_validator;
	}

	/**
	 * Set the ID mapper service.
	 *
	 * @since 1.0.0
	 *
	 * @param IdMapper $id_mapper ID mapper instance.
	 * @return void
	 */
	public function set_id_mapper( IdMapper $id_mapper ): void {
		$this->id_mapper = $id_mapper;
	}

	/**
	 * Get the ID mapper service.
	 *
	 * @since 1.0.0
	 *
	 * @return IdMapper
	 *
	 * @throws \RuntimeException If the service has not been registered.
	 */
	public function get_id_mapper(): IdMapper {
		if ( null === $this->id_mapper ) {
			throw new \RuntimeException(
				esc_html__( 'IdMapper service has not been registered.', 'syncforge-config-manager' )
			);
		}

		return $this->id_mapper;
	}

	/**
	 * Set the lock service.
	 *
	 * @since 1.0.0
	 *
	 * @param Lock $lock Lock instance.
	 * @return void
	 */
	public function set_lock( Lock $lock ): void {
		$this->lock = $lock;
	}

	/**
	 * Get the lock service.
	 *
	 * @since 1.0.0
	 *
	 * @return Lock
	 *
	 * @throws \RuntimeException If the service has not been registered.
	 */
	public function get_lock(): Lock {
		if ( null === $this->lock ) {
			throw new \RuntimeException(
				esc_html__( 'Lock service has not been registered.', 'syncforge-config-manager' )
			);
		}

		return $this->lock;
	}

	/**
	 * Set the audit logger service.
	 *
	 * @since 1.0.0
	 *
	 * @param AuditLogger $audit_logger Audit logger instance.
	 * @return void
	 */
	public function set_audit_logger( AuditLogger $audit_logger ): void {
		$this->audit_logger = $audit_logger;
	}

	/**
	 * Get the audit logger service.
	 *
	 * @since 1.0.0
	 *
	 * @return AuditLogger
	 *
	 * @throws \RuntimeException If the service has not been registered.
	 */
	public function get_audit_logger(): AuditLogger {
		if ( null === $this->audit_logger ) {
			throw new \RuntimeException(
				esc_html__( 'AuditLogger service has not been registered.', 'syncforge-config-manager' )
			);
		}

		return $this->audit_logger;
	}

	/**
	 * Set the environment override service.
	 *
	 * @since 1.0.0
	 *
	 * @param Override\EnvironmentOverride $environment_override Environment override instance.
	 * @return void
	 */
	public function set_environment_override( Override\EnvironmentOverride $environment_override ): void {
		$this->environment_override = $environment_override;
	}

	/**
	 * Get the environment override service.
	 *
	 * @since 1.0.0
	 *
	 * @return Override\EnvironmentOverride
	 *
	 * @throws \RuntimeException If the service has not been registered.
	 */
	public function get_environment_override(): Override\EnvironmentOverride {
		if ( null === $this->environment_override ) {
			throw new \RuntimeException(
				esc_html__( 'EnvironmentOverride service has not been registered.', 'syncforge-config-manager' )
			);
		}

		return $this->environment_override;
	}

	/**
	 * Set the YAML sanitizer service.
	 *
	 * @since 1.0.0
	 *
	 * @param Sanitizer\YamlSanitizer $yaml_sanitizer YAML sanitizer instance.
	 * @return void
	 */
	public function set_yaml_sanitizer( Sanitizer\YamlSanitizer $yaml_sanitizer ): void {
		$this->yaml_sanitizer = $yaml_sanitizer;
	}

	/**
	 * Get the YAML sanitizer service.
	 *
	 * @since 1.0.0
	 *
	 * @return Sanitizer\YamlSanitizer
	 *
	 * @throws \RuntimeException If the service has not been registered.
	 */
	public function get_yaml_sanitizer(): Sanitizer\YamlSanitizer {
		if ( null === $this->yaml_sanitizer ) {
			throw new \RuntimeException(
				esc_html__( 'YamlSanitizer service has not been registered.', 'syncforge-config-manager' )
			);
		}

		return $this->yaml_sanitizer;
	}

	/**
	 * Register a provider.
	 *
	 * @since 1.0.0
	 *
	 * @param Provider\ProviderInterface $provider Provider instance to register.
	 * @return void
	 */
	public function add_provider( Provider\ProviderInterface $provider ): void {
		$this->providers[ $provider->get_id() ] = $provider;
	}

	/**
	 * Get all registered providers.
	 *
	 * @since 1.0.0
	 *
	 * @return Provider\ProviderInterface[] Associative array of providers keyed by ID.
	 */
	public function get_providers(): array {
		return $this->providers;
	}
}
