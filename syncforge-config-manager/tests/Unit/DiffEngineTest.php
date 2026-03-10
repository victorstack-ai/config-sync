<?php
/**
 * Unit tests for the DiffEngine class.
 *
 * @package ConfigSync\Tests\Unit
 * @since   1.0.0
 */

namespace ConfigSync\Tests\Unit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ConfigSync\DiffEngine;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Class DiffEngineTest
 *
 * @since 1.0.0
 */
class DiffEngineTest extends TestCase {

	/**
	 * DiffEngine instance under test.
	 *
	 * @since 1.0.0
	 * @var DiffEngine
	 */
	private DiffEngine $engine;

	/**
	 * Set up test fixtures.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();
		$this->engine = new DiffEngine();
	}

	/**
	 * Test that compute detects keys present in incoming but not in current.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_compute_detects_added_keys(): void {
		$current  = array( 'a' => 1 );
		$incoming = array( 'a' => 1, 'b' => 2 );

		$diff = $this->engine->compute( $current, $incoming );

		$this->assertCount( 1, $diff );
		$this->assertSame( 'added', $diff[0]['type'] );
		$this->assertSame( 'b', $diff[0]['key'] );
		$this->assertNull( $diff[0]['old'] );
		$this->assertSame( 2, $diff[0]['new'] );
	}

	/**
	 * Test that compute detects keys present in current but not in incoming.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_compute_detects_removed_keys(): void {
		$current  = array( 'a' => 1, 'b' => 2 );
		$incoming = array( 'a' => 1 );

		$diff = $this->engine->compute( $current, $incoming );

		$this->assertCount( 1, $diff );
		$this->assertSame( 'removed', $diff[0]['type'] );
		$this->assertSame( 'b', $diff[0]['key'] );
		$this->assertSame( 2, $diff[0]['old'] );
		$this->assertNull( $diff[0]['new'] );
	}

	/**
	 * Test that compute detects modified values.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_compute_detects_modified_values(): void {
		$current  = array( 'a' => 'old_value' );
		$incoming = array( 'a' => 'new_value' );

		$diff = $this->engine->compute( $current, $incoming );

		$this->assertCount( 1, $diff );
		$this->assertSame( 'modified', $diff[0]['type'] );
		$this->assertSame( 'a', $diff[0]['key'] );
		$this->assertSame( 'old_value', $diff[0]['old'] );
		$this->assertSame( 'new_value', $diff[0]['new'] );
	}

	/**
	 * Test that compute returns empty array for identical arrays.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_compute_returns_empty_for_identical_arrays(): void {
		$data = array( 'a' => 1, 'b' => 'hello', 'c' => array( 1, 2, 3 ) );

		$diff = $this->engine->compute( $data, $data );

		$this->assertSame( array(), $diff );
	}

	/**
	 * Test that compute handles empty current (all keys are added).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_compute_handles_empty_current(): void {
		$incoming = array( 'x' => 10, 'y' => 20 );

		$diff = $this->engine->compute( array(), $incoming );

		$this->assertCount( 2, $diff );

		$types = array_column( $diff, 'type' );
		$this->assertSame( array( 'added', 'added' ), $types );
	}

	/**
	 * Test that compute handles empty incoming (all keys are removed).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_compute_handles_empty_incoming(): void {
		$current = array( 'x' => 10, 'y' => 20 );

		$diff = $this->engine->compute( $current, array() );

		$this->assertCount( 2, $diff );

		$types = array_column( $diff, 'type' );
		$this->assertSame( array( 'removed', 'removed' ), $types );
	}

	/**
	 * Test that compute returns empty when both arrays are empty.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_compute_handles_both_empty(): void {
		$diff = $this->engine->compute( array(), array() );

		$this->assertSame( array(), $diff );
	}

	/**
	 * Test that compute detects changes within nested arrays.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_compute_handles_nested_array_changes(): void {
		$current  = array(
			'settings' => array(
				'color'  => 'blue',
				'size'   => 10,
				'nested' => array( 'deep' => true ),
			),
		);
		$incoming = array(
			'settings' => array(
				'color'  => 'red',
				'size'   => 10,
				'nested' => array( 'deep' => true ),
			),
		);

		$diff = $this->engine->compute( $current, $incoming );

		$this->assertCount( 1, $diff );
		$this->assertSame( 'modified', $diff[0]['type'] );
		$this->assertSame( 'settings', $diff[0]['key'] );
	}

	/**
	 * Test that compute detects type changes as modifications.
	 *
	 * String to int and string to array should be treated as different values.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_compute_handles_type_changes(): void {
		$current  = array(
			'count'   => '1',
			'options' => 'none',
		);
		$incoming = array(
			'count'   => 1,
			'options' => array( 'a', 'b' ),
		);

		$diff = $this->engine->compute( $current, $incoming );

		$this->assertCount( 2, $diff );

		$keys = array_column( $diff, 'key' );
		$this->assertContains( 'count', $keys );
		$this->assertContains( 'options', $keys );

		foreach ( $diff as $item ) {
			$this->assertSame( 'modified', $item['type'] );
		}
	}

	/**
	 * Test that compute includes the provider_id in each diff item.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_compute_includes_provider_id(): void {
		$current  = array( 'a' => 1 );
		$incoming = array( 'a' => 2, 'b' => 3 );

		$diff = $this->engine->compute( $current, $incoming, 'options' );

		$this->assertCount( 2, $diff );

		foreach ( $diff as $item ) {
			$this->assertSame( 'options', $item['provider'] );
		}
	}

	/**
	 * Test that the generator yields the same results as compute.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_generator_yields_same_results_as_compute(): void {
		$current  = array(
			'keep'    => 'same',
			'change'  => 'old',
			'remove'  => 'gone',
		);
		$incoming = array(
			'keep'    => 'same',
			'change'  => 'new',
			'add'     => 'fresh',
		);

		$compute_result   = $this->engine->compute( $current, $incoming, 'test' );
		$generator_result = array();

		foreach ( $this->engine->compute_generator( $current, $incoming, 'test' ) as $item ) {
			$generator_result[] = $item;
		}

		$this->assertSame( $compute_result, $generator_result );
	}

	/**
	 * Test that summarize correctly counts items by type.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_summarize_counts_correctly(): void {
		$diff = array(
			array( 'type' => 'added', 'key' => 'a', 'old' => null, 'new' => 1, 'provider' => '' ),
			array( 'type' => 'added', 'key' => 'b', 'old' => null, 'new' => 2, 'provider' => '' ),
			array( 'type' => 'modified', 'key' => 'c', 'old' => 1, 'new' => 2, 'provider' => '' ),
			array( 'type' => 'removed', 'key' => 'd', 'old' => 1, 'new' => null, 'provider' => '' ),
			array( 'type' => 'removed', 'key' => 'e', 'old' => 2, 'new' => null, 'provider' => '' ),
			array( 'type' => 'removed', 'key' => 'f', 'old' => 3, 'new' => null, 'provider' => '' ),
		);

		$summary = $this->engine->summarize( $diff );

		$this->assertSame( 2, $summary['added'] );
		$this->assertSame( 1, $summary['modified'] );
		$this->assertSame( 3, $summary['removed'] );
	}

	/**
	 * Test that format_for_rest includes diff items and a summary.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function test_format_for_rest_includes_summary(): void {
		$diff = array(
			array( 'type' => 'added', 'key' => 'a', 'old' => null, 'new' => 1, 'provider' => '' ),
			array( 'type' => 'modified', 'key' => 'b', 'old' => 1, 'new' => 2, 'provider' => '' ),
		);

		$result = $this->engine->format_for_rest( $diff );

		$this->assertArrayHasKey( 'items', $result );
		$this->assertArrayHasKey( 'summary', $result );
		$this->assertSame( $diff, $result['items'] );
		$this->assertSame( 1, $result['summary']['added'] );
		$this->assertSame( 1, $result['summary']['modified'] );
		$this->assertSame( 0, $result['summary']['removed'] );
	}
}
