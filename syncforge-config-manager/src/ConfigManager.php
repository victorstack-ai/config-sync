<?php
/**
 * Config manager — central orchestrator for export/import operations.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConfigManager
 *
 * Coordinates export, import, diff, and rollback operations across all
 * registered configuration providers via the service container.
 *
 * @since 1.0.0
 */
class ConfigManager {

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
	 * @param Container $container Service container with all registered services and providers.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Export configuration from all registered providers.
	 *
	 * Iterates over every provider, calls its export() method, writes the
	 * resulting data to YAML files via FileHandler, and logs the operation.
	 *
	 * @since 1.0.0
	 *
	 * @return array Export summary with per-provider stats and any errors.
	 */
	public function export_all(): array {
		$providers = $this->container->get_providers();

		/**
		 * Fires before a full configuration export begins.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $provider_ids Array of provider IDs being exported.
		 */
		do_action( 'config_sync_before_export', array_keys( $providers ) );

		$results = array();
		$errors  = array();

		foreach ( $providers as $provider_id => $provider ) {
			try {
				$results[ $provider_id ] = $this->do_export_provider( $provider );
			} catch ( \Throwable $e ) {
				$errors[ $provider_id ] = $e->getMessage();
			}
		}

		$this->container->get_audit_logger()->log_operation(
			'export',
			'all',
			$this->get_environment(),
			array(),
			array()
		);

		/**
		 * Fires after a full configuration export completes.
		 *
		 * @since 1.0.0
		 *
		 * @param array $results Per-provider export results.
		 * @param array $errors  Per-provider errors.
		 */
		do_action( 'config_sync_after_export', $results, $errors );

		return array(
			'providers' => $results,
			'errors'    => $errors,
		);
	}

	/**
	 * Export configuration from a single provider.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider_id The provider identifier to export.
	 * @return array Export summary for the provider.
	 */
	public function export_provider( string $provider_id ): array {
		$provider_id = sanitize_key( $provider_id );
		$providers   = $this->container->get_providers();

		if ( ! isset( $providers[ $provider_id ] ) ) {
			return array(
				'providers' => array(),
				'errors'    => array(
					$provider_id => esc_html__( 'Provider not found.', 'syncforge-config-manager' ),
				),
			);
		}

		/**
		 * Fires before a single-provider configuration export begins.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $provider_ids The provider being exported.
		 */
		do_action( 'config_sync_before_export', array( $provider_id ) );

		$results = array();
		$errors  = array();

		try {
			$results[ $provider_id ] = $this->do_export_provider( $providers[ $provider_id ] );
		} catch ( \Throwable $e ) {
			$errors[ $provider_id ] = $e->getMessage();
		}

		$this->container->get_audit_logger()->log_operation(
			'export',
			$provider_id,
			$this->get_environment(),
			array(),
			array()
		);

		/**
		 * Fires after a single-provider configuration export completes.
		 *
		 * @since 1.0.0
		 *
		 * @param array $results Per-provider export results.
		 * @param array $errors  Per-provider errors.
		 */
		do_action( 'config_sync_after_export', $results, $errors );

		return array(
			'providers' => $results,
			'errors'    => $errors,
		);
	}

