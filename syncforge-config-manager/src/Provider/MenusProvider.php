<?php
/**
 * Menus provider — exports and imports WordPress nav menus.
 *
 * @package ConfigSync
 * @since   1.0.0
 */

namespace ConfigSync\Provider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MenusProvider
 *
 * Handles exporting and importing WordPress navigation menus with nested
 * tree structures and slug-based references instead of numeric IDs.
 *
 * @since 1.0.0
 */
class MenusProvider extends AbstractProvider {

	/**
	 * Get the unique provider identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider ID.
	 */
	public function get_id(): string {
		return 'menus';
	}

	/**
	 * Get the translated provider label.
	 *
	 * @since 1.0.0
	 *
	 * @return string Translated label.
	 */
	public function get_label(): string {
		return __( 'Menus', 'syncforge-config-manager' );
	}

	/**
	 * Get provider dependencies.
	 *
	 * Menus depend on options for menu location registration.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of provider IDs.
	 */
	public function get_dependencies(): array {
		return array( 'options' );
	}

	/**
	 * Get the number of items to process per batch.
	 *
	 * @since 1.0.0
	 *
	 * @return int Items per batch.
	 */
	public function get_batch_size(): int {
		return 50;
	}

	/**
	 * Export all navigation menus and their locations.
	 *
	 * Builds a nested tree structure for each menu using slug-based references
	 * instead of numeric IDs. Menu locations are included under the '_locations' key.
	 *
	 * @since 1.0.0
	 *
	 * @return array Exported menu configuration keyed by menu slug.
	 */
	public function export(): array {
		$config = array();
		$menus  = wp_get_nav_menus();

		if ( ! empty( $menus ) ) {
			foreach ( $menus as $menu ) {
				$items     = wp_get_nav_menu_items( $menu->term_id );
				$item_data = array();

				if ( ! empty( $items ) ) {
					foreach ( $items as $item ) {
						$item_data[] = $this->resolve_menu_item_target( $item );
					}
				}

				$config[ $menu->slug ] = array(
					'name'        => $menu->name,
					'description' => $menu->description,
					'items'       => $this->build_menu_tree( $item_data ),
				);
			}
		}

		$config['_locations'] = $this->export_menu_locations();

		return $config;
	}

