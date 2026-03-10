<?php
/**
 * Unit tests for the IdMapper class.
 *
 * @package ConfigSync\Tests\Unit
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\IdMapper;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class IdMapperTest
 *
 * @since 1.0.0
 */
class IdMapperTest extends TestCase {

	/**
	 * IdMapper instance under test.
	 *
	 * @since 1.0.0
	 * @var IdMapper
	 */
	private IdMapper $mapper;

	/**
	 * Mock wpdb instance.
	 *
	 * @since 1.0.0
	 * @var \stdClass
	 */
	private \stdClass $wpdb_mock;

	/**
	 * Set up test fixtures.
	 *
	 * Creates a mock $wpdb global with a prefix and prepare/query/get_var methods.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$this->mapper    = new IdMapper();
		$this->wpdb_mock = new \stdClass();

		$this->wpdb_mock->prefix         = 'wp_';
		$this->wpdb_mock->last_query     = '';
		$this->wpdb_mock->rows_affected  = 0;
		$this->wpdb_mock->insert_id      = 0;
		$this->wpdb_mock->_results       = array();
		$this->wpdb_mock->_get_var_value = null;

		/*
		 * Simple prepare mock that replaces %s and %d placeholders
		 * with the provided arguments for testability.
		 */
		$mock = $this->wpdb_mock;

		$this->wpdb_mock->prepare = function () use ( $mock ) {
			$args  = func_get_args();
			$query = array_shift( $args );
			$i     = 0;
			$query = preg_replace_callback(
				'/%[sd]/',
				function ( $matches ) use ( $args, &$i ) {
					if ( ! isset( $args[ $i ] ) ) {
						return $matches[0];
					}
					$val = $args[ $i ];
					$i++;
					if ( '%d' === $matches[0] ) {
						return (string) (int) $val;
					}
					return "'" . addslashes( (string) $val ) . "'";
				},
				$query
			);
			return $query;
		};