	/**
	 * Import configuration for all registered providers.
	 *
	 * Acquires a lock, reads YAML files, validates data, sorts providers
	 * by dependency order, and imports (or previews via dry-run).
	 *
	 * @since 1.0.0
	 *
	 * @param bool $dry_run If true, preview changes without applying them.
	 * @return array|\WP_Error Import summary or WP_Error if lock cannot be acquired.
	 */
	public function import_all( bool $dry_run = false ) {
		$lock = $this->container->get_lock();

		if ( ! $lock->acquire( 'import' ) ) {
			return new \WP_Error(
				'config_sync_locked',
				esc_html__( 'Another import operation is in progress. Please try again later.', 'syncforge-config-manager' )
			);
		}

		try {
			$providers = $this->container->get_providers();

			$sorted = $this->topological_sort( $providers );

			/**
			 * Fires before a full configuration import begins.
			 *
			 * @since 1.0.0
			 *
			 * @param string[] $provider_ids Sorted provider IDs being imported.
			 * @param bool     $dry_run      Whether this is a dry run.
			 */
			do_action( 'config_sync_before_import', array_keys( $sorted ), $dry_run );

			// Create a snapshot of the current state before importing.
			$snapshot = array();
			foreach ( $sorted as $pid => $prov ) {
				try {
					$snapshot[ $pid ] = $prov->export();
				} catch ( \Throwable $e ) {
					$snapshot[ $pid ] = array();
				}
			}

			$this->container->get_audit_logger()->log_operation(
				'import',
				'all',
				$this->get_environment(),
				array(),
				$snapshot
			);

			$results = array();
			$errors  = array();

			foreach ( $sorted as $pid => $prov ) {
				try {
					$results[ $pid ] = $this->do_import_provider( $prov, $dry_run );
				} catch ( \Throwable $e ) {
					$errors[ $pid ] = $e->getMessage();

					/**
					 * Fires when a provider import fails.
					 *
					 * @since 1.0.0
					 *
					 * @param string     $provider_id The provider that failed.
					 * @param \Throwable $exception   The exception that was thrown.
					 */
					do_action( 'config_sync_import_failed', $pid, $e );
				}
			}

			/**
			 * Fires after a full configuration import completes.
			 *
			 * @since 1.0.0
			 *
			 * @param array $results Per-provider import results.
			 * @param array $errors  Per-provider errors.
			 * @param bool  $dry_run Whether this was a dry run.
			 */
			do_action( 'config_sync_after_import', $results, $errors, $dry_run );

			return array(
				'providers' => $results,
				'errors'    => $errors,
				'dry_run'   => $dry_run,
			);
		} finally {
			$lock->release();
		}
	}

	/**
	 * Import configuration for a single provider.
	 *
	 * Acquires a lock, reads the provider's YAML files, validates, and imports.
	 *
	 * @since 1.0.0
	 *
	 * @param string $provider_id The provider identifier to import.
	 * @param bool   $dry_run     If true, preview changes without applying them.
	 * @return array|\WP_Error Import summary or WP_Error if lock cannot be acquired.
	 */
	public function import_provider( string $provider_id, bool $dry_run = false ) {
		$provider_id = sanitize_key( $provider_id );
		$providers   = $this->container->get_providers();

		if ( ! isset( $providers[ $provider_id ] ) ) {
			return array(
				'providers' => array(),
				'errors'    => array(
					$provider_id => esc_html__( 'Provider not found.', 'syncforge-config-manager' ),
				),
				'dry_run'   => $dry_run,
			);
		}

		$lock = $this->container->get_lock();

		if ( ! $lock->acquire( 'import' ) ) {
			return new \WP_Error(
				'config_sync_locked',
				esc_html__( 'Another import operation is in progress. Please try again later.', 'syncforge-config-manager' )
			);
		}

		try {
			$provider = $providers[ $provider_id ];

			/**
			 * Fires before a single-provider configuration import begins.
			 *
			 * @since 1.0.0
			 *
			 * @param string[] $provider_ids The provider being imported.
			 * @param bool     $dry_run      Whether this is a dry run.
			 */
			do_action( 'config_sync_before_import', array( $provider_id ), $dry_run );

			// Snapshot the current state.
			$snapshot = array();
			try {
				$snapshot[ $provider_id ] = $provider->export();
			} catch ( \Throwable $e ) {
				$snapshot[ $provider_id ] = array();
			}

			$this->container->get_audit_logger()->log_operation(
				'import',
				$provider_id,
				$this->get_environment(),
				array(),
				$snapshot
			);

			$results = array();
			$errors  = array();

			try {
				$results[ $provider_id ] = $this->do_import_provider( $provider, $dry_run );
			} catch ( \Throwable $e ) {
				$errors[ $provider_id ] = $e->getMessage();

				/** This action is documented in src/ConfigManager.php */
				do_action( 'config_sync_import_failed', $provider_id, $e );
			}

			/**
			 * Fires after a single-provider configuration import completes.
			 *
			 * @since 1.0.0
			 *
			 * @param array $results Per-provider import results.
			 * @param array $errors  Per-provider errors.
			 * @param bool  $dry_run Whether this was a dry run.
			 */
			do_action( 'config_sync_after_import', $results, $errors, $dry_run );

			return array(
				'providers' => $results,
				'errors'    => $errors,
				'dry_run'   => $dry_run,
			);
		} finally {
			$lock->release();
		}
	}