	/**
	 * Import menus and locations from configuration.
	 *
	 * Creates or updates navigation menus, recreates menu items from the nested
	 * tree structure, and assigns menu locations.
	 *
	 * @since 1.0.0
	 *
	 * @param array $config Menu configuration data.
	 * @return array Change summary with created, updated, deleted, and details keys.
	 */
	public function import( array $config ): array {
		$result = array(
			'created' => 0,
			'updated' => 0,
			'deleted' => 0,
			'details' => array(),
		);

		$locations_config = array();
		if ( isset( $config['_locations'] ) ) {
			$locations_config = $config['_locations'];
			unset( $config['_locations'] );
		}

		$id_mapper       = config_sync()->get_id_mapper();
		$menu_slug_to_id = array();

		foreach ( $config as $menu_slug => $menu_data ) {
			$menu_name = isset( $menu_data['name'] ) ? sanitize_text_field( $menu_data['name'] ) : $menu_slug;
			$menu_desc = isset( $menu_data['description'] ) ? sanitize_text_field( $menu_data['description'] ) : '';

			$existing_menu = wp_get_nav_menu_object( $menu_slug );

			if ( $existing_menu ) {
				$menu_id = wp_update_nav_menu_object(
					$existing_menu->term_id,
					array(
						'menu-name' => $menu_name,
						'description' => $menu_desc,
					)
				);

				if ( ! is_wp_error( $menu_id ) ) {
					$result['updated']++;
					$result['details'][] = sprintf(
						/* translators: %s: menu slug */
						__( 'Updated menu: %s', 'syncforge-config-manager' ),
						$menu_slug
					);

					$this->delete_menu_items( $existing_menu->term_id );
				}
			} else {
				$menu_id = wp_update_nav_menu_object(
					0,
					array(
						'menu-name' => $menu_name,
						'description' => $menu_desc,
					)
				);

				if ( ! is_wp_error( $menu_id ) ) {
					$result['created']++;
					$result['details'][] = sprintf(
						/* translators: %s: menu slug */
						__( 'Created menu: %s', 'syncforge-config-manager' ),
						$menu_slug
					);
				}
			}

			if ( is_wp_error( $menu_id ) ) {
				$result['details'][] = sprintf(
					/* translators: 1: menu slug, 2: error message */
					__( 'Failed to import menu %1$s: %2$s', 'syncforge-config-manager' ),
					$menu_slug,
					$menu_id->get_error_message()
				);
				continue;
			}

			$menu_slug_to_id[ $menu_slug ] = $menu_id;
			$id_mapper->set_mapping( 'menus', $menu_slug, $menu_id );

			if ( ! empty( $menu_data['items'] ) ) {
				$flat_items = $this->flatten_menu_tree( $menu_data['items'] );

				foreach ( $flat_items as $item_config ) {
					$item_data = array(
						'menu-item-title'     => isset( $item_config['title'] ) ? sanitize_text_field( $item_config['title'] ) : '',
						'menu-item-status'    => 'publish',
						'menu-item-parent-id' => isset( $item_config['parent_id'] ) ? absint( $item_config['parent_id'] ) : 0,
						'menu-item-position'  => isset( $item_config['position'] ) ? absint( $item_config['position'] ) : 0,
						'menu-item-attr-title' => isset( $item_config['attr_title'] ) ? sanitize_text_field( $item_config['attr_title'] ) : '',
						'menu-item-target'    => isset( $item_config['target'] ) ? sanitize_text_field( $item_config['target'] ) : '',
						'menu-item-classes'   => isset( $item_config['classes'] ) ? implode( ' ', array_map( 'sanitize_html_class', (array) $item_config['classes'] ) ) : '',
						'menu-item-xfn'       => isset( $item_config['xfn'] ) ? sanitize_text_field( $item_config['xfn'] ) : '',
						'menu-item-description' => isset( $item_config['description'] ) ? sanitize_text_field( $item_config['description'] ) : '',
					);

					$type = isset( $item_config['type'] ) ? sanitize_key( $item_config['type'] ) : 'custom';

					if ( 'post_type' === $type ) {
						$object_type = isset( $item_config['object'] ) ? sanitize_key( $item_config['object'] ) : 'page';
						$object_id   = $this->resolve_target_id( $item_config );

						$item_data['menu-item-type']      = 'post_type';
						$item_data['menu-item-object']    = $object_type;
						$item_data['menu-item-object-id'] = $object_id;
					} elseif ( 'taxonomy' === $type ) {
						$object_type = isset( $item_config['object'] ) ? sanitize_key( $item_config['object'] ) : 'category';
						$object_id   = $this->resolve_target_id( $item_config );

						$item_data['menu-item-type']      = 'taxonomy';
						$item_data['menu-item-object']    = $object_type;
						$item_data['menu-item-object-id'] = $object_id;
					} else {
						$item_data['menu-item-type'] = 'custom';
						$item_data['menu-item-url']  = isset( $item_config['url'] ) ? sanitize_url( $item_config['url'] ) : '';
					}

					$new_item_id = wp_update_nav_menu_item( $menu_id, 0, $item_data );

					if ( ! is_wp_error( $new_item_id ) && ! empty( $item_config['_temp_id'] ) ) {
						$id_mapper->set_mapping( 'menus', 'item_' . $item_config['_temp_id'], $new_item_id );
					}
				}
			}
		}

		if ( ! empty( $locations_config ) ) {
			$location_ids = array();

			foreach ( $locations_config as $location => $menu_slug ) {
				$location = sanitize_key( $location );
				$menu_slug = sanitize_key( $menu_slug );

				if ( isset( $menu_slug_to_id[ $menu_slug ] ) ) {
					$location_ids[ $location ] = $menu_slug_to_id[ $menu_slug ];
				}
			}

			if ( ! empty( $location_ids ) ) {
				set_theme_mod( 'nav_menu_locations', $location_ids );
			}
		}

		return $result;
	}

	/**
	 * Get the configuration file paths for this provider.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of relative file paths.
	 */
	public function get_config_files(): array {
		return array( 'menus/' );
	}

	/**
	 * Build a nested tree structure from flat menu items.
	 *
	 * Converts a flat list of menu items into a parent-child tree using
	 * the menu_item_parent property.
	 *
	 * @since 1.0.0
	 *
	 * @param array $items Flat array of menu item data.
	 * @return array Nested tree structure.
	 */
	private function build_menu_tree( array $items ): array {
		$tree     = array();
		$children = array();

		foreach ( $items as $item ) {
			$parent_id = isset( $item['menu_item_parent'] ) ? absint( $item['menu_item_parent'] ) : 0;
			$item_id   = isset( $item['id'] ) ? absint( $item['id'] ) : 0;

			if ( 0 === $parent_id ) {
				$tree[ $item_id ] = $item;
			} else {
				if ( ! isset( $children[ $parent_id ] ) ) {
					$children[ $parent_id ] = array();
				}
				$children[ $parent_id ][ $item_id ] = $item;
			}
		}

		return $this->attach_children( $tree, $children );
	}

	/**
	 * Recursively attach children to their parent items.
	 *
	 * @since 1.0.0
	 *
	 * @param array $items    Items at the current level.
	 * @param array $children Map of parent_id => child items.
	 * @return array Items with children attached.
	 */
	private function attach_children( array $items, array $children ): array {
		$result = array();

		foreach ( $items as $item_id => $item ) {
			unset( $item['id'], $item['menu_item_parent'] );

			if ( isset( $children[ $item_id ] ) ) {
				$item['children'] = $this->attach_children( $children[ $item_id ], $children );
			}

			$result[] = $item;
		}

		return $result;
	}

