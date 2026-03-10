<?php
/**
 * Block Patterns provider for Config Sync.
 *
 * Exports and imports reusable block patterns (wp_block post type).
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class BlockPatternsProvider
 *
 * Syncs reusable block patterns stored as wp_block posts between environments.
 * Uses the post_name (slug) as the stable identifier for each block.
 *
 * @since 1.0.0
 */
class BlockPatternsProvider extends AbstractProvider {

	/**
	 * Get the unique identifier for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider ID.
	 */
	public function get_id(): string {
		return 'block-patterns';
	}

	/**
	 * Get the human-readable label for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string Translated label.
	 */
	public function get_label(): string {
		return __( 'Block Patterns', 'syncforge-config-manager' );
	}

	/**
	 * Get the IDs of providers this provider depends on.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Empty array; block patterns have no dependencies.
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
	 * Export all reusable block patterns from the database.
	 *
	 * Queries all published wp_block posts and returns them keyed by slug.
	 * Also stores slug-to-ID mappings via the IdMapper service.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array{title: string, content: string, status: string}> Blocks keyed by slug.
	 */
	public function export(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'wp_block',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		$id_mapper = config_sync()->get_id_mapper();
		$exported  = array();

		foreach ( $posts as $post ) {
			$slug = $post->post_name;

			$exported[ $slug ] = array(
				'title'   => $post->post_title,
				'content' => $post->post_content,
				'status'  => $post->post_status,
			);

			$id_mapper->set_mapping( $this->get_id(), $slug, $post->ID );
		}

		return $exported;
	}

	/**
	 * Import block patterns from configuration.
	 *
	 * Creates new wp_block posts or updates existing ones based on slug.
	 * Content is sanitized with wp_kses_post() before saving.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Block patterns configuration keyed by slug.
	 * @return array{created: int, updated: int, deleted: int, details: string[]} Import results.
	 */
	public function import( array $config ): array {
		$created = 0;
		$updated = 0;
		$deleted = 0;
		$details = array();

		$id_mapper = config_sync()->get_id_mapper();

		foreach ( $config as $slug => $block_data ) {
			$slug    = sanitize_key( $slug );
			$title   = sanitize_text_field( $block_data['title'] );
			$content = wp_kses_post( $block_data['content'] );
			$status  = isset( $block_data['status'] ) ? sanitize_key( $block_data['status'] ) : 'publish';

			$existing = get_page_by_path( $slug, OBJECT, 'wp_block' );

			if ( $existing ) {
				$result = wp_update_post(
					array(
						'ID'           => $existing->ID,
						'post_title'   => $title,
						'post_content' => $content,
						'post_status'  => $status,
						'post_name'    => $slug,
					),
					true
				);

				if ( ! is_wp_error( $result ) ) {
					++$updated;
					$id_mapper->set_mapping( $this->get_id(), $slug, $existing->ID );
					$details[] = sprintf(
						/* translators: %s: block pattern slug */
						__( 'Updated block pattern: %s', 'syncforge-config-manager' ),
						$slug
					);
				}
			} else {
				$post_id = wp_insert_post(
					array(
						'post_type'    => 'wp_block',
						'post_title'   => $title,
						'post_content' => $content,
						'post_status'  => $status,
						'post_name'    => $slug,
					),
					true
				);

				if ( ! is_wp_error( $post_id ) ) {
					++$created;
					$id_mapper->set_mapping( $this->get_id(), $slug, $post_id );
					$details[] = sprintf(
						/* translators: %s: block pattern slug */
						__( 'Created block pattern: %s', 'syncforge-config-manager' ),
						$slug
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
	 * Get the list of configuration file paths for this provider.
	 *
	 * Returns a directory path; one file per block slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Relative file paths.
	 */
	public function get_config_files(): array {
		return array( 'block-patterns/' );
	}
}