	/**
	 * Compute the diff between current database state and YAML files on disk.
	 *
	 * For each provider, exports the current state and compares it with the
	 * data stored in YAML configuration files using the DiffEngine.
	 *
	 * @since 1.0.0
	 *
	 * @return array Combined diff array keyed by provider ID.
	 */
	public function diff( ?string $provider_filter = null ): array {
		$providers    = $this->container->get_providers();
		$diff_engine  = $this->container->get_diff_engine();
		$combined     = array();

		if ( null !== $provider_filter ) {
			$provider_filter = sanitize_key( $provider_filter );
			if ( isset( $providers[ $provider_filter ] ) ) {
				$providers = array( $provider_filter => $providers[ $provider_filter ] );
			} else {
				return array();
			}
		}

		foreach ( $providers as $provider_id => $provider ) {
			try {
				$current   = $provider->export();
				$from_disk = $this->read_provider_config( $provider );

				$provider_diff = $diff_engine->compute( $current, $from_disk, $provider_id );

				if ( ! empty( $provider_diff ) ) {
					$combined[ $provider_id ] = $provider_diff;
				}
			} catch ( \Throwable $e ) {
				$combined[ $provider_id ] = array(
					array(
						'type'     => 'error',
						'key'      => $provider_id,
						'old'      => null,
						'new'      => null,
						'provider' => $provider_id,
						'message'  => $e->getMessage(),
					),
				);
			}
		}

		return $combined;
	}

	/**
	 * Rollback to a previous snapshot.
	 *
	 * Retrieves the snapshot from the audit log and imports it directly,
	 * bypassing the YAML file read step.
	 *
	 * @since 1.0.0
	 *
	 * @param int $snapshot_id The audit log row ID containing the snapshot.
	 * @return array|\WP_Error Rollback result summary or WP_Error on failure.
	 */
	public function rollback( int $snapshot_id ) {
		$snapshot_id = absint( $snapshot_id );

		$audit_logger = $this->container->get_audit_logger();
		$snapshot     = $audit_logger->get_snapshot( $snapshot_id );

		if ( null === $snapshot ) {
			return new \WP_Error(
				'config_sync_snapshot_not_found',
				esc_html__( 'Snapshot not found.', 'syncforge-config-manager' )
			);
		}

		$lock = $this->container->get_lock();

		if ( ! $lock->acquire( 'rollback' ) ) {
			return new \WP_Error(
				'config_sync_locked',
				esc_html__( 'Another operation is in progress. Please try again later.', 'syncforge-config-manager' )
			);
		}

		try {
			$providers = $this->container->get_providers();
			$results   = array();
			$errors    = array();

			foreach ( $snapshot as $provider_id => $config ) {
				if ( ! isset( $providers[ $provider_id ] ) ) {
					$errors[ $provider_id ] = esc_html__( 'Provider not found during rollback.', 'syncforge-config-manager' );
					continue;
				}

				try {
					$result = $providers[ $provider_id ]->import( $config );
					$results[ $provider_id ] = $result;
				} catch ( \Throwable $e ) {
					$errors[ $provider_id ] = $e->getMessage();

					/** This action is documented in src/ConfigManager.php */
					do_action( 'config_sync_import_failed', $provider_id, $e );
				}
			}

			$audit_logger->log_operation(
				'rollback',
				'all',
				$this->get_environment(),
				array(),
				$snapshot
			);

			/**
			 * Fires after a rollback operation completes.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $snapshot_id The snapshot ID that was rolled back to.
			 * @param array $results     Per-provider rollback results.
			 * @param array $errors      Per-provider errors.
			 */
			do_action( 'config_sync_rollback', $snapshot_id, $results, $errors );

			return array(
				'snapshot_id' => $snapshot_id,
				'providers'   => $results,
				'errors'      => $errors,
			);
		} finally {
			$lock->release();
		}
	}

