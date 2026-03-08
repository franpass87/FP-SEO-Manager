<?php
/**
 * Memory leak tests.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Tests for memory leaks.
 */
class MemoryLeakTest extends TestCase {

	/**
	 * Test that cache doesn't leak memory.
	 */
	public function test_cache_memory_cleanup(): void {
		$cache = new \FP\SEO\Core\Services\Cache\WordPressCache();
		
		$initial_memory = memory_get_usage( true );
		
		// Create many cache entries
		for ( $i = 0; $i < 100; $i++ ) {
			$cache->set( "test_key_{$i}", "test_value_{$i}", 60, 'test' );
		}
		
		$after_set_memory = memory_get_usage( true );
		
		// Delete all entries
		for ( $i = 0; $i < 100; $i++ ) {
			$cache->delete( "test_key_{$i}", 'test' );
		}
		
		$after_delete_memory = memory_get_usage( true );
		
		// Memory should not grow significantly (allow 10MB tolerance)
		$memory_growth = $after_delete_memory - $initial_memory;
		$this->assertLessThan( 10 * 1024 * 1024, $memory_growth, 'Memory leak detected in cache operations' );
	}

	/**
	 * Test that state manager doesn't leak memory.
	 */
	public function test_state_manager_memory_cleanup(): void {
		$state_manager = new \FP\SEO\Editor\Services\MetaboxStateManager( new \FP\SEO\Core\Services\Logger\WordPressLogger() );
		
		$initial_memory = memory_get_usage( true );
		
		// Process many posts
		for ( $i = 0; $i < 1000; $i++ ) {
			$state_manager->mark_processed( $i );
		}
		
		$after_process_memory = memory_get_usage( true );
		
		// Clear all
		$state_manager->clear_all();
		
		$after_clear_memory = memory_get_usage( true );
		
		// Memory should be cleaned up
		$memory_after_clear = $after_clear_memory - $initial_memory;
		$this->assertLessThan( 5 * 1024 * 1024, $memory_after_clear, 'State manager memory not cleaned up' );
	}

	/**
	 * Test that performance monitor doesn't leak memory.
	 */
	public function test_performance_monitor_memory_cleanup(): void {
		$monitor = \FP\SEO\Utils\PerformanceMonitor::get_instance();
		
		$initial_memory = memory_get_usage( true );
		
		// Record many operations
		for ( $i = 0; $i < 1000; $i++ ) {
			$monitor->start_timer( "operation_{$i}" );
			$monitor->end_timer( "operation_{$i}" );
		}
		
		$after_operations_memory = memory_get_usage( true );
		
		// Reset
		$monitor->reset();
		
		$after_reset_memory = memory_get_usage( true );
		
		// Memory should be cleaned up after reset
		$memory_after_reset = $after_reset_memory - $initial_memory;
		$this->assertLessThan( 5 * 1024 * 1024, $memory_after_reset, 'Performance monitor memory not cleaned up' );
	}
}




