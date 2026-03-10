<?php
/**
 * ZIP archive handler for config export/import.
 *
 * Provides AJAX endpoints for downloading all YAML files as a ZIP
 * and uploading a ZIP to replace YAML files before import.
 *
 * @package ConfigSync\Admin
 * @since   1.1.0
 */

namespace ConfigSync\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ZipHandler
 *
 * Handles ZIP archive creation for export and extraction for import
 * via WordPress AJAX hooks.
 *
 * @since 1.1.0
 */
class ZipHandler {

	/**
	 * Absolute path to the configuration directory.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	private string $config_dir;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 *
	 * @param string $config_dir Absolute path to the config directory.
	 */
	public function __construct( string $config_dir ) {
		$this->config_dir = trailingslashit( $config_dir );
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_config_sync_zip_export', array( $this, 'handle_zip_export' ) );
		add_action( 'wp_ajax_config_sync_zip_import', array( $this, 'handle_zip_import' ) );
	}

	/**
	 * Handle ZIP export AJAX request.
	 *
	 * Creates a ZIP archive of all YAML files in the config directory
	 * and streams it as a download.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function handle_zip_export(): void {
		check_ajax_referer( 'config_sync_zip', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'syncforge-config-manager' ), 403 );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( esc_html__( 'ZipArchive extension is not available on this server.', 'syncforge-config-manager' ), 500 );
		}

		$real_config_dir = realpath( $this->config_dir );
		if ( false === $real_config_dir || ! is_dir( $real_config_dir ) ) {
			wp_die( esc_html__( 'Configuration directory not found. Export first.', 'syncforge-config-manager' ), 404 );
		}

		$tmp_file = wp_tempnam( 'syncforge-export' );
		if ( ! $tmp_file ) {
			wp_die( esc_html__( 'Could not create temporary file.', 'syncforge-config-manager' ), 500 );
		}

		$zip = new \ZipArchive();
		$result = $zip->open( $tmp_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );

		if ( true !== $result ) {
			wp_delete_file( $tmp_file );
			wp_die( esc_html__( 'Could not create ZIP archive.', 'syncforge-config-manager' ), 500 );
		}

		$this->add_directory_to_zip( $zip, $real_config_dir, '' );
		$zip->close();

		$filesize = filesize( $tmp_file );

		// Stream the file as a download.
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="syncforge-' . gmdate( 'Y-m-d-His' ) . '.zip"' );
		header( 'Content-Length: ' . $filesize );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $tmp_file );
		wp_delete_file( $tmp_file );
		exit;
	}

	/**
	 * Handle ZIP import AJAX request.
	 *
	 * Accepts a ZIP file upload, validates it, and extracts YAML files
	 * into the configuration directory.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function handle_zip_import(): void {
		check_ajax_referer( 'config_sync_zip', '_wpnonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'syncforge-config-manager' ), 403 );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_send_json_error( __( 'ZipArchive extension is not available on this server.', 'syncforge-config-manager' ) );
		}

		if ( empty( $_FILES['config_zip'] ) ) {
			wp_send_json_error( __( 'No file uploaded.', 'syncforge-config-manager' ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- File array values are validated below.
		$file = $_FILES['config_zip'];

		// Validate upload.
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			wp_send_json_error( __( 'File upload error.', 'syncforge-config-manager' ) );
		}

		$finfo = new \finfo( FILEINFO_MIME_TYPE );
		$mime  = $finfo->file( $file['tmp_name'] );

		if ( 'application/zip' !== $mime && 'application/x-zip-compressed' !== $mime ) {
			wp_send_json_error( __( 'Invalid file type. Please upload a ZIP file.', 'syncforge-config-manager' ) );
		}

		// Enforce a reasonable size limit (50MB).
		$max_size = 50 * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			wp_send_json_error( __( 'File exceeds the maximum allowed size of 50MB.', 'syncforge-config-manager' ) );
		}

		$zip = new \ZipArchive();
		$result = $zip->open( $file['tmp_name'] );

		if ( true !== $result ) {
			wp_send_json_error( __( 'Could not open ZIP archive.', 'syncforge-config-manager' ) );
		}

		// Validate all entries before extracting.
		$valid_entries = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$entry = $zip->getNameIndex( $i );

			// Skip directories and non-YAML files.
			if ( '/' === substr( $entry, -1 ) ) {
				continue;
			}

			// Only allow .yml files.
			if ( '.yml' !== substr( $entry, -4 ) ) {
				continue;
			}

			// Reject path traversal.
			if ( false !== strpos( $entry, '..' ) ) {
				$zip->close();
				wp_send_json_error( __( 'ZIP contains invalid path traversal entries.', 'syncforge-config-manager' ) );
			}

			$valid_entries[] = $entry;
		}

		if ( empty( $valid_entries ) ) {
			$zip->close();
			wp_send_json_error( __( 'ZIP archive contains no YAML files.', 'syncforge-config-manager' ) );
		}

		// Ensure config directory exists.
		$real_config_dir = $this->config_dir;
		if ( ! is_dir( $real_config_dir ) ) {
			wp_mkdir_p( $real_config_dir );
		}

		// Extract valid YAML files.
		$extracted = 0;
		foreach ( $valid_entries as $entry ) {
			$content = $zip->getFromName( $entry );
			if ( false === $content ) {
				continue;
			}

			$target_path = $real_config_dir . $entry;
			$target_dir  = dirname( $target_path );

			if ( ! is_dir( $target_dir ) ) {
				wp_mkdir_p( $target_dir );
			}

			// Validate real path of parent doesn't escape config dir.
			$real_target_dir = realpath( $target_dir );
			$real_base       = realpath( $this->config_dir );

			if ( false === $real_target_dir || false === $real_base || 0 !== strpos( $real_target_dir, $real_base ) ) {
				continue;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $real_target_dir . '/' . basename( $entry ), $content );
			++$extracted;
		}

		$zip->close();

		wp_send_json_success(
			sprintf(
				/* translators: %d: number of files extracted */
				__( 'Extracted %d YAML file(s) into the configuration directory.', 'syncforge-config-manager' ),
				$extracted
			)
		);
	}

	/**
	 * Recursively add a directory's YAML files to a ZipArchive.
	 *
	 * Skips non-YAML files, .htaccess, index.php, and web.config.
	 *
	 * @since 1.1.0
	 *
	 * @param \ZipArchive $zip        The archive instance.
	 * @param string      $dir        Absolute directory path.
	 * @param string      $zip_prefix Prefix for file paths inside the ZIP.
	 * @return void
	 */
	private function add_directory_to_zip( \ZipArchive $zip, string $dir, string $zip_prefix ): void {
		$items = scandir( $dir );
		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$full_path = $dir . '/' . $item;

			if ( is_dir( $full_path ) ) {
				$sub_prefix = '' === $zip_prefix ? $item . '/' : $zip_prefix . $item . '/';
				$this->add_directory_to_zip( $zip, $full_path, $sub_prefix );
				continue;
			}

			// Only include .yml files.
			if ( '.yml' !== substr( $item, -4 ) ) {
				continue;
			}

			$zip_path = '' === $zip_prefix ? $item : $zip_prefix . $item;
			$zip->addFile( $full_path, $zip_path );
		}
	}
}
