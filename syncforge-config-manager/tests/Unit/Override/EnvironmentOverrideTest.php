<?php
/**
 * Unit tests for the EnvironmentOverride class.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit\Override;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Override\EnvironmentOverride;
use ConfigSync\FileHandler;
use WP_UnitTestCase;

/**
 * Class EnvironmentOverrideTest
 *
 * @since 1.0.0
 * @covers \ConfigSync\Override\EnvironmentOverride
 */
class EnvironmentOverrideTest extends WP_UnitTestCase {

	/**
	 * EnvironmentOverride instance under test.
	 *
	 * @since 1.0.0
	 * @var EnvironmentOverride
	 */
	private EnvironmentOverride $override;

	/**
	 * Temporary config directory.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $config_dir;

	/**
	 * FileHandler instance.
	 *
	 * @since 1.0.0
	 * @var FileHandler
	 */
	private FileHandler $file_handler;

	/**
	 * Set up each test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		$this->config_dir = sys_get_temp_dir() . '/syncforge-env-test-' . uniqid() . '/';
		wp_mkdir_p( $this->config_dir );

		$this->file_handler = new FileHandler( $this->config_dir );
		$this->override     = new EnvironmentOverride( $this->file_handler );
	}

	/**
	 * Tear down each test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function tear_down(): void {
		// Remove temp directory.
		global $wp_filesystem;

		if ( $wp_filesystem instanceof \WP_Filesystem_Base && $wp_filesystem->is_dir( $this->config_dir ) ) {
			$wp_filesystem->delete( $this->config_dir, true );
		}

		remove_all_filters( 'config_sync_environment' );
		remove_all_filters( 'config_sync_environment_overrides' );
		remove_all_filters( 'config_sync_export_environment_overrides' );

		parent::tear_down();
	}

	/**
	 * Test get_environment falls back to wp_get_environment_type.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_environment_returns_wp_environment_type(): void {
		// wp_get_environment_type() returns 'production' by default in test.
		$environment = $this->override->get_environment();
		$this->assertIsString( $environment );
		$this->assertNotEmpty( $environment );
	}

	/**
	 * Test get_environment respects the filter.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_environment_respects_filter(): void {
		add_filter( 'config_sync_environment', function () {
			return 'staging';
		} );

		$override = new EnvironmentOverride( $this->file_handler );

		$this->assertSame( 'staging', $override->get_environment() );
	}

	/**
	 * Test get_environment sanitizes the value.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_environment_sanitizes_value(): void {
		add_filter( 'config_sync_environment', function () {
			return 'My <Unsafe> Env!';
		} );

		$override = new EnvironmentOverride( $this->file_handler );

		$this->assertSame( 'my-unsafe-env', $override->get_environment() );
	}

	/**
	 * Test get_override_path returns correct structure.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_override_path_returns_correct_structure(): void {
		add_filter( 'config_sync_environment', function () {
			return 'staging';
		} );

		$override = new EnvironmentOverride( $this->file_handler );

		$this->assertSame( 'environments/staging/', $override->get_override_path() );
	}

	/**
	 * Test has_overrides returns false when no override file exists.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_has_overrides_returns_false_when_no_file(): void {
		$this->assertFalse( $this->override->has_overrides( 'options' ) );
	}

	/**
	 * Test has_overrides returns true when override file exists.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_has_overrides_returns_true_when_file_exists(): void {
		add_filter( 'config_sync_environment', function () {
			return 'staging';
		} );

		$override = new EnvironmentOverride( $this->file_handler );

		$this->file_handler->write(
			'environments/staging/options.yml',
			array( 'blogname' => 'Staging Site' )
		);

		$this->assertTrue( $override->has_overrides( 'options' ) );
	}

	/**
	 * Test apply_overrides returns original config when no overrides exist.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_apply_overrides_returns_original_when_no_overrides(): void {
		$config = array( 'blogname' => 'My Site' );

		$result = $this->override->apply_overrides( 'options', $config );

		$this->assertSame( $config, $result );
	}

	/**
	 * Test apply_overrides merges environment config on top of base.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_apply_overrides_merges_environment_config(): void {
		add_filter( 'config_sync_environment', function () {
			return 'staging';
		} );

		$override = new EnvironmentOverride( $this->file_handler );

		$this->file_handler->write(
			'environments/staging/options.yml',
			array( 'blogname' => 'Staging Site' )
		);

		$base_config = array(
			'blogname'        => 'Production Site',
			'blogdescription' => 'A WordPress site',
		);

		$result = $override->apply_overrides( 'options', $base_config );

		$this->assertSame( 'Staging Site', $result['blogname'] );
		$this->assertSame( 'A WordPress site', $result['blogdescription'] );
	}

	/**
	 * Test apply_overrides handles remove sentinel.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_apply_overrides_handles_remove_sentinel(): void {
		add_filter( 'config_sync_environment', function () {
			return 'staging';
		} );

		$override = new EnvironmentOverride( $this->file_handler );

		$this->file_handler->write(
			'environments/staging/options.yml',
			array( 'blogdescription' => '__remove__' )
		);

		$base_config = array(
			'blogname'        => 'My Site',
			'blogdescription' => 'A WordPress site',
		);

		$result = $override->apply_overrides( 'options', $base_config );

		$this->assertSame( 'My Site', $result['blogname'] );
		$this->assertArrayNotHasKey( 'blogdescription', $result );
	}

	/**
	 * Test deep_merge with scalar override.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_deep_merge_scalar_override(): void {
		$base     = array( 'key' => 'base_value' );
		$override = array( 'key' => 'override_value' );

		$result = $this->override->deep_merge( $base, $override );

		$this->assertSame( 'override_value', $result['key'] );
	}

	/**
	 * Test deep_merge with nested arrays.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_deep_merge_nested_arrays(): void {
		$base = array(
			'section' => array(
				'key_a' => 'value_a',
				'key_b' => 'value_b',
			),
		);

		$override_data = array(
			'section' => array(
				'key_b' => 'new_value_b',
				'key_c' => 'value_c',
			),
		);

		$result = $this->override->deep_merge( $base, $override_data );

		$this->assertSame( 'value_a', $result['section']['key_a'] );
		$this->assertSame( 'new_value_b', $result['section']['key_b'] );
		$this->assertSame( 'value_c', $result['section']['key_c'] );
	}

	/**
	 * Test deep_merge with remove sentinel.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_deep_merge_remove_sentinel(): void {
		$base = array(
			'keep_me'   => 'value',
			'remove_me' => 'value',
		);

		$override_data = array(
			'remove_me' => '__remove__',
		);

		$result = $this->override->deep_merge( $base, $override_data );

		$this->assertArrayHasKey( 'keep_me', $result );
		$this->assertArrayNotHasKey( 'remove_me', $result );
	}

	/**
	 * Test deep_merge with nested remove sentinel.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_deep_merge_nested_remove_sentinel(): void {
		$base = array(
			'section' => array(
				'keep_me'   => 'value',
				'remove_me' => 'value',
			),
		);

		$override_data = array(
			'section' => array(
				'remove_me' => '__remove__',
			),
		);

		$result = $this->override->deep_merge( $base, $override_data );

		$this->assertArrayHasKey( 'keep_me', $result['section'] );
		$this->assertArrayNotHasKey( 'remove_me', $result['section'] );
	}

	/**
	 * Test deep_merge adds new keys from override.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_deep_merge_adds_new_keys(): void {
		$base          = array( 'existing' => 'value' );
		$override_data = array( 'new_key' => 'new_value' );

		$result = $this->override->deep_merge( $base, $override_data );

		$this->assertSame( 'value', $result['existing'] );
		$this->assertSame( 'new_value', $result['new_key'] );
	}

	/**
	 * Test deep_merge override scalar replaces array.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_deep_merge_scalar_replaces_array(): void {
		$base          = array( 'key' => array( 'nested' => 'value' ) );
		$override_data = array( 'key' => 'scalar_value' );

		$result = $this->override->deep_merge( $base, $override_data );

		$this->assertSame( 'scalar_value', $result['key'] );
	}

	/**
	 * Test export_overrides writes diff to environment file.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_overrides_writes_diff(): void {
		add_filter( 'config_sync_environment', function () {
			return 'staging';
		} );

		$override = new EnvironmentOverride( $this->file_handler );

		$base_config = array(
			'blogname'        => 'Production Site',
			'blogdescription' => 'A WordPress site',
		);

		$env_config = array(
			'blogname'        => 'Staging Site',
			'blogdescription' => 'A WordPress site',
		);

		$result = $override->export_overrides( 'options', $env_config, $base_config );

		$this->assertTrue( $result );
		$this->assertTrue( $this->file_handler->exists( 'environments/staging/options.yml' ) );

		$written = $this->file_handler->read( 'environments/staging/options.yml' );
		$this->assertSame( 'Staging Site', $written['blogname'] );
		$this->assertArrayNotHasKey( 'blogdescription', $written );
	}

	/**
	 * Test export_overrides marks deleted keys with sentinel.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_overrides_marks_deleted_keys(): void {
		add_filter( 'config_sync_environment', function () {
			return 'staging';
		} );

		$override = new EnvironmentOverride( $this->file_handler );

		$base_config = array(
			'blogname'        => 'My Site',
			'blogdescription' => 'A WordPress site',
			'removed_option'  => 'some value',
		);

		$env_config = array(
			'blogname'        => 'My Site',
			'blogdescription' => 'A WordPress site',
		);

		$result = $override->export_overrides( 'options', $env_config, $base_config );

		$this->assertTrue( $result );

		$written = $this->file_handler->read( 'environments/staging/options.yml' );
		$this->assertSame( '__remove__', $written['removed_option'] );
	}

	/**
	 * Test export_overrides with no differences does not write file.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_overrides_no_diff_skips_write(): void {
		$config = array( 'blogname' => 'Same' );

		$result = $this->override->export_overrides( 'options', $config, $config );

		$this->assertTrue( $result );
	}

	/**
	 * Test export_overrides deletes existing override file when no diff.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_overrides_deletes_file_when_no_diff(): void {
		add_filter( 'config_sync_environment', function () {
			return 'staging';
		} );

		$override = new EnvironmentOverride( $this->file_handler );

		// Write an override file first.
		$this->file_handler->write(
			'environments/staging/options.yml',
			array( 'blogname' => 'Old Override' )
		);

		$this->assertTrue(
			$this->file_handler->exists( 'environments/staging/options.yml' )
		);

		$config = array( 'blogname' => 'Same' );
		$override->export_overrides( 'options', $config, $config );

		$this->assertFalse(
			$this->file_handler->exists( 'environments/staging/options.yml' )
		);
	}

	/**
	 * Test has_overrides sanitizes the provider ID.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_has_overrides_sanitizes_provider_id(): void {
		$this->assertFalse( $this->override->has_overrides( 'Some <Bad> ID!' ) );
	}

	/**
	 * Test full round-trip: export then apply.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_full_round_trip(): void {
		add_filter( 'config_sync_environment', function () {
			return 'staging';
		} );

		$override = new EnvironmentOverride( $this->file_handler );

		$base_config = array(
			'blogname'        => 'Production Site',
			'blogdescription' => 'A WordPress site',
			'admin_email'     => 'admin@prod.example.com',
			'old_setting'     => 'should be removed',
		);

		$env_config = array(
			'blogname'        => 'Staging Site',
			'blogdescription' => 'A WordPress site',
			'admin_email'     => 'admin@staging.example.com',
		);

		$override->export_overrides( 'options', $env_config, $base_config );

		// Now apply overrides to the base config.
		$result = $override->apply_overrides( 'options', $base_config );

		$this->assertSame( 'Staging Site', $result['blogname'] );
		$this->assertSame( 'A WordPress site', $result['blogdescription'] );
		$this->assertSame( 'admin@staging.example.com', $result['admin_email'] );
		$this->assertArrayNotHasKey( 'old_setting', $result );
	}

	/**
	 * Test environment caching within a single instance.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_environment_is_cached(): void {
		$call_count = 0;

		add_filter( 'config_sync_environment', function ( $env ) use ( &$call_count ) {
			$call_count++;
			return 'staging';
		} );

		$override = new EnvironmentOverride( $this->file_handler );

		$override->get_environment();
		$override->get_environment();

		// Filter should only be called once due to caching.
		$this->assertSame( 1, $call_count );
	}
}
