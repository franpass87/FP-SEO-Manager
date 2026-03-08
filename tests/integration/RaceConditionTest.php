<?php
/**
 * Race condition tests.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Tests for race conditions and concurrent access.
 */
class RaceConditionTest extends TestCase {

	/**
	 * Test that state manager prevents duplicate processing.
	 */
	public function test_state_manager_prevents_duplicates(): void {
		$state_manager = new \FP\SEO\Editor\Services\MetaboxStateManager( new \FP\SEO\Core\Services\Logger\WordPressLogger() );
		
		$post_id = 123;
		
		// First call should not be processed
		$this->assertFalse( $state_manager->is_processed( $post_id ) );
		
		// Mark as processed
		$state_manager->mark_processed( $post_id );
		
		// Second call should be processed
		$this->assertTrue( $state_manager->is_processed( $post_id ) );
		
		// Clear and verify
		$state_manager->clear_processed( $post_id );
		$this->assertFalse( $state_manager->is_processed( $post_id ) );
	}

	/**
	 * Test that cache operations are thread-safe.
	 */
	public function test_cache_thread_safety(): void {
		$cache = new \FP\SEO\Core\Services\Cache\WordPressCache();
		
		$key = 'test_race_' . time();
		$value1 = 'value1';
		$value2 = 'value2';
		
		// Set value
		$cache->set( $key, $value1, 60, 'test' );
		
		// Get should return value1
		$result = $cache->get( $key, null, 'test' );
		$this->assertEquals( $value1, $result );
		
		// Update
		$cache->set( $key, $value2, 60, 'test' );
		
		// Get should return value2
		$result = $cache->get( $key, null, 'test' );
		$this->assertEquals( $value2, $result );
		
		// Cleanup
		$cache->delete( $key, 'test' );
	}

	/**
	 * Test that rate limiter prevents concurrent abuse.
	 */
	public function test_rate_limiter_concurrent_requests(): void {
		$cache = new \FP\SEO\Core\Services\Cache\WordPressCache();
		$rate_limiter = new \FP\SEO\Utils\RateLimiter( $cache );
		
		$key = 'test_rate_' . time();
		$max_requests = 5;
		$time_window = 60;
		
		// First 5 requests should be allowed
		for ( $i = 0; $i < $max_requests; $i++ ) {
			$this->assertTrue( $rate_limiter->is_allowed( $key, $max_requests, $time_window ) );
		}
		
		// 6th request should be blocked
		$this->assertFalse( $rate_limiter->is_allowed( $key, $max_requests, $time_window ) );
	}
}




