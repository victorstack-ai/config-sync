<?php
/**
 * Unit tests for YamlSanitizer.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit\Sanitizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Sanitizer\YamlSanitizer;
use WP_UnitTestCase;

/**
 * Class YamlSanitizerTest
 *
 * @since 1.0.0
 */
class YamlSanitizerTest extends WP_UnitTestCase {

	/**
	 * The YAML sanitizer instance.
	 *
	 * @var YamlSanitizer
	 */
	private $sanitizer;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->sanitizer = new YamlSanitizer();
	}

	/**
	 * Test sanitize strips dangerous script tags from string values.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_sanitize_strips_script_tags(): void {
		$data = array(
			'blogname' => '<script>alert("xss")</script>My Blog',
		);

		$result = $this->sanitizer->sanitize( $data, 'options' );

		$this->assertArrayHasKey( 'blogname', $result );
		$this->assertStringNotContainsString( '<script>', $result['blogname'] );
		$this->assertStringContainsString( 'My Blog', $result['blogname'] );
	}

	/**
	 * Test sanitize preserves safe HTML in option values.
	 *
	 * Options like cookie banner templates contain HTML that must survive
	 * the sanitization process. wp_kses_post allows safe tags.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_sanitize_preserves_safe_html(): void {
		$data = array(
			'banner_template' => '<div class="banner"><p>Hello <strong>World</strong></p></div>',
		);

		$result = $this->sanitizer->sanitize( $data, 'options' );

		$this->assertStringContainsString( '<div', $result['banner_template'] );
		$this->assertStringContainsString( '<strong>', $result['banner_template'] );
		$this->assertStringContainsString( '<p>', $result['banner_template'] );
	}

	/**
	 * Test sanitize preserves integer values.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_sanitize_preserves_integers(): void {
		$data = array(
			'posts_per_page' => 10,
		);

		$result = $this->sanitizer->sanitize( $data, 'options' );

		$this->assertSame( 10, $result['posts_per_page'] );
	}

	/**
	 * Test sanitize preserves boolean values.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_sanitize_preserves_booleans(): void {
		$data = array(
			'blog_public' => true,
		);

		$result = $this->sanitizer->sanitize( $data, 'options' );

		$this->assertTrue( $result['blog_public'] );
	}

	/**
	 * Test sanitize preserves null values.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_sanitize_preserves_null(): void {
		$data = array(
			'empty_option' => null,
		);

		$result = $this->sanitizer->sanitize( $data, 'options' );

		$this->assertNull( $result['empty_option'] );
	}

	/**
	 * Test sanitize preserves float values.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_sanitize_preserves_floats(): void {
		$data = array(
			'ratio' => 1.5,
		);

		$result = $this->sanitizer->sanitize( $data, 'options' );

		$this->assertSame( 1.5, $result['ratio'] );
	}

	/**
	 * Test sanitize rejects serialized PHP objects.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_serialized_php_objects(): void {
		$data = array(
			'malicious' => 'O:8:"stdClass":1:{s:4:"test";s:5:"value";}',
		);

		$result = $this->sanitizer->sanitize( $data, 'options' );

		$this->assertSame( '', $result['malicious'] );
	}

	/**
	 * Test sanitize rejects serialized objects embedded in arrays.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_embedded_serialized_objects(): void {
		$data = array(
			'nested_malicious' => 'a:1:{i:0;O:8:"stdClass":0:{}}',
		);

		$result = $this->sanitizer->sanitize( $data, 'options' );

		$this->assertSame( '', $result['nested_malicious'] );
	}

	/**
	 * Test sanitize handles nested arrays recursively.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_sanitize_handles_nested_arrays(): void {
		$data = array(
			'parent' => array(
				'child_key'   => '<b>bold</b> text',
				'child_int'   => 42,
				'grandchild'  => array(
					'deep_key' => 'deep value',
				),
			),
		);

		$result = $this->sanitizer->sanitize( $data, 'options' );

		$this->assertIsArray( $result['parent'] );
		// wp_kses_post preserves <b> tags (safe HTML).
		$this->assertStringContainsString( '<b>', $result['parent']['child_key'] );
		$this->assertSame( 42, $result['parent']['child_int'] );
		$this->assertSame( 'deep value', $result['parent']['grandchild']['deep_key'] );
	}

	/**
	 * Test sanitize preserves case in option keys.
	 *
	 * WordPress has case-sensitive option names like options_Careers_cta.
	 * The sanitizer must preserve case using sanitize_text_field, not
	 * sanitize_key which lowercases everything.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_sanitize_preserves_case_in_keys(): void {
		$data = array(
			'options_Careers_cta'  => 'Apply Now',
			'MyPlugin_Settings'   => 'value',
			'UPPERCASE_KEY'       => 'test',
		);

		$result = $this->sanitizer->sanitize( $data, 'options' );

		$this->assertArrayHasKey( 'options_Careers_cta', $result );
		$this->assertArrayHasKey( 'MyPlugin_Settings', $result );
		$this->assertArrayHasKey( 'UPPERCASE_KEY', $result );
	}

	/**
	 * Test sanitize preserves case in nested array keys.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_sanitize_preserves_case_in_nested_keys(): void {
		$data = array(
			'parent' => array(
				'ChildKey'    => 'value1',
				'UPPER_CHILD' => 'value2',
			),
		);

		$result = $this->sanitizer->sanitize( $data, 'options' );

		$this->assertArrayHasKey( 'ChildKey', $result['parent'] );
		$this->assertArrayHasKey( 'UPPER_CHILD', $result['parent'] );
	}

	/**
	 * Test sanitize does NOT strip secret keys from data.
	 *
	 * Secret stripping was removed from the import path because API keys
	 * and passwords ARE configuration that needs to be imported. Secrets
	 * are only redacted in audit logs via redact_for_audit().
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function test_sanitize_preserves_secret_keys(): void {
		$data = array(
			'blogname'       => 'My Blog',
			'stripe_api_key' => 'sk_live_abc123',
			'db_password'    => 'secret123',
		);

		$result = $this->sanitizer->sanitize( $data, 'options' );

		$this->assertArrayHasKey( 'blogname', $result );
		$this->assertArrayHasKey( 'stripe_api_key', $result );
		$this->assertArrayHasKey( 'db_password', $result );
		$this->assertSame( 'sk_live_abc123', $result['stripe_api_key'] );
	}

	// ------------------------------------------------------------------
	// is_secret()
	// ------------------------------------------------------------------

	/**
	 * Test is_secret matches API key pattern.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_is_secret_matches_api_key_pattern(): void {
		$this->assertTrue( $this->sanitizer->is_secret( 'google_api_key' ) );
		$this->assertTrue( $this->sanitizer->is_secret( 'stripe_api_key' ) );
	}

	/**
	 * Test is_secret matches password pattern.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_is_secret_matches_password_pattern(): void {
		$this->assertTrue( $this->sanitizer->is_secret( 'db_password' ) );
		$this->assertTrue( $this->sanitizer->is_secret( 'smtp_password' ) );
	}

	/**
	 * Test is_secret matches token pattern.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_is_secret_matches_token_pattern(): void {
		$this->assertTrue( $this->sanitizer->is_secret( 'access_token' ) );
		$this->assertTrue( $this->sanitizer->is_secret( 'refresh_token' ) );
	}

	/**
	 * Test is_secret matches salt pattern.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_is_secret_matches_salt_pattern(): void {
		$this->assertTrue( $this->sanitizer->is_secret( 'auth_salt' ) );
		$this->assertTrue( $this->sanitizer->is_secret( 'secure_auth_salt' ) );
	}

	/**
	 * Test is_secret does not match normal keys.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_is_secret_does_not_match_normal_keys(): void {
		$this->assertFalse( $this->sanitizer->is_secret( 'blogname' ) );
		$this->assertFalse( $this->sanitizer->is_secret( 'posts_per_page' ) );
		$this->assertFalse( $this->sanitizer->is_secret( 'permalink_structure' ) );
	}

	/**
	 * Test secret patterns are filterable.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_secret_patterns_filterable(): void {
		add_filter(
			'config_sync_secret_patterns',
			function ( $patterns ) {
				$patterns[] = '*_custom_secret';
				return $patterns;
			}
		);

		$this->assertTrue( $this->sanitizer->is_secret( 'my_custom_secret' ) );

		remove_all_filters( 'config_sync_secret_patterns' );
	}

	// ------------------------------------------------------------------
	// redact_for_audit()
	// ------------------------------------------------------------------

	/**
	 * Test redact_for_audit replaces secret values with placeholder.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_redact_for_audit_redacts_secrets(): void {
		$data = array(
			'blogname'       => 'My Blog',
			'stripe_api_key' => 'sk_live_abc123',
			'db_password'    => 'secret123',
		);

		$result = YamlSanitizer::redact_for_audit( $data );

		$this->assertSame( 'My Blog', $result['blogname'] );
		$this->assertSame( '[REDACTED]', $result['stripe_api_key'] );
		$this->assertSame( '[REDACTED]', $result['db_password'] );
	}

	/**
	 * Test redact_for_audit handles nested arrays.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_redact_for_audit_handles_nested(): void {
		$data = array(
			'settings' => array(
				'name'       => 'Test',
				'api_key'    => 'key123',
				'nested'     => array(
					'auth_token' => 'tok_abc',
				),
			),
		);

		$result = YamlSanitizer::redact_for_audit( $data );

		$this->assertSame( 'Test', $result['settings']['name'] );
		// 'api_key' alone does not match '*_api_key' pattern, but let's check.
		// 'auth_token' matches '*_token'.
		$this->assertSame( '[REDACTED]', $result['settings']['nested']['auth_token'] );
	}

	/**
	 * Test redact_for_audit preserves non-secret values unchanged.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_redact_for_audit_preserves_non_secrets(): void {
		$data = array(
			'blogname'        => 'Test Site',
			'posts_per_page'  => 10,
			'show_on_front'   => 'page',
		);

		$result = YamlSanitizer::redact_for_audit( $data );

		$this->assertSame( $data, $result );
	}
}
