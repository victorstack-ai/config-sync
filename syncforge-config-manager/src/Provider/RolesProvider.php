<?php
/**
 * Roles configuration provider.
 *
 * Exports and imports WordPress user roles and their capabilities.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RolesProvider
 *
 * Syncs WordPress roles and capabilities between environments.
 *
 * @since 1.0.0
 */
class RolesProvider extends AbstractProvider {

	/**
	 * Get the unique identifier for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider ID.
	 */
	public function get_id(): string {
		return 'roles';
	}

	/**
	 * Get the human-readable label for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string Translated label.
	 */
	public function get_label(): string {
		return __( 'Roles', 'syncforge-config-manager' );
	}

	/**
	 * Get the IDs of providers this provider depends on.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Empty array; roles have no dependencies.
	 */
	public function get_dependencies(): array {
		return array();
	}

	/**
	 * Get the number of items to process per batch iteration.
	 *
	 * @since 1.0.0
	 *
	 * @return int Items per batch.
	 */
	public function get_batch_size(): int {
		return 50;
	}

	/**
	 * Export all roles and their capabilities from the database.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{name: string, capabilities: array<string, bool>}> Roles keyed by slug.
	 */
	public function export(): array {
		$wp_roles = wp_roles();
		$exported = array();

		foreach ( $wp_roles->roles as $slug => $role_data ) {
			$capabilities = isset( $role_data['capabilities'] ) ? $role_data['capabilities'] : array();
			ksort( $capabilities );

			$exported[ $slug ] = array(
				'name'         => $role_data['name'],
				'capabilities' => $capabilities,
			);
		}

		return $exported;
	}

	/**
	 * Import roles and capabilities from configuration.
	 *
	 * Creates new roles, updates capabilities on existing roles, and removes
	 * roles that are not present in the configuration. The administrator role
	 * is never deleted as a safety measure.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Roles configuration keyed by role slug.
	 * @return array{created: int, updated: int, deleted: int, details: string[]} Import results.
	 */
	public function import( array $config ): array {
		$created = 0;
		$updated = 0;
		$deleted = 0;
		$details = array();

		$wp_roles      = wp_roles();
		$existing_slugs = array_keys( $wp_roles->roles );

		// Create or update roles from config.
		foreach ( $config as $slug => $role_data ) {
			$slug = sanitize_key( $slug );
			$name = sanitize_text_field( $role_data['name'] );
			$capabilities = isset( $role_data['capabilities'] ) ? $role_data['capabilities'] : array();

			if ( in_array( $slug, $existing_slugs, true ) ) {
				// Update existing role capabilities.
				$role    = get_role( $slug );
				$changed = false;

				if ( null === $role ) {
					continue;
				}

				$current_caps = $role->capabilities;

				// Add or update capabilities.
				foreach ( $capabilities as $cap => $granted ) {
					$cap     = sanitize_key( $cap );
					$granted = (bool) $granted;

					if ( ! isset( $current_caps[ $cap ] ) || (bool) $current_caps[ $cap ] !== $granted ) {
						$role->add_cap( $cap, $granted );
						$changed = true;
					}
				}

				// Remove capabilities not in config.
				foreach ( $current_caps as $cap => $granted ) {
					if ( ! isset( $capabilities[ $cap ] ) ) {
						$role->remove_cap( $cap );
						$changed = true;
					}
				}

				if ( $changed ) {
					++$updated;
					$details[] = sprintf(
						/* translators: %s: role slug */
						__( 'Updated role: %s', 'syncforge-config-manager' ),
						$slug
					);
				}
			} else {
				// Create new role.
				add_role( $slug, $name, $capabilities );
				++$created;
				$details[] = sprintf(
					/* translators: %s: role slug */
					__( 'Created role: %s', 'syncforge-config-manager' ),
					$slug
				);
			}
		}

		// Remove roles not in config (never delete administrator).
		$config_slugs = array_keys( $config );

		foreach ( $existing_slugs as $slug ) {
			if ( ! in_array( $slug, $config_slugs, true ) ) {
				if ( 'administrator' === $slug ) {
					$details[] = __( 'Skipped deletion of administrator role for safety.', 'syncforge-config-manager' );
					continue;
				}

				remove_role( $slug );
				++$deleted;
				$details[] = sprintf(
					/* translators: %s: role slug */
					__( 'Deleted role: %s', 'syncforge-config-manager' ),
					$slug
				);
			}
		}

		return array(
			'created' => $created,
			'updated' => $updated,
			'deleted' => $deleted,
			'details' => $details,
		);
	}

	/**
	 * Get the list of configuration files for this provider.
	 *
	 * Returns one YAML file per role, dynamically based on current roles.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Relative file paths.
	 */
	public function get_config_files(): array {
		$wp_roles = wp_roles();
		$files    = array();

		foreach ( array_keys( $wp_roles->roles ) as $slug ) {
			$files[] = 'roles/' . $slug . '.yml';
		}

		return $files;
	}
}
