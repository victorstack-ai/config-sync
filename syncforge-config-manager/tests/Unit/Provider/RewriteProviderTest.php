<?php
/**
 * Unit tests for the RewriteProvider class.
 *
 * @package ConfigSync\Tests\Unit\Provider
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Provider\RewriteProvider;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class RewriteProviderTest
 *
 * @since 1.0.0
 */
class RewriteProviderTest extends TestCase {

	/**
	 * RewriteProvider instance under test.
	 *
	 * @since 1.0.0
	 * @var RewriteProvider
	 */
	private RewriteProvider $provider;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->provider = new RewriteProvider();
	}

	/**
	 * Test that get_id returns 'rewrite'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_id_returns_rewrite(): void {
		$this->assertSame( 'rewrite', $this->provider->get_id() );
	}

	/**
	 * Test that get_dependencies includes 'options'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_dependencies_includes_options(): void {
		$dependencies = $this->provider->get_dependencies();

		$this->assertIsArray( $dependencies );
		$this->assertContains( 'options', $dependencies );
	}

	/**
	 * Test that export returns permalink_structure.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_returns_permalink_structure(): void {
		update_option( 'permalink_structure', '/%postname%/' );

		$exported = $this->provider->export();

		$this->assertArrayHasKey( 'permalink_structure', $exported );
		$this->assertSame( '/%postname%/', $exported['permalink_structure'] );
	}

	/**
	 * Test that export includes category_base and tag_base.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_includes_category_and_tag_base(): void {
		update_option( 'category_base', 'topics' );
		update_option( 'tag_base', 'labels' );

		$exported = $this->provider->export();

		$this->assertArrayHasKey( 'category_base', $exported );
		$this->assertSame( 'topics', $exported['category_base'] );
		$this->assertArrayHasKey( 'tag_base', $exported );
		$this->assertSame( 'labels', $exported['tag_base'] );
	}

	/**
	 * Test that import updates permalink_structure when changed.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_updates_permalink_structure(): void {
		update_option( 'permalink_structure', '' );

		$result = $this->provider->import( array(
			'permalink_structure' => '/%year%/%postname%/',
			'category_base'       => '',
			'tag_base'            => '',
		) );

		$this->assertSame( '/%year%/%postname%/', get_option( 'permalink_structure' ) );
		$this->assertGreaterThanOrEqual( 1, $result['updated'] );
		$this->assertIsArray( $result['details'] );
	}

	/**
	 * Test that import calls flush_rewrite_rules when changes are made.
	 *
	 * Verifies indirectly by checking that the rewrite_rules option is
	 * regenerated (non-empty) after importing a permalink structure change.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_flushes_rewrite_rules(): void {
		update_option( 'permalink_structure', '' );
		delete_option( 'rewrite_rules' );

		$this->provider->import( array(
			'permalink_structure' => '/%postname%/',
			'category_base'       => '',
			'tag_base'            => '',
		) );

		/*
		 * After flush_rewrite_rules() is called, WordPress regenerates
		 * the rewrite_rules option. If the option is no longer empty,
		 * it confirms the flush occurred.
		 */
		$rules = get_option( 'rewrite_rules' );
		$this->assertNotEmpty( $rules );
	}

	/**
	 * Test that import returns zero updates when config matches current state.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_no_changes_when_config_matches(): void {
		update_option( 'permalink_structure', '/%postname%/' );
		update_option( 'category_base', '' );
		update_option( 'tag_base', '' );

		$result = $this->provider->import( array(
			'permalink_structure' => '/%postname%/',
			'category_base'       => '',
			'tag_base'            => '',
		) );

		$this->assertSame( 0, $result['updated'] );
		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 0, $result['deleted'] );
		$this->assertSame( array(), $result['details'] );
	}

	/**
	 * Test that get_batch_size returns 10.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_batch_size_returns_ten(): void {
		$this->assertSame( 10, $this->provider->get_batch_size() );
	}

	/**
	 * Test that get_config_files returns rewrite.yml.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_config_files_returns_rewrite_yml(): void {
		$files = $this->provider->get_config_files();

		$this->assertSame( array( 'rewrite.yml' ), $files );
	}

	/**
	 * Test that get_label returns a non-empty translated string.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_label_returns_non_empty_string(): void {
		$label = $this->provider->get_label();

		$this->assertIsString( $label );
		$this->assertNotEmpty( $label );
	}
}