	/**
	 * Flatten a nested menu tree for import.
	 *
	 * Converts a nested tree structure back into a flat list with parent_id
	 * references for use with wp_update_nav_menu_item().
	 *
	 * @since 1.0.0
	 *
	 * @param array $tree      Nested tree of menu items.
	 * @param int   $parent_id Parent menu item ID (0 for top-level).
	 * @return array Flat array of menu item configurations.
	 */
	private function flatten_menu_tree( array $tree, int $parent_id = 0 ): array {
		static $position = 0;
		static $temp_id  = 0;

		if ( 0 === $parent_id ) {
			$position = 0;
			$temp_id  = 0;
		}

		$flat = array();

		foreach ( $tree as $item ) {
			$children = array();
			if ( isset( $item['children'] ) ) {
				$children = $item['children'];
				unset( $item['children'] );
			}

			$position++;
			$temp_id++;

			$item['parent_id'] = $parent_id;
			$item['position']  = $position;
			$item['_temp_id']  = $temp_id;

			$flat[] = $item;

			if ( ! empty( $children ) ) {
				$child_items = $this->flatten_menu_tree( $children, $temp_id );
				$flat        = array_merge( $flat, $child_items );
			}
		}

		return $flat;
	}

	/**
	 * Resolve a menu item object to its slug/URL for export.
	 *
	 * Converts numeric WordPress IDs to stable slug-based references.
	 *
	 * @since 1.0.0
	 *
	 * @param object $item WordPress nav menu item object.
	 * @return array Menu item data with slug-based references.
	 */
	private function resolve_menu_item_target( object $item ): array {
		$data = array(
			'id'               => absint( $item->ID ),
			'title'            => $item->title,
			'menu_item_parent' => absint( $item->menu_item_parent ),
			'attr_title'       => $item->attr_title,
			'target'           => $item->target,
			'classes'          => array_filter( (array) $item->classes ),
			'xfn'              => $item->xfn,
			'description'      => $item->description,
		);

		if ( 'post_type' === $item->type ) {
			$post = get_post( $item->object_id );
			$data['type']   = 'post_type';
			$data['object'] = $item->object;
			$data['slug']   = $post ? $post->post_name : '';
		} elseif ( 'taxonomy' === $item->type ) {
			$term = get_term( $item->object_id );
			$data['type']   = 'taxonomy';
			$data['object'] = $item->object;
			$data['slug']   = ( $term && ! is_wp_error( $term ) ) ? $term->slug : '';
		} else {
			$data['type'] = 'custom';
			$data['url']  = $item->url;
		}

		return $data;
	}

	/**
	 * Resolve a slug-based reference back to a WordPress object ID.
	 *
	 * Looks up post or term IDs from slugs stored in the configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item_config Menu item configuration with slug reference.
	 * @return int Resolved WordPress object ID, or 0 if not found.
	 */
	private function resolve_target_id( array $item_config ): int {
		$type   = isset( $item_config['type'] ) ? sanitize_key( $item_config['type'] ) : '';
		$slug   = isset( $item_config['slug'] ) ? sanitize_key( $item_config['slug'] ) : '';
		$object = isset( $item_config['object'] ) ? sanitize_key( $item_config['object'] ) : '';

		if ( empty( $slug ) ) {
			return 0;
		}

		if ( 'post_type' === $type && ! empty( $object ) ) {
			$posts = get_posts(
				array(
					'name'        => $slug,
					'post_type'   => $object,
					'post_status' => 'any',
					'numberposts' => 1,
				)
			);

			if ( ! empty( $posts ) ) {
				return absint( $posts[0]->ID );
			}
		} elseif ( 'taxonomy' === $type && ! empty( $object ) ) {
			$term = get_term_by( 'slug', $slug, $object );

			if ( $term && ! is_wp_error( $term ) ) {
				return absint( $term->term_id );
			}
		}

		return 0;
	}

	/**
	 * Export menu locations mapping.
	 *
	 * Maps registered theme locations to their assigned menu slugs.
	 *
	 * @since 1.0.0
	 *
	 * @return array Location name => menu slug mapping.
	 */
	private function export_menu_locations(): array {
		$locations    = get_nav_menu_locations();
		$registered   = get_registered_nav_menus();
		$location_map = array();

		foreach ( $registered as $location => $label ) {
			if ( isset( $locations[ $location ] ) && $locations[ $location ] > 0 ) {
				$menu = wp_get_nav_menu_object( $locations[ $location ] );

				if ( $menu ) {
					$location_map[ $location ] = $menu->slug;
				}
			}
		}

		return $location_map;
	}

	/**
	 * Delete all items from a nav menu.
	 *
	 * @since 1.0.0
	 *
	 * @param int $menu_id Nav menu term ID.
	 * @return void
	 */
	private function delete_menu_items( int $menu_id ): void {
		$items = wp_get_nav_menu_items( $menu_id );

		if ( ! empty( $items ) ) {
			foreach ( $items as $item ) {
				wp_delete_post( $item->ID, true );
			}
		}
	}
}