	/**
	 * Sort providers by their dependencies using topological sort (Kahn's algorithm).
	 *
	 * Ensures providers are processed in an order that respects dependency
	 * relationships. Throws an exception if circular dependencies are detected.
	 *
	 * @since 1.0.0
	 *
	 * @param Provider\ProviderInterface[] $providers Associative array of providers keyed by ID.
	 * @return Provider\ProviderInterface[] Sorted providers in dependency order.
	 *
	 * @throws \RuntimeException If circular dependencies are detected.
	 */
	private function topological_sort( array $providers ): array {
		$in_degree = array();
		$adjacency = array();
		$ids       = array_keys( $providers );

		// Initialise in-degree and adjacency list.
		foreach ( $ids as $id ) {
			$in_degree[ $id ] = 0;
			$adjacency[ $id ] = array();
		}

		// Build the graph: an edge from dependency -> dependent.
		foreach ( $providers as $id => $provider ) {
			foreach ( $provider->get_dependencies() as $dep ) {
				if ( ! isset( $providers[ $dep ] ) ) {
					// Dependency not registered — skip silently.
					continue;
				}
				$adjacency[ $dep ][] = $id;
				++$in_degree[ $id ];
			}
		}

		// Seed the queue with nodes that have no dependencies.
		$queue = array();
		foreach ( $in_degree as $id => $degree ) {
			if ( 0 === $degree ) {
				$queue[] = $id;
			}
		}

		$sorted = array();

		while ( ! empty( $queue ) ) {
			$current = array_shift( $queue );
			$sorted[ $current ] = $providers[ $current ];

			foreach ( $adjacency[ $current ] as $neighbor ) {
				--$in_degree[ $neighbor ];
				if ( 0 === $in_degree[ $neighbor ] ) {
					$queue[] = $neighbor;
				}
			}
		}

		if ( count( $sorted ) !== count( $providers ) ) {
			throw new \RuntimeException(
				esc_html__( 'Circular dependency detected among providers.', 'syncforge-config-manager' )
			);
		}

		return $sorted;
	}

	/**
	 * Perform the export for a single provider and write files.
	 *
	 * @since 1.0.0
	 *
	 * @param Provider\ProviderInterface $provider The provider to export.
	 * @return array Export statistics with file_count and item_count.
	 */
	private function do_export_provider( Provider\ProviderInterface $provider ): array {
		$file_handler = $this->container->get_file_handler();
		$data         = $provider->export();
		$config_files = $provider->get_config_files();
		$file_count   = 0;

		if ( count( $config_files ) === 1 && $this->is_directory_path( $config_files[0] ) ) {
			// Directory-based provider: write one file per top-level key.
			$dir_prefix = $config_files[0];
			foreach ( $data as $key => $value ) {
				$file_path  = $dir_prefix . sanitize_key( $key ) . '.yml';
				$file_data  = is_array( $value ) ? $value : array( 'value' => $value );
				$file_handler->write( $file_path, $file_data );
				++$file_count;
			}
		} elseif ( count( $config_files ) === 1 ) {
			// Single-file provider: write all data to one file.
			$file_handler->write( $config_files[0], $data );
			$file_count = 1;
		} else {
			// Multiple named files: write each group to its corresponding file.
			foreach ( $config_files as $file_path ) {
				$group_key  = $this->extract_group_key( $file_path );
				$group_data = isset( $data[ $group_key ] ) ? $data[ $group_key ] : array();
				$file_handler->write( $file_path, $group_data );
				++$file_count;
			}

			// Write any remaining keys not covered by named files (e.g., "extra").
			$named_keys = array_map( array( $this, 'extract_group_key' ), $config_files );
			foreach ( $data as $key => $value ) {
				if ( ! in_array( $key, $named_keys, true ) && ! empty( $value ) ) {
					$dir_prefix = dirname( $config_files[0] ) . '/';
					$file_handler->write( $dir_prefix . sanitize_key( $key ) . '.yml', $value );
					++$file_count;
				}
			}
		}

		return array(
			'files' => $file_count,
			'items' => $this->count_items( $data ),
		);
	}

