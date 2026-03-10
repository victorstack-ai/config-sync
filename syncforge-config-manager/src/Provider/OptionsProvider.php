<?php
/**
 * Options provider for Config Sync.
 *
 * Exports and imports WordPress core options grouped by category.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Provider;

use ConfigSync\Admin\OptionDiscovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptionsProvider
 *
 * Manages export and import of curated WordPress options, organised
 * into logical groups (general, reading, writing, discussion, media,
 * permalinks).  Transients, internal bookkeeping options, and
 * plugin-specific keys are excluded by default.
 *
 * @since 1.0.0
 */
class OptionsProvider extends AbstractProvider {

	/**
	 * Curated map of option groups and their option names.
	 *
	 * @since 1.0.0
	 * @var array<string, string[]>
	 */
	private array $option_groups = array(
		'general'    => array(
			'blogname',
			'blogdescription',
			'siteurl',
			'home',
			'admin_email',
			'timezone_string',
			'gmt_offset',
			'date_format',
			'time_format',
			'start_of_week',
			'WPLANG',
		),
		'reading'    => array(
			'posts_per_page',
			'posts_per_rss',
			'rss_use_excerpt',
			'show_on_front',
			'page_on_front',
			'page_for_posts',
			'blog_public',
		),
		'writing'    => array(
			'default_category',
			'default_post_format',
			'default_email_category',
			'use_smilies',
			'use_balanceTags',
		),
		'discussion' => array(
			'default_pingback_flag',
			'default_ping_status',
			'default_comment_status',
			'require_name_email',
			'comment_registration',
			'close_comments_for_old_posts',
			'close_comments_days_old',
			'thread_comments',
			'thread_comments_depth',
			'page_comments',
			'comments_per_page',
			'default_comments_page',
			'comment_order',
			'comments_notify',
			'moderation_notify',
			'comment_moderation',
			'moderation_keys',
			'blacklist_keys',
		),
		'media'      => array(
			'thumbnail_size_w',
			'thumbnail_size_h',
			'thumbnail_crop',
			'medium_size_w',
			'medium_size_h',
			'medium_large_size_w',
			'medium_large_size_h',
			'large_size_w',
			'large_size_h',
			'uploads_use_yearmonth_folders',
		),
		'permalinks' => array(
			'permalink_structure',
			'category_base',
			'tag_base',
		),
	);

	/**
	 * Option names and wildcard patterns excluded from tracking by default.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private array $default_exclusions = array(
		'cron',
		'db_version',
		'initial_db_version',
		'recently_edited',
		'active_plugins',
		'uninstall_plugins',
		'auto_core_update_notified',
		'widget_*',
		'theme_mods_*',
		'sidebars_widgets',
		'user_roles',
		'transient_*',
		'_transient_*',
		'_site_transient_*',
		'config_sync_*',
	);

	/**
	 * Return the unique provider identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'options';
	}

	/**
	 * Return the translated human-readable label.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Options', 'syncforge-config-manager' );
	}

	/**
	 * Return provider dependencies.
	 *
	 * Options has no dependencies on other providers.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function get_dependencies(): array {
		return array();
	}

	/**
	 * Return the batch size for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function get_batch_size(): int {
		return 200;
	}

	/**
	 * Export all tracked options grouped by category.
	 *
	 * Core WordPress options are grouped by their settings page category
	 * (general, reading, writing, discussion, media, permalinks).
	 * Discovered plugin/theme options are automatically grouped by their
	 * plugin slug, creating one file per plugin. An active plugins list
	 * is also exported.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function export(): array {
		/**
		 * Filters the list of option names excluded from export.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $exclusions Default excluded option names and patterns.
		 */
		$settings_exclusions = $this->get_settings_exclusions();
		$all_exclusions      = array_merge( $this->default_exclusions, $settings_exclusions );
		$exclusions          = apply_filters( 'config_sync_excluded_options', $all_exclusions );

		/**
		 * Filters additional option names to track beyond the curated groups.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $tracked Extra option names to include in export.
		 */
		$discovered    = OptionDiscovery::get_tracked_options();
		$extra_tracked = apply_filters( 'config_sync_tracked_options', $discovered );

		$result = array();

		// Export active plugins list.
		$result['plugins'] = $this->get_active_plugins_list();

		foreach ( $this->option_groups as $group => $options ) {
			$group_data = array();

			foreach ( $options as $option_name ) {
				if ( $this->is_excluded( $option_name, $exclusions ) ) {
					continue;
				}

				$value = get_option( $option_name );

				if ( false !== $value ) {
					$group_data[ $option_name ] = maybe_unserialize( $value );
				}
			}

			if ( ! empty( $group_data ) ) {
				$result[ $group ] = $group_data;
			}
		}

		// Group discovered options by plugin slug instead of one big file.
		if ( ! empty( $extra_tracked ) ) {
			$plugin_slugs = $this->get_plugin_slug_map();
			$grouped      = $this->group_options_by_plugin( $extra_tracked, $plugin_slugs, $exclusions );

			foreach ( $grouped as $group_name => $group_data ) {
				if ( ! empty( $group_data ) ) {
					$result[ $group_name ] = $group_data;
				}
			}
		}

