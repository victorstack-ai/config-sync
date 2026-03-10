<?php
/**
 * WordPress option sanitizer.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptionSanitizer
 *
 * Provides type-aware sanitization for known WordPress options.
 *
 * @since 1.0.0
 */
class OptionSanitizer {

	/**
	 * Map of known WordPress options to their expected types.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $option_types = array(
		// Strings.
		'blogname'            => 'string',
		'blogdescription'     => 'string',
		'date_format'         => 'string',
		'time_format'         => 'string',
		'timezone_string'     => 'string',
		'permalink_structure' => 'string',
		'category_base'       => 'string',
		'tag_base'            => 'string',
		'default_role'        => 'string',
		'WPLANG'              => 'string',
		'template'            => 'string',
		'stylesheet'          => 'string',

		// Integers.
		'posts_per_page'           => 'int',
		'posts_per_rss'            => 'int',
		'comments_per_page'        => 'int',
		'page_on_front'            => 'int',
		'page_for_posts'           => 'int',
		'thread_comments_depth'    => 'int',
		'close_comments_days_old'  => 'int',
		'blog_public'              => 'int',

		// Booleans.
		'comment_registration'     => 'bool',
		'default_ping_status'      => 'bool',
		'default_comment_status'   => 'bool',
		'require_name_email'       => 'bool',
		'thread_comments'          => 'bool',
		'close_comments_for_old_posts' => 'bool',
		'page_comments'            => 'bool',
		'show_avatars'             => 'bool',
		'users_can_register'       => 'bool',

		// URLs.
		'siteurl'                  => 'url',
		'home'                     => 'url',

		// HTML.
		'sidebars_widgets'         => 'array',

		// Arrays.
		'active_plugins'           => 'array',
		'recently_activated'       => 'array',
	);

	/**
	 * Sanitize a WordPress option value based on its known type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option_name The option name.
	 * @param mixed  $value       The value to sanitize.
	 * @return mixed The sanitized value.
	 */
	public function sanitize_option( string $option_name, $value ) {
		$type = $this->get_type_for_option( $option_name );

		switch ( $type ) {
			case 'int':
				return absint( $value );

			case 'bool':
				return (bool) $value;

			case 'url':
				return sanitize_url( $value );

			case 'html':
				return wp_kses_post( $value );

			case 'array':
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', $value );
				}
				return array();

			case 'string':
			default:
				if ( is_string( $value ) ) {
					return sanitize_text_field( $value );
				}
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Get the expected type for a WordPress option.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option_name The option name.
	 * @return string The type: 'string', 'int', 'bool', 'url', 'html', or 'array'.
	 */
	public function get_type_for_option( string $option_name ): string {
		if ( isset( self::$option_types[ $option_name ] ) ) {
			return self::$option_types[ $option_name ];
		}

		return 'string';
	}
}