		$GLOBALS['wpdb'] = $this->wpdb_mock;
	}

	/**
	 * Tear down test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tear_down();
	}

	/**
	 * Make the mock wpdb callable for methods.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function enable_wpdb_methods(): void {
		$mock = $this->wpdb_mock;

		/*
		 * Since stdClass does not support __call, we use an anonymous class
		 * that delegates to stored closures and tracks queries.
		 */
		$wpdb = new class() {
			/** @var string */
			public string $prefix = 'wp_';

			/** @var string */
			public string $last_query = '';

			/** @var int */
			public int $rows_affected = 0;

			/** @var int */
			public int $insert_id = 0;

			/** @var array */
			public array $queries = array();

			/** @var mixed */
			public $get_var_return = null;

			/**
			 * Mock prepare.
			 *
			 * @return string
			 */
			public function prepare(): string {
				$args  = func_get_args();
				$query = array_shift( $args );
				$i     = 0;
				$query = preg_replace_callback(
					'/%[sd]/',
					function ( $matches ) use ( $args, &$i ) {
						if ( ! isset( $args[ $i ] ) ) {
							return $matches[0];
						}
						$val = $args[ $i ];
						$i++;
						if ( '%d' === $matches[0] ) {
							return (string) (int) $val;
						}
						return "'" . addslashes( (string) $val ) . "'";
					},
					$query
				);
				return $query;
			}

			/**
			 * Mock query.
			 *
			 * @param string $query SQL query.
			 * @return bool
			 */
			public function query( string $query ): bool {
				$this->queries[]  = $query;
				$this->last_query = $query;
				return true;
			}

			/**
			 * Mock get_var.
			 *
			 * @param string $query SQL query.
			 * @return mixed
			 */
			public function get_var( string $query ) {
				$this->queries[]  = $query;
				$this->last_query = $query;
				return $this->get_var_return;
			}
		};

		$GLOBALS['wpdb'] = $wpdb;
		$this->wpdb_mock = $wpdb;
	}

	/**
	 * Test that set_mapping inserts a new row.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_set_mapping_inserts_new_row(): void {
		$this->enable_wpdb_methods();

		$this->mapper->set_mapping( 'menus', 'main-menu', 42 );

		$this->assertCount( 1, $this->wpdb_mock->queries );
		$this->assertStringContainsString( 'INSERT INTO', $this->wpdb_mock->queries[0] );
		$this->assertStringContainsString( "'menus'", $this->wpdb_mock->queries[0] );
		$this->assertStringContainsString( "'main-menu'", $this->wpdb_mock->queries[0] );
		$this->assertStringContainsString( '42', $this->wpdb_mock->queries[0] );
	}

	/**
	 * Test that set_mapping uses ON DUPLICATE KEY UPDATE for upsert behavior.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_set_mapping_updates_existing_row(): void {
		$this->enable_wpdb_methods();

		$this->mapper->set_mapping( 'menus', 'main-menu', 42 );
		$this->mapper->set_mapping( 'menus', 'main-menu', 99 );

		$this->assertCount( 2, $this->wpdb_mock->queries );

		foreach ( $this->wpdb_mock->queries as $query ) {
			$this->assertStringContainsString( 'ON DUPLICATE KEY UPDATE', $query );
		}

		$this->assertStringContainsString( '99', $this->wpdb_mock->queries[1] );
	}

	/**
	 * Test that get_local_id returns an int for an existing mapping.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_local_id_returns_int_for_existing(): void {
		$this->enable_wpdb_methods();
		$this->wpdb_mock->get_var_return = '42';

		$result = $this->mapper->get_local_id( 'menus', 'main-menu' );

		$this->assertSame( 42, $result );
		$this->assertStringContainsString( 'SELECT local_id', $this->wpdb_mock->last_query );
		$this->assertStringContainsString( "'menus'", $this->wpdb_mock->last_query );
		$this->assertStringContainsString( "'main-menu'", $this->wpdb_mock->last_query );
	}

	/**
	 * Test that get_local_id returns null when no mapping exists.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_local_id_returns_null_for_missing(): void {
		$this->enable_wpdb_methods();
		$this->wpdb_mock->get_var_return = null;

		$result = $this->mapper->get_local_id( 'menus', 'nonexistent' );

		$this->assertNull( $result );
	}

	/**
	 * Test that get_stable_key returns a string for an existing mapping.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_stable_key_returns_string_for_existing(): void {
		$this->enable_wpdb_methods();
		$this->wpdb_mock->get_var_return = 'main-menu';

		$result = $this->mapper->get_stable_key( 'menus', 42 );

		$this->assertSame( 'main-menu', $result );
		$this->assertStringContainsString( 'SELECT stable_key', $this->wpdb_mock->last_query );
		$this->assertStringContainsString( "'menus'", $this->wpdb_mock->last_query );
		$this->assertStringContainsString( '42', $this->wpdb_mock->last_query );
	}

	/**
	 * Test that get_stable_key returns null when no mapping exists.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_get_stable_key_returns_null_for_missing(): void {
		$this->enable_wpdb_methods();
		$this->wpdb_mock->get_var_return = null;

		$result = $this->mapper->get_stable_key( 'menus', 999 );

		$this->assertNull( $result );
	}

	/**
	 * Test that delete_mapping removes a specific row.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_delete_mapping_removes_row(): void {
		$this->enable_wpdb_methods();

		$this->mapper->delete_mapping( 'menus', 'main-menu' );

		$this->assertCount( 1, $this->wpdb_mock->queries );
		$this->assertStringContainsString( 'DELETE FROM', $this->wpdb_mock->queries[0] );
		$this->assertStringContainsString( "'menus'", $this->wpdb_mock->queries[0] );
		$this->assertStringContainsString( "'main-menu'", $this->wpdb_mock->queries[0] );
		$this->assertStringContainsString( 'stable_key', $this->wpdb_mock->queries[0] );
	}

	/**
	 * Test that clear_provider removes all rows for a provider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_clear_provider_removes_all_for_provider(): void {
		$this->enable_wpdb_methods();

		$this->mapper->clear_provider( 'menus' );

		$this->assertCount( 1, $this->wpdb_mock->queries );
		$this->assertStringContainsString( 'DELETE FROM', $this->wpdb_mock->queries[0] );
		$this->assertStringContainsString( "'menus'", $this->wpdb_mock->queries[0] );
		// Should NOT filter by stable_key — removes all rows for the provider.
		$this->assertStringNotContainsString( 'stable_key', $this->wpdb_mock->queries[0] );
	}

	/**
	 * Test that clear_provider does not affect other providers.
	 *
	 * Verifies the SQL only targets the specified provider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_clear_provider_does_not_affect_other_providers(): void {
		$this->enable_wpdb_methods();

		$this->mapper->clear_provider( 'menus' );

		$query = $this->wpdb_mock->queries[0];

		// The query should reference only the 'menus' provider.
		$this->assertStringContainsString( "'menus'", $query );
		$this->assertStringNotContainsString( "'roles'", $query );
		$this->assertStringNotContainsString( "'options'", $query );

		// Verify the WHERE clause scopes to provider only.
		$this->assertStringContainsString( 'WHERE provider =', $query );
	}
}