		return $result;
	}

	/**
	 * Import configuration options into the database.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Grouped options data as exported by export().
	 * @return array{created: int, updated: int, deleted: int, details: string[]}
	 */
	public function import( array $config ): array {
		$created = 0;
		$updated = 0;
		$deleted = 0;
		$details = array();

		foreach ( $config as $group => $options ) {
			if ( ! is_array( $options ) ) {
				continue;
			}

			// Skip the plugins manifest - it is informational only,
			// not importable as wp_options rows.
			if ( 'plugins' === $group ) {
				continue;
			}

			foreach ( $options as $option_name => $value ) {
				$old_value = get_option( $option_name );

				if ( false === $old_value ) {
					add_option( $option_name, $value );
					++$created;
					$details[] = sprintf(
						/* translators: %s: option name */
						__( 'Created option: %s', 'syncforge-config-manager' ),
						$option_name
					);
				} elseif ( maybe_serialize( $old_value ) !== maybe_serialize( $value ) ) {
					update_option( $option_name, $value );
					++$updated;
					$details[] = sprintf(
						/* translators: %s: option name */
						__( 'Updated option: %s', 'syncforge-config-manager' ),
						$option_name
					);
				}
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
	 * Return the list of config file paths for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function get_config_files(): array {
		return array(
			'options/general.yml',
			'options/reading.yml',
			'options/writing.yml',
			'options/discussion.yml',
			'options/media.yml',
			'options/permalinks.yml',
		);
	}

	/**
	 * Check whether an option name is excluded.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $option_name The option name to check.
	 * @param string[] $exclusions  List of exclusion names and wildcard patterns.
	 * @return bool True if the option should be excluded.
	 */
	private function is_excluded( string $option_name, array $exclusions ): bool {
		foreach ( $exclusions as $pattern ) {
			if ( $this->matches_pattern( $option_name, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a name matches a pattern (supports wildcards via fnmatch).
	 *
	 * @since 1.0.0
	 *
	 * @param string $name    The string to test.
	 * @param string $pattern The pattern, which may contain wildcard characters.
	 * @return bool True if the name matches the pattern.
	 */
	private function matches_pattern( string $name, string $pattern ): bool {
		return fnmatch( $pattern, $name );
	}

	/**
	 * Get user-configured exclusion patterns from the settings page.
	 *
	 * @since 1.1.0
	 *
	 * @return string[]
	 */
	private function get_settings_exclusions(): array {
		$settings = get_option( 'config_sync_settings', array() );
		$excluded = $settings['excluded_options'] ?? '';

		if ( empty( $excluded ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map( 'trim', explode( "\n", $excluded ) )
			)
		);
	}

	/**
	 * Get the list of all installed plugins with their status.
	 *
	 * Returns a map of plugin slug to status info. Only plugins that have
	 * settings (discovered options matching their prefix) are included,
	 * plus all active plugins.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, array{file: string, status: string}> Plugin slug => info.
	 */
	private function get_active_plugins_list(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$active      = get_option( 'active_plugins', array() );
		$active_map  = is_array( $active ) ? array_flip( $active ) : array();
		$plugins     = array();

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$slug = dirname( $plugin_file );
			if ( '.' === $slug ) {
				$slug = basename( $plugin_file, '.php' );
			}

			$status = isset( $active_map[ $plugin_file ] ) ? 'active' : 'inactive';

			$plugins[ $slug ] = array(
				'file'    => $plugin_file,
				'status'  => $status,
				'version' => $plugin_data['Version'] ?? '',
			);
		}

		ksort( $plugins );

		return $plugins;
	}

	/**
	 * Build a map of option name prefixes to plugin slugs.
	 *
	 * Scans all installed plugins (active and inactive) to derive common
	 * option name prefixes from their directory and main file names.
	 * For example, plugin "advanced-custom-fields-pro/acf.php" produces
	 * prefixes like "acf_" and "advanced_custom_fields_pro_". This is
	 * used to group discovered options by their owning plugin.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, string> Prefix => plugin slug.
	 */
	private function get_plugin_slug_map(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();
		$prefixes    = array();

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$slug = dirname( $plugin_file );
			if ( '.' === $slug ) {
				$slug = basename( $plugin_file, '.php' );
			}

			// Normalize: replace hyphens with underscores for prefix matching.
			$normalized = str_replace( '-', '_', $slug );

			// Add the full normalized slug as prefix.
			$prefixes[ $normalized . '_' ] = $slug;

			// Also try the main file name as prefix (e.g., "acf" from "acf.php").
			$main_file       = basename( $plugin_file, '.php' );
			$main_normalized = str_replace( '-', '_', $main_file );

			if ( $main_normalized !== $normalized ) {
				$prefixes[ $main_normalized . '_' ] = $slug;
			}

			// Use the text domain if available and different from slug.
			if ( ! empty( $plugin_data['TextDomain'] ) ) {
				$td_normalized = str_replace( '-', '_', $plugin_data['TextDomain'] );
				if ( $td_normalized !== $normalized && $td_normalized !== $main_normalized ) {
					$prefixes[ $td_normalized . '_' ] = $slug;
				}
			}
		}

		// Sort by prefix length descending so longer/more specific prefixes match first.
		uksort( $prefixes, static function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		} );

		return $prefixes;
	}

	/**
	 * Group discovered options by their plugin slug.
	 *
	 * Matches each option name against known plugin prefixes. Options that
	 * do not match any known plugin are auto-grouped by their first name
	 * segment, but only if at least 3 options share that prefix. Otherwise
	 * they are placed in a "misc" group.
	 *
	 * @since 1.2.0
	 *
	 * @param string[] $option_names Option names to group.
	 * @param array    $plugin_slugs Prefix => slug map from get_plugin_slug_map().
	 * @param string[] $exclusions   Exclusion patterns.
	 * @return array<string, array<string, mixed>> Grouped options keyed by slug.
	 */
	private function group_options_by_plugin( array $option_names, array $plugin_slugs, array $exclusions ): array {
		$groups        = array();
		$pending       = array();
		$pending_names = array();

		foreach ( $option_names as $option_name ) {
			if ( $this->is_excluded( $option_name, $exclusions ) ) {
				continue;
			}

			$value = get_option( $option_name );

			if ( false === $value ) {
				continue;
			}

			$group_key = $this->detect_plugin_group( $option_name, $plugin_slugs );

			if ( 'misc' === $group_key ) {
				// Stage for threshold check before committing to a group.
				$name_normalized = str_replace( '-', '_', strtolower( ltrim( $option_name, '_' ) ) );
				$prefix = $this->extract_prefix( $name_normalized );
				if ( '' !== $prefix ) {
					$pending[ $prefix ][ $option_name ] = maybe_unserialize( $value );
					$pending_names[] = $option_name;
				} else {
					$groups['misc'][ $option_name ] = maybe_unserialize( $value );
				}
			} else {
				$groups[ $group_key ][ $option_name ] = maybe_unserialize( $value );
			}
		}

		// Only promote auto-detected prefix groups with 3+ options.
		foreach ( $pending as $prefix => $options ) {
			if ( count( $options ) >= 3 ) {
				$groups[ $prefix ] = $options;
			} else {
				foreach ( $options as $name => $val ) {
					$groups['misc'][ $name ] = $val;
				}
			}
		}

		// Sort groups alphabetically.
		ksort( $groups );

		return $groups;
	}

	/**
	 * Detect which plugin an option belongs to based on its name prefix.
	 *
	 * @since 1.2.0
	 *
	 * @param string $option_name  The option name.
	 * @param array  $plugin_slugs Prefix => slug map.
	 * @return string The plugin slug, or "misc" if no match.
	 */
	private function detect_plugin_group( string $option_name, array $plugin_slugs ): string {
		// Strip leading underscore for matching (e.g., "_options_footer" -> "options_footer").
		$name_for_matching = ltrim( $option_name, '_' );
		$name_normalized   = str_replace( '-', '_', strtolower( $name_for_matching ) );

		foreach ( $plugin_slugs as $prefix => $slug ) {
			$prefix_lower = strtolower( $prefix );
			if ( 0 === strpos( $name_normalized, $prefix_lower ) ) {
				return $slug;
			}
		}

		// ACF Options Pages store fields as "options_<field>" and "_options_<field>".
		if ( 0 === strpos( $name_normalized, 'options_' ) ) {
			return 'advanced-custom-fields-pro';
		}

		// Common WordPress core option patterns not tied to a specific plugin.
		$wp_core_prefixes = array(
			'auto_update', 'auto_plugin', 'comment_max', 'disallowed_keys',
			'default_settings', 'avatar_',
		);
		foreach ( $wp_core_prefixes as $wp_prefix ) {
			if ( 0 === strpos( $name_normalized, $wp_prefix ) ) {
				return 'wordpress';
			}
		}

		return 'misc';
	}

	/**
	 * Extract a meaningful prefix from an option name for auto-grouping.
	 *
	 * Takes the first underscore-separated segment if it is at least 3
	 * characters long and is not a generic/common word.
	 *
	 * @since 1.2.0
	 *
	 * @param string $name_normalized Normalized (lowercase, underscores) option name.
	 * @return string The prefix, or empty string if no meaningful prefix found.
	 */
	private function extract_prefix( string $name_normalized ): string {
		$parts = explode( '_', $name_normalized, 2 );

		if ( count( $parts ) < 2 || strlen( $parts[0] ) < 3 ) {
			return '';
		}

		// Skip generic/common word prefixes that create meaningless groups.
		$generic = array(
			'auto', 'show', 'use', 'theme', 'image', 'links', 'sticky',
			'https', 'default', 'new', 'simple', 'enable', 'disable',
			'custom', 'global', 'site', 'page', 'post', 'comment',
		);

		if ( in_array( $parts[0], $generic, true ) ) {
			return '';
		}

		return $parts[0];
	}
}