	/**
	 * Perform the import for a single provider from YAML files.
	 *
	 * Reads configuration from disk, validates it via SchemaValidator,
	 * then calls the provider's import() or dry_run() method.
	 *
	 * @since 1.0.0
	 *
	 * @param Provider\ProviderInterface $provider The provider to import.
	 * @param bool                       $dry_run  Whether to preview changes only.
	 * @return array Import or dry-run result from the provider.
	 */
	private function do_import_provider( Provider\ProviderInterface $provider, bool $dry_run ): array {
		$config = $this->read_provider_config( $provider );

		// Apply environment overrides (e.g. staging vs production).
		$config = $this->container->get_environment_override()->apply_overrides( $provider->get_id(), $config );

		// Validate the configuration data.
		$validation = $this->container->get_schema_validator()->validate( $provider->get_id(), $config );

		if ( is_wp_error( $validation ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: 1: provider ID, 2: validation error message */
					esc_html__( 'Validation failed for provider %1$s: %2$s', 'syncforge-config-manager' ),
					esc_html( $provider->get_id() ),
					esc_html( $validation->get_error_message() )
				)
			);
		}

		// Sanitize before writing to the database.
		$config = $this->container->get_yaml_sanitizer()->sanitize( $config, $provider->get_id() );

		if ( $dry_run ) {
			return $provider->dry_run( $config );
		}

		return $provider->import( $config );
	}

	/**
	 * Read configuration data from YAML files for a given provider.
	 *
	 * If the provider has multiple config files, the data is assembled into
	 * a grouped array keyed by the group name extracted from the file path.
	 *
	 * @since 1.0.0
	 *
	 * @param Provider\ProviderInterface $provider The provider whose files to read.
	 * @return array Assembled configuration data.
	 */
	private function read_provider_config( Provider\ProviderInterface $provider ): array {
		$file_handler = $this->container->get_file_handler();
		$config_files = $provider->get_config_files();

		if ( count( $config_files ) === 1 && $this->is_directory_path( $config_files[0] ) ) {
			// Directory-based provider: read all .yml files from the directory.
			$dir_path = rtrim( $config_files[0], '/' );

			try {
				$yml_files = $file_handler->list_files( $dir_path );
			} catch ( \InvalidArgumentException $e ) {
				// Directory does not exist yet (no files exported).
				return array();
			}

			$config = array();

			foreach ( $yml_files as $file_path ) {
				$key  = $this->extract_group_key( $file_path );
				$data = $file_handler->read( $file_path );

				if ( ! empty( $data ) ) {
					$config[ $key ] = $data;
				}
			}

			return $config;
		}

		if ( count( $config_files ) === 1 ) {
			return $file_handler->read( $config_files[0] );
		}

		$config = array();

		foreach ( $config_files as $file_path ) {
			$group_key  = $this->extract_group_key( $file_path );
			$group_data = $file_handler->read( $file_path );

			if ( ! empty( $group_data ) ) {
				$config[ $group_key ] = $group_data;
			}
		}

		// Also read any extra files not in the predefined list (e.g., options/extra.yml).
		$dir_path = dirname( $config_files[0] );

		try {
			$yml_files = $file_handler->list_files( $dir_path );
		} catch ( \InvalidArgumentException $e ) {
			return $config;
		}

		$named_files = array_map( 'basename', $config_files );
		foreach ( $yml_files as $file_path ) {
			$basename = basename( $file_path );
			if ( ! in_array( $basename, $named_files, true ) ) {
				$key  = $this->extract_group_key( $file_path );
				$data = $file_handler->read( $file_path );

				if ( ! empty( $data ) ) {
					$config[ $key ] = $data;
				}
			}
		}

		return $config;
	}

	/**
	 * Extract a group key from a YAML file path.
	 *
	 * Converts 'options/general.yml' to 'general'.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path Relative YAML file path.
	 * @return string The group key.
	 */
	private function extract_group_key( string $file_path ): string {
		$basename = basename( $file_path, '.yml' );

		return sanitize_key( $basename );
	}

	/**
	 * Count the total number of leaf items in a configuration array.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Configuration data.
	 * @return int Total item count.
	 */
	private function count_items( array $data ): int {
		$count = 0;

		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$count += count( $value );
			} else {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get the current environment name.
	 *
	 * @since 1.0.0
	 *
	 * @return string The environment name.
	 */
	private function get_environment(): string {
		if ( function_exists( 'wp_get_environment_type' ) ) {
			return wp_get_environment_type();
		}

		return 'production';
	}

	/**
	 * Check if a config file path represents a directory (ends with /).
	 *
	 * @since 1.0.0
	 *
	 * @param string $path The file path to check.
	 * @return bool True if the path ends with a slash.
	 */
	private function is_directory_path( string $path ): bool {
		return '/' === substr( $path, -1 );
	}
}
