<?php
/**
 * Unit tests for SchemaValidator.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\SchemaValidator;
use WP_UnitTestCase;

/**
 * Class SchemaValidatorTest
 *
 * @since 1.0.0
 */
class SchemaValidatorTest extends WP_UnitTestCase {

	/**
	 * The schema validator instance.
	 *
	 * @var SchemaValidator
	 */
	private $validator;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->validator = new SchemaValidator();
	}

	/**
	 * Test validate returns true for valid data against options schema.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_validate_returns_true_for_valid_data(): void {
		$data = array(
			'blogname'        => 'Test Blog',
			'posts_per_page'  => 10,
			'blog_public'     => true,
		);

		$result = $this->validator->validate( 'options', $data );

		$this->assertTrue( $result );
	}

	/**
	 * Test validate returns WP_Error for missing required fields.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_validate_returns_wp_error_for_missing_required(): void {
		$schema_path = dirname( __DIR__, 2 ) . '/src/Schema/rewrite.json';
		$this->validator->register_schema( 'rewrite', $schema_path );

		$data = array(
			'category_base' => 'category',
		);

		$result = $this->validator->validate( 'rewrite', $data );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'config_sync_validation_failed', $result->get_error_code() );
		$this->assertStringContainsString( 'permalink_structure', $result->get_error_message() );
	}

	/**
	 * Test validate returns WP_Error for wrong type.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_validate_returns_wp_error_for_wrong_type(): void {
		$schema_path = dirname( __DIR__, 2 ) . '/src/Schema/rewrite.json';
		$this->validator->register_schema( 'rewrite', $schema_path );

		$data = array(
			'permalink_structure' => 12345,
			'category_base'       => 'category',
		);

		$result = $this->validator->validate( 'rewrite', $data );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'config_sync_validation_failed', $result->get_error_code() );
		$this->assertStringContainsString( 'permalink_structure', $result->get_error_message() );
	}

	/**
	 * Test register_schema loads JSON and stores it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_register_schema_loads_json(): void {
		$schema_path = dirname( __DIR__, 2 ) . '/src/Schema/options.json';
		$this->validator->register_schema( 'options', $schema_path );

		$schema = $this->validator->get_schema( 'options' );

		$this->assertIsArray( $schema );
		$this->assertSame( 'object', $schema['type'] );
	}

	/**
	 * Test get_schema returns null for unknown provider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_schema_returns_null_for_unknown(): void {
		$result = $this->validator->get_schema( 'nonexistent_provider' );

		$this->assertNull( $result );
	}

	/**
	 * Test validate auto-loads default schemas.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_validate_loads_default_schemas(): void {
		$data = array(
			'permalink_structure' => '/%postname%/',
		);

		$result = $this->validator->validate( 'rewrite', $data );

		$this->assertTrue( $result );

		// Verify schema was loaded.
		$schema = $this->validator->get_schema( 'rewrite' );
		$this->assertIsArray( $schema );
	}

	/**
	 * Test validate allows additional properties when schema permits it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_validate_allows_additional_properties(): void {
		$data = array(
			'permalink_structure' => '/%postname%/',
			'custom_field'        => 'custom_value',
		);

		$result = $this->validator->validate( 'rewrite', $data );

		$this->assertTrue( $result );
	}

	/**
	 * Test validate rejects empty provider ID.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_validate_rejects_empty_provider_id(): void {
		$result = $this->validator->validate( '', array( 'key' => 'value' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'config_sync_validation_failed', $result->get_error_code() );
	}

	/**
	 * Test validate returns true for provider with no schema.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_validate_returns_true_for_no_schema(): void {
		$result = $this->validator->validate( 'custom_unknown_provider', array( 'anything' => 'goes' ) );

		$this->assertTrue( $result );
	}

	/**
	 * Test validate checks nested required fields in pattern properties.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_validate_checks_nested_required_in_roles(): void {
		$data = array(
			'editor' => array(
				'name' => 'Editor',
			),
		);

		$result = $this->validator->validate( 'roles', $data );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'capabilities', $result->get_error_message() );
	}
}
