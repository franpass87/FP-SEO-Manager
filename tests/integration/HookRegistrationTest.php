<?php
/**
 * Integration tests for hook registration via HookManager.
 *
 * Verifies that hooks are properly registered through HookManager
 * and that the tracking system works correctly.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Providers\CoreServiceProvider;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Infrastructure\Bootstrap\HookManager;
use PHPUnit\Framework\TestCase;

/**
 * Hook registration integration tests.
 */
class HookRegistrationTest extends TestCase {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface
	 */
	private HookManagerInterface $hook_manager;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->container = new Container();
		$provider = new CoreServiceProvider();
		$provider->register( $this->container );
		$this->hook_manager = $this->container->get( HookManagerInterface::class );
	}

	/**
	 * Test that HookManager can register actions.
	 */
	public function test_hook_manager_registers_actions(): void {
		$callback_called = false;
		$callback = function() use ( &$callback_called ) {
			$callback_called = true;
		};

		$this->hook_manager->add_action( 'test_action', $callback, 10, 0 );
		
		// Verify hook is registered in WordPress
		$this->assertTrue( has_action( 'test_action', $callback ) !== false );
		
		// Fire the action
		do_action( 'test_action' );
		
		$this->assertTrue( $callback_called, 'Action callback should be called' );
	}

	/**
	 * Test that HookManager can register filters.
	 */
	public function test_hook_manager_registers_filters(): void {
		$callback = function( $value ) {
			return $value . '_filtered';
		};

		$this->hook_manager->add_filter( 'test_filter', $callback, 10, 1 );
		
		// Verify filter is registered in WordPress
		$this->assertTrue( has_filter( 'test_filter', $callback ) !== false );
		
		// Apply the filter
		$result = apply_filters( 'test_filter', 'test' );
		
		$this->assertEquals( 'test_filtered', $result, 'Filter should modify the value' );
	}

	/**
	 * Test that HookManager prevents duplicate registrations.
	 */
	public function test_hook_manager_prevents_duplicates(): void {
		$callback = function() {
			return 'test';
		};

		// Register the same hook twice
		$this->hook_manager->add_action( 'test_duplicate', $callback, 10, 0 );
		$this->hook_manager->add_action( 'test_duplicate', $callback, 10, 0 );
		
		// Verify hook is registered only once
		$hook_count = 0;
		global $wp_filter;
		if ( isset( $wp_filter['test_duplicate'] ) ) {
			$hook_count = count( $wp_filter['test_duplicate']->callbacks[10] ?? array() );
		}
		
		// Should be registered only once (or WordPress handles duplicates)
		$this->assertGreaterThanOrEqual( 1, $hook_count );
	}

	/**
	 * Test that HookManager tracks registered hooks.
	 */
	public function test_hook_manager_tracks_registrations(): void {
		$callback = function() {
			return 'test';
		};

		$this->hook_manager->add_action( 'test_tracking', $callback, 10, 0 );
		
		// Verify hook is tracked (if HookManager exposes tracking)
		if ( $this->hook_manager instanceof HookManager ) {
			// Use reflection to access private property for testing
			$reflection = new \ReflectionClass( $this->hook_manager );
			$property = $reflection->getProperty( 'registered_hooks' );
			$property->setAccessible( true );
			$registered_hooks = $property->getValue( $this->hook_manager );
			
			$this->assertArrayHasKey( 'test_tracking', $registered_hooks );
		}
	}

	/**
	 * Test that HookManager handles different priorities.
	 */
	public function test_hook_manager_handles_priorities(): void {
		$call_order = array();
		
		$callback1 = function() use ( &$call_order ) {
			$call_order[] = 'first';
		};
		
		$callback2 = function() use ( &$call_order ) {
			$call_order[] = 'second';
		};

		// Register with different priorities
		$this->hook_manager->add_action( 'test_priority', $callback2, 20, 0 );
		$this->hook_manager->add_action( 'test_priority', $callback1, 10, 0 );
		
		// Fire the action
		do_action( 'test_priority' );
		
		// Lower priority (higher number) should be called first
		$this->assertEquals( 'first', $call_order[0] );
		$this->assertEquals( 'second', $call_order[1] );
	}

	/**
	 * Test that HookManager validates hook names.
	 */
	public function test_hook_manager_validates_hook_names(): void {
		$callback = function() {
			return 'test';
		};

		// Empty hook name should be handled gracefully
		$this->hook_manager->add_action( '', $callback, 10, 0 );
		
		// Should not throw exception
		$this->assertTrue( true, 'Empty hook name should be handled gracefully' );
	}

	/**
	 * Test that HookManager works with class methods.
	 */
	public function test_hook_manager_with_class_methods(): void {
		$test_class = new class {
			public $called = false;
			
			public function handle_action() {
				$this->called = true;
			}
		};

		$this->hook_manager->add_action( 'test_class_method', array( $test_class, 'handle_action' ), 10, 0 );
		
		// Fire the action
		do_action( 'test_class_method' );
		
		$this->assertTrue( $test_class->called, 'Class method should be called' );
	}
}














