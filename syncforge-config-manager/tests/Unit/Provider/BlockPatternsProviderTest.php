<?php
/**
 * Unit tests for the BlockPatternsProvider class.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\Provider\BlockPatternsProvider;
use WP_UnitTestCase;

/**
 * Class BlockPatternsProviderTest
 *
 * @since 1.0.0
 * @covers \ConfigSync\Provider\BlockPatternsProvider
 */
class BlockPatternsProviderTest extends WP_UnitTestCase {

	/**
	 * BlockPatternsProvider instance under test.
	 *
	 * @since 1.0.0
	 * @var BlockPatternsProvider
	 */
	private BlockPatternsProvider $provider;

	/**
	 * IDs of wp_block posts created during tests.
	 *
	 * @since 1.0.0
	 * @var int[]
	 */
	private array $created_post_ids = array();

	/**
	 * Set up each test.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->provider = new BlockPatternsProvider();
	}

	/**
	 * Tear down each test.
	 *
	 * Removes any wp_block posts created during tests.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->created_post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->created_post_ids = array();
		parent::tear_down();
	}

	/**
	 * Test that get_id returns 'block-patterns'.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_id_returns_block_patterns(): void {
		$this->assertSame( 'block-patterns', $this->provider->get_id() );
	}

	/**
	 * Test that get_dependencies returns an empty array.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_dependencies_returns_empty(): void {
		$this->assertSame( array(), $this->provider->get_dependencies() );
	}

	/**
	 * Test that get_batch_size returns 50.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_batch_size_returns_fifty(): void {
		$this->assertSame( 50, $this->provider->get_batch_size() );
	}

	/**
	 * Test that export returns blocks keyed by slug.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_returns_blocks_by_slug(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'wp_block',
				'post_title'   => 'My Header Block',
				'post_content' => '<!-- wp:heading --><h2>Hello</h2><!-- /wp:heading -->',
				'post_status'  => 'publish',
				'post_name'    => 'my-header-block',
			)
		);
		$this->created_post_ids[] = $post_id;

		$exported = $this->provider->export();

		$this->assertIsArray( $exported );
		$this->assertArrayHasKey( 'my-header-block', $exported );
	}

	/**
	 * Test that export includes title, content, and status for each block.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_export_includes_title_content_status(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'wp_block',
				'post_title'   => 'Test Pattern',
				'post_content' => '<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
				'post_name'    => 'test-pattern',
			)
		);
		$this->created_post_ids[] = $post_id;

		$exported = $this->provider->export();
		$block    = $exported['test-pattern'];

		$this->assertArrayHasKey( 'title', $block );
		$this->assertArrayHasKey( 'content', $block );
		$this->assertArrayHasKey( 'status', $block );
		$this->assertSame( 'Test Pattern', $block['title'] );
		$this->assertSame( 'publish', $block['status'] );
	}

	/**
	 * Test that import creates a new wp_block post.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_creates_new_block(): void {
		$config = array(
			'new-hero-block' => array(
				'title'   => 'New Hero Block',
				'content' => '<!-- wp:heading --><h2>Hero</h2><!-- /wp:heading -->',
				'status'  => 'publish',
			),
		);

		$result = $this->provider->import( $config );

		$this->assertSame( 1, $result['created'] );
		$this->assertSame( 0, $result['updated'] );

		$post = get_page_by_path( 'new-hero-block', OBJECT, 'wp_block' );
		$this->assertNotNull( $post );
		$this->assertSame( 'New Hero Block', $post->post_title );

		$this->created_post_ids[] = $post->ID;
	}

	/**
	 * Test that import updates an existing wp_block post.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_updates_existing_block(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'wp_block',
				'post_title'   => 'Old Title',
				'post_content' => '<!-- wp:paragraph --><p>Old</p><!-- /wp:paragraph -->',
				'post_status'  => 'publish',
				'post_name'    => 'existing-block',
			)
		);
		$this->created_post_ids[] = $post_id;

		$config = array(
			'existing-block' => array(
				'title'   => 'Updated Title',
				'content' => '<!-- wp:paragraph --><p>Updated</p><!-- /wp:paragraph -->',
				'status'  => 'publish',
			),
		);

		$result = $this->provider->import( $config );

		$this->assertSame( 0, $result['created'] );
		$this->assertSame( 1, $result['updated'] );

		$post = get_post( $post_id );
		$this->assertSame( 'Updated Title', $post->post_title );
	}

	/**
	 * Test that import sanitizes content with wp_kses_post.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_import_sanitizes_content(): void {
		$config = array(
			'unsafe-block' => array(
				'title'   => 'Unsafe Block',
				'content' => '<p>Safe content</p><script>alert("xss")</script>',
				'status'  => 'publish',
			),
		);

		$result = $this->provider->import( $config );

		$this->assertSame( 1, $result['created'] );

		$post = get_page_by_path( 'unsafe-block', OBJECT, 'wp_block' );
		$this->assertNotNull( $post );
		$this->assertStringNotContainsString( '<script>', $post->post_content );
		$this->assertStringContainsString( '<p>Safe content</p>', $post->post_content );

		$this->created_post_ids[] = $post->ID;
	}

	/**
	 * Test that get_config_files returns the block-patterns directory.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_config_files_returns_block_patterns_directory(): void {
		$files = $this->provider->get_config_files();

		$this->assertIsArray( $files );
		$this->assertContains( 'block-patterns/', $files );
	}

	/**
	 * Test that get_label returns the translated label.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_label_returns_block_patterns(): void {
		$this->assertSame( 'Block Patterns', $this->provider->get_label() );
	}
}
