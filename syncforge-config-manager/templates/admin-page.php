<?php
/**
 * Admin page template.
 *
 * Renders the Config Sync management page with export, import, diff,
 * and ZIP transfer controls using native WordPress admin UI.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap syncforge-admin">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div id="syncforge-notices"></div>

	<div class="syncforge-actions">
		<h2><?php esc_html_e( 'Configuration Actions', 'syncforge-config-manager' ); ?></h2>

		<div class="syncforge-cards">
			<div class="syncforge-card">
				<h3><?php esc_html_e( 'Export', 'syncforge-config-manager' ); ?></h3>
				<p><?php esc_html_e( 'Export current database configuration to YAML files on disk.', 'syncforge-config-manager' ); ?></p>
				<button type="button" class="button button-primary" id="syncforge-export">
					<?php esc_html_e( 'Export to YAML', 'syncforge-config-manager' ); ?>
				</button>
			</div>

			<div class="syncforge-card">
				<h3><?php esc_html_e( 'Import', 'syncforge-config-manager' ); ?></h3>
				<p><?php esc_html_e( 'Import configuration from YAML files on disk into the database.', 'syncforge-config-manager' ); ?></p>
				<button type="button" class="button" id="syncforge-preview">
					<?php esc_html_e( 'Preview Changes', 'syncforge-config-manager' ); ?>
				</button>
				<button type="button" class="button button-primary" id="syncforge-import" style="display:none;">
					<?php esc_html_e( 'Apply Import', 'syncforge-config-manager' ); ?>
				</button>
			</div>

			<div class="syncforge-card">
				<h3><?php esc_html_e( 'Download ZIP', 'syncforge-config-manager' ); ?></h3>
				<p><?php esc_html_e( 'Download all YAML configuration files as a ZIP archive.', 'syncforge-config-manager' ); ?></p>
				<button type="button" class="button button-primary" id="syncforge-zip-export">
					<?php esc_html_e( 'Download ZIP', 'syncforge-config-manager' ); ?>
				</button>
			</div>

			<div class="syncforge-card">
				<h3><?php esc_html_e( 'Upload ZIP', 'syncforge-config-manager' ); ?></h3>
				<p><?php esc_html_e( 'Upload a ZIP archive to replace YAML configuration files, then import.', 'syncforge-config-manager' ); ?></p>
				<form id="syncforge-zip-import-form" enctype="multipart/form-data">
					<input type="file" name="config_zip" id="syncforge-zip-file" accept=".zip" />
					<button type="submit" class="button button-primary" id="syncforge-zip-import" disabled>
						<?php esc_html_e( 'Upload & Preview', 'syncforge-config-manager' ); ?>
					</button>
				</form>
			</div>
		</div>
	</div>

	<div class="syncforge-diff-section" id="syncforge-diff-section" style="display:none;">
		<h2><?php esc_html_e( 'Configuration Diff', 'syncforge-config-manager' ); ?></h2>
		<div id="syncforge-diff-content"></div>
	</div>

	<div class="syncforge-results" id="syncforge-results" style="display:none;">
		<h2><?php esc_html_e( 'Operation Results', 'syncforge-config-manager' ); ?></h2>
		<div id="syncforge-results-content"></div>
	</div>

	<div class="syncforge-status">
		<h2><?php esc_html_e( 'Registered Providers', 'syncforge-config-manager' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Provider', 'syncforge-config-manager' ); ?></th>
					<th><?php esc_html_e( 'Config Files', 'syncforge-config-manager' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$config_sync_providers = config_sync()->get_providers();
				foreach ( $config_sync_providers as $id => $config_sync_provider ) :
					$config_sync_files = $config_sync_provider->get_config_files();
					?>
					<tr>
						<td><strong><?php echo esc_html( $id ); ?></strong></td>
						<td><code><?php echo esc_html( implode( ', ', $config_sync_files ) ); ?></code></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
