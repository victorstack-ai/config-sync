<?php
/**
 * Unit tests for the OptionDiscovery heuristic classification.
 *
 * @package ConfigSync\Tests\Unit\Admin
 * @since   1.2.0
 */

namespace ConfigSync\Tests\Unit\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_UnitTestCase;

/**
 * Class OptionDiscoveryTest
 *
 * Tests the heuristic runtime option classification logic in OptionDiscovery.
 * Uses reflection to test the private is_runtime_option method directly.
 *
 * @since   1.2.0
 * @covers  \ConfigSync\Admin\OptionDiscovery
 */
class OptionDiscoveryTest extends WP_UnitTestCase {

	/**
	 * OptionDiscovery instance.
	 *
	 * @since 1.2.0
	 * @var \ConfigSync\Admin\OptionDiscovery
	 */
	private $discovery;

	/**
	 * ReflectionMethod for the private is_runtime_option method.
	 *
	 * @since 1.2.0
	 * @var \ReflectionMethod
	 */
	private $is_runtime_method;

	/**
	 * Default runtime keywords.
	 *
	 * @since 1.2.0
	 * @var string[]
	 */
	private array $keywords;

	/**
	 * Default runtime suffixes.
	 *
	 * @since 1.2.0
	 * @var string[]
	 */
	private array $suffixes;

	/**
	 * Default runtime prefixes.
	 *
	 * @since 1.2.0
	 * @var string[]
	 */
	private array $prefixes;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->discovery = new \ConfigSync\Admin\OptionDiscovery();

		// Access private method via reflection.
		$reflection = new \ReflectionClass( $this->discovery );
		$this->is_runtime_method = $reflection->getMethod( 'is_runtime_option' );
		$this->is_runtime_method->setAccessible( true );

