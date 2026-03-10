<?php
/**
 * File handler for reading/writing YAML configuration files.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Class FileHandler
 *
 * Handles all file I/O for configuration YAML files using WP_Filesystem.
 * Includes path traversal protection and secure directory creation.
 *
 * @since 1.0.0
 */
class FileHandler {

	/**
	 * Path to the configuration directory.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $config_dir;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $config_dir Absolute path to the configuration directory.
	 */
	public function __construct( string $config_dir ) {
		$this->config_dir = rtrim( $config_dir, '/\\' ) . '/';
	}

	/**
	 * Read and parse a YAML configuration file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $relative_path Relative path to the YAML file within config dir.
	 * @return array Parsed configuration data, or empty array if file is missing.
	 */
	public function read( string $relative_path ): array {
		$filesystem = $this->get_filesystem();

		try {
			$safe_path = $this->resolve_safe_path( $relative_path );
		} catch ( \InvalidArgumentException $e ) {
			// Re-throw traversal errors, but return empty for missing files.
			if ( false !== strpos( $e->getMessage(), 'traversal' ) ) {
				throw $e;
			}
			return array();
		}

		if ( ! $filesystem->exists( $safe_path ) ) {
			return array();
		}

		$contents = $filesystem->get_contents( $safe_path );

		if ( false === $contents || '' === trim( $contents ) ) {
			return array();
		}

		try {
			$data = Yaml::parse( $contents, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE );
		} catch ( ParseException $e ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: 1: file path, 2: error message */
					esc_html__( 'Failed to parse YAML file %1$s: %2$s', 'syncforge-config-manager' ),
					esc_html( $relative_path ),
					esc_html( $e->getMessage() )
				)
			);
		}

		if ( ! is_array( $data ) ) {
			return array();
		}

		return $data;
	}

	/**
	 * Write configuration data to a YAML file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $relative_path Relative path to the YAML file within config dir.
	 * @param array  $data          Configuration data to write.
	 * @return bool True on success, false on failure.
	 */
	public function write( string $relative_path, array $data ): bool {
		$filesystem = $this->get_filesystem();
		$safe_path  = $this->resolve_safe_path_for_write( $relative_path );

		$parent_dir = dirname( $safe_path );
		if ( ! $filesystem->is_dir( $parent_dir ) ) {
			wp_mkdir_p( $parent_dir );
		}

		$yaml_content = Yaml::dump( $data, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK );

		return $filesystem->put_contents( $safe_path, $yaml_content, FS_CHMOD_FILE );
	}

	/**
	 * Check if a configuration file exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $relative_path Relative path to the YAML file within config dir.
	 * @return bool True if the file exists, false otherwise.
	 */
	public function exists( string $relative_path ): bool {
		$filesystem = $this->get_filesystem();

		try {
			$safe_path = $this->resolve_safe_path( $relative_path );
		} catch ( \InvalidArgumentException $e ) {
			if ( false !== strpos( $e->getMessage(), 'traversal' ) ) {
				throw $e;
			}
			return false;
		}

		return $filesystem->exists( $safe_path );
	}

	/**
	 * Delete a configuration file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $relative_path Relative path to the YAML file within config dir.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $relative_path ): bool {
		$filesystem = $this->get_filesystem();
		$safe_path  = $this->resolve_safe_path( $relative_path );

		if ( ! $filesystem->exists( $safe_path ) ) {
			return false;
		}

		return $filesystem->delete( $safe_path );
	}

	/**
	 * List all .yml files in a subdirectory of the config directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $subdirectory Relative subdirectory path within config dir.
	 * @return array List of relative file paths ending in .yml.
	 */
	public function list_files( string $subdirectory = '' ): array {
		$filesystem = $this->get_filesystem();

		if ( '' !== $subdirectory ) {
			$dir_path = $this->resolve_safe_path( $subdirectory );
		} else {
			$dir_path = realpath( $this->config_dir );
			if ( false === $dir_path ) {
				return array();
			}
		}

		if ( ! $filesystem->is_dir( $dir_path ) ) {
			return array();
		}

		$file_list = $filesystem->dirlist( $dir_path, false, true );

		if ( ! is_array( $file_list ) ) {
			return array();
		}

		$yml_files = array();
		$this->collect_yml_files( $file_list, $dir_path, $yml_files );

		$config_dir_real = realpath( $this->config_dir );
		$result          = array();

		foreach ( $yml_files as $absolute_path ) {
			$relative = ltrim( str_replace( $config_dir_real, '', $absolute_path ), '/\\' );
			$result[] = $relative;
		}

		sort( $result );

		return $result;
	}

	/**
	 * Create a secure configuration directory with protective files.
	 *
	 * Creates .htaccess (Apache 2.2 + 2.4), index.php, web.config (IIS),
	 * and .gitignore (secrets.yml).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function create_secure_directory(): void {
		$filesystem = $this->get_filesystem();

		if ( ! $filesystem->is_dir( $this->config_dir ) ) {
			wp_mkdir_p( $this->config_dir );
		}

		// .htaccess — deny access for both Apache 2.2 and 2.4.
		$htaccess_content = "# Apache 2.4\n";
		$htaccess_content .= "<IfModule mod_authz_core.c>\n";
		$htaccess_content .= "    Require all denied\n";
		$htaccess_content .= "</IfModule>\n\n";
		$htaccess_content .= "# Apache 2.2\n";
		$htaccess_content .= "<IfModule !mod_authz_core.c>\n";
		$htaccess_content .= "    Order deny,allow\n";
		$htaccess_content .= "    Deny from all\n";
		$htaccess_content .= "</IfModule>\n";

		$filesystem->put_contents(
			$this->config_dir . '.htaccess',
			$htaccess_content,
			FS_CHMOD_FILE
		);

		// index.php — silence is golden.
		$index_content = "<?php\n// Silence is golden.\n";

		$filesystem->put_contents(
			$this->config_dir . 'index.php',
			$index_content,
			FS_CHMOD_FILE
		);

		// web.config — IIS deny.
		$webconfig_content  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$webconfig_content .= "<configuration>\n";
		$webconfig_content .= "    <system.webServer>\n";
		$webconfig_content .= "        <authorization>\n";
		$webconfig_content .= "            <deny users=\"*\" />\n";
		$webconfig_content .= "        </authorization>\n";
		$webconfig_content .= "    </system.webServer>\n";
		$webconfig_content .= "</configuration>\n";

		$filesystem->put_contents(
			$this->config_dir . 'web.config',
			$webconfig_content,
			FS_CHMOD_FILE
		);

		// .gitignore — ignore secrets.yml.
		$gitignore_content = "secrets.yml\n";

		$filesystem->put_contents(
			$this->config_dir . '.gitignore',
			$gitignore_content,
			FS_CHMOD_FILE
		);
	}

	/**
	 * Get the initialized WP_Filesystem instance.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Filesystem_Base The filesystem instance.
	 *
	 * @throws \RuntimeException If the filesystem cannot be initialized.
	 */
	private function get_filesystem(): \WP_Filesystem_Base {
		global $wp_filesystem;

		if ( $wp_filesystem instanceof \WP_Filesystem_Base ) {
			return $wp_filesystem;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_Filesystem( false, false, true );

			if ( ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
				$wp_filesystem = new \WP_Filesystem_Direct( null );
			}
		} else {
			\WP_Filesystem();
		}

		if ( ! $wp_filesystem instanceof \WP_Filesystem_Base ) {
			throw new \RuntimeException(
				esc_html__( 'Could not initialize the WordPress filesystem.', 'syncforge-config-manager' )
			);
		}

		return $wp_filesystem;
	}

	/**
	 * Resolve a relative path to a safe absolute path within the config directory.
	 *
	 * Validates against path traversal attacks.
	 *
	 * @since 1.0.0
	 *
	 * @param string $relative_path Relative path to resolve.
	 * @return string Resolved absolute path.
	 *
	 * @throws \InvalidArgumentException If the path contains traversal patterns or escapes config dir.
	 */
	private function resolve_safe_path( string $relative_path ): string {
		$this->reject_traversal_patterns( $relative_path );

		$absolute_path = realpath( $this->config_dir . $relative_path );

		if ( false === $absolute_path ) {
			throw new \InvalidArgumentException(
				sprintf(
					/* translators: %s: file path */
					esc_html__( 'Path does not exist: %s', 'syncforge-config-manager' ),
					esc_html( $relative_path )
				)
			);
		}

		$config_dir_real = realpath( $this->config_dir );

		if ( false === $config_dir_real || 0 !== strpos( $absolute_path, $config_dir_real ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'Path traversal detected: path resolves outside configuration directory.', 'syncforge-config-manager' )
			);
		}

		return $absolute_path;
	}

	/**
	 * Resolve a relative path for writing (file may not exist yet).
	 *
	 * Validates the parent directory against path traversal attacks.
	 *
	 * @since 1.0.0
	 *
	 * @param string $relative_path Relative path to resolve.
	 * @return string Resolved absolute path for writing.
	 *
	 * @throws \InvalidArgumentException If the path contains traversal patterns or escapes config dir.
	 */
	private function resolve_safe_path_for_write( string $relative_path ): string {
		$this->reject_traversal_patterns( $relative_path );

		$config_dir_real = realpath( $this->config_dir );

		if ( false === $config_dir_real ) {
			throw new \InvalidArgumentException(
				esc_html__( 'Configuration directory does not exist.', 'syncforge-config-manager' )
			);
		}

		$parent_relative = dirname( $relative_path );
		$parent_absolute = realpath( $this->config_dir . $parent_relative );

		if ( false !== $parent_absolute && 0 !== strpos( $parent_absolute, $config_dir_real ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'Path traversal detected: path resolves outside configuration directory.', 'syncforge-config-manager' )
			);
		}

		return $config_dir_real . '/' . ltrim( $relative_path, '/\\' );
	}

	/**
	 * Reject known path traversal patterns in a relative path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $relative_path The relative path to check.
	 *
	 * @throws \InvalidArgumentException If traversal patterns are detected.
	 */
	private function reject_traversal_patterns( string $relative_path ): void {
		// Reject ../ and ..\ patterns, including URL-encoded variants.
		$decoded = rawurldecode( $relative_path );

		if ( preg_match( '/\.\.[\\/\\\\]/', $decoded ) || preg_match( '/\.\.[\\/\\\\]/', $relative_path ) ) {
			throw new \InvalidArgumentException(
				esc_html__( 'Path traversal detected: relative path contains forbidden patterns.', 'syncforge-config-manager' )
			);
		}

		// Also reject a bare ".." at the end.
		if ( '..' === $decoded || '..' === $relative_path ) {
			throw new \InvalidArgumentException(
				esc_html__( 'Path traversal detected: relative path contains forbidden patterns.', 'syncforge-config-manager' )
			);
		}
	}

	/**
	 * Recursively collect .yml file paths from a dirlist result.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $file_list  The dirlist result array.
	 * @param string $base_path  The base directory path.
	 * @param array  $yml_files  Reference to the array collecting results.
	 */
	private function collect_yml_files( array $file_list, string $base_path, array &$yml_files ): void {
		foreach ( $file_list as $name => $info ) {
			$full_path = rtrim( $base_path, '/' ) . '/' . $name;

			if ( 'd' === $info['type'] && ! empty( $info['files'] ) && is_array( $info['files'] ) ) {
				$this->collect_yml_files( $info['files'], $full_path, $yml_files );
			} elseif ( 'f' === $info['type'] && '.yml' === substr( $name, -4 ) ) {
				$yml_files[] = $full_path;
			}
		}
	}
}