		// Get the constant values for test parameters.
		$this->keywords = $reflection->getConstant( 'RUNTIME_KEYWORDS' );
		$this->suffixes = $reflection->getConstant( 'RUNTIME_SUFFIXES' );
		$this->prefixes = $reflection->getConstant( 'RUNTIME_PREFIXES' );
	}

	/**
	 * Helper to call the private is_runtime_option method.
	 *
	 * @param string $name Option name.
	 * @return bool
	 */
	private function is_runtime( string $name ): bool {
		return $this->is_runtime_method->invoke(
			$this->discovery,
			$name,
			$this->keywords,
			$this->suffixes,
			$this->prefixes
		);
	}

	// ------------------------------------------------------------------
	// Runtime keyword detection
	// ------------------------------------------------------------------

	/**
	 * Test options with _version keyword are classified as runtime.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_version_options_are_runtime(): void {
		$this->assertTrue( $this->is_runtime( 'wpseo_version' ) );
		$this->assertTrue( $this->is_runtime( 'acf_version' ) );
		$this->assertTrue( $this->is_runtime( 'gravityforms_db_version' ) );
	}

	/**
	 * Test options with _nonce keyword are classified as runtime.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_nonce_options_are_runtime(): void {
		$this->assertTrue( $this->is_runtime( 'wp_rocket_nonce' ) );
		$this->assertTrue( $this->is_runtime( 'some_plugin_pingnonce' ) );
	}

	/**
	 * Test options with _count keyword are classified as runtime.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_count_options_are_runtime(): void {
		$this->assertTrue( $this->is_runtime( 'simple_history_total_rows_count' ) );
		$this->assertTrue( $this->is_runtime( 'plugin_item_count' ) );
	}

	/**
	 * Test options with _dismissed keyword are classified as runtime.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_dismissed_options_are_runtime(): void {
		$this->assertTrue( $this->is_runtime( 'wpseo_dismissed' ) );
		$this->assertTrue( $this->is_runtime( 'notice_dismissed' ) );
	}

	// ------------------------------------------------------------------
	// Hyphen normalization
	// ------------------------------------------------------------------

	/**
	 * Test hyphenated option names are normalized and matched.
	 *
	 * Options like acf-image-aspect-ratio-crop-version use hyphens
	 * instead of underscores but should still match the _version keyword.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_hyphenated_names_are_normalized(): void {
		$this->assertTrue( $this->is_runtime( 'acf-image-aspect-ratio-crop-version' ) );
		$this->assertTrue( $this->is_runtime( 'some-plugin-db-version' ) );
	}

	// ------------------------------------------------------------------
	// Runtime prefix detection
	// ------------------------------------------------------------------

	/**
	 * Test action_scheduler_ prefixed options are classified as runtime.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_action_scheduler_prefix_is_runtime(): void {
		$this->assertTrue( $this->is_runtime( 'action_scheduler_lock_async-request-runner' ) );
		$this->assertTrue( $this->is_runtime( 'action_scheduler_migration_status' ) );
	}

	/**
	 * Test fs_ prefixed options are classified as runtime.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_fs_prefix_is_runtime(): void {
		$this->assertTrue( $this->is_runtime( 'fs_accounts' ) );
		$this->assertTrue( $this->is_runtime( 'fs_api_cache' ) );
	}

	// ------------------------------------------------------------------
	// Runtime suffix detection
	// ------------------------------------------------------------------

	/**
	 * Test options ending with _state are classified as runtime.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_state_suffix_is_runtime(): void {
		$this->assertTrue( $this->is_runtime( 'wpseo_onboarding_state' ) );
		$this->assertTrue( $this->is_runtime( 'plugin_migration_state' ) );
	}

	// ------------------------------------------------------------------
	// Config indicator override
	// ------------------------------------------------------------------

	/**
	 * Test options containing "settings" are never classified as runtime.
	 *
	 * Even if they also contain a runtime keyword, the config indicator
	 * takes precedence.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_settings_options_are_never_runtime(): void {
		$this->assertFalse( $this->is_runtime( 'wpseo_settings_version' ) );
		$this->assertFalse( $this->is_runtime( 'plugin_settings' ) );
		$this->assertFalse( $this->is_runtime( 'cache_settings' ) );
	}

	/**
	 * Test options containing "options" are never classified as runtime.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_options_keyword_overrides_runtime(): void {
		$this->assertFalse( $this->is_runtime( 'wpseo_social_options' ) );
		$this->assertFalse( $this->is_runtime( 'display_options' ) );
	}

	/**
	 * Test options containing "config" are never classified as runtime.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_config_keyword_overrides_runtime(): void {
		$this->assertFalse( $this->is_runtime( 'plugin_config_version' ) );
		$this->assertFalse( $this->is_runtime( 'my_config' ) );
	}

	/**
	 * Test options containing "_key" are never classified as runtime.
	 *
	 * API keys are configuration, not runtime state.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_key_keyword_overrides_runtime(): void {
		$this->assertFalse( $this->is_runtime( 'cloudflare_api_key' ) );
		$this->assertFalse( $this->is_runtime( 'rg_gforms_key' ) );
	}

	// ------------------------------------------------------------------
	// Normal config options (should NOT be runtime)
	// ------------------------------------------------------------------

	/**
	 * Test normal configuration options are not classified as runtime.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_normal_config_options_are_not_runtime(): void {
		$this->assertFalse( $this->is_runtime( 'blogname' ) );
		$this->assertFalse( $this->is_runtime( 'pantheon-cache' ) );
		$this->assertFalse( $this->is_runtime( 'wpseo' ) );
		$this->assertFalse( $this->is_runtime( 'cky_banner_template' ) );
		$this->assertFalse( $this->is_runtime( 'wp_rocket_settings' ) );
		$this->assertFalse( $this->is_runtime( 'autoptimize_css' ) );
	}

	/**
	 * Test the pantheon-cache option is NOT classified as runtime.
	 *
	 * This was a real bug where _cache keyword was too aggressive.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function test_pantheon_cache_is_not_runtime(): void {
		$this->assertFalse( $this->is_runtime( 'pantheon-cache' ) );
	}
}
