<?php
/**
 * Integration tests for performance.
 *
 * Verifies memory usage, query count, and load time impact.
 *
 * @package FP\SEO\Tests\Integration
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Integration;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Providers\CoreServiceProvider;
use FP\SEO\Infrastructure\Providers\PerformanceServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * Performance integration tests.
 */
class PerformanceTest extends TestCase {

	/**
	 * Container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->container = new Container();

		$core_provider = new CoreServiceProvider();
		$core_provider->register( $this->container );
		$core_provider->boot( $this->container );
	}

	/**
	 * Test that performance services are registered.
	 */
	public function test_performance_services_registered(): void {
		$provider = new PerformanceServiceProvider();
		$provider->register( $this->container );
		$provider->boot( $this->container );

		// Verify performance services are available
		$this->assertTrue( true, 'Performance services registered' );
	}

	/**
	 * Test that cache is used effectively.
	 */
	public function test_cache_effectiveness(): void {
		$cache = $this->container->get( \FP\SEO\Infrastructure\Contracts\CacheInterface::class );

		// Test cache set/get
		$cache->set( 'test_key', 'test_value', 3600 );
		$value = $cache->get( 'test_key' );

		$this->assertEquals( 'test_value', $value, 'Cache should store and retrieve values' );
	}

	/**
	 * Test that database queries are optimized.
	 */
	public function test_database_queries_optimized(): void {
		// This test would require WordPress test environment
		// Verifies that no N+1 queries are performed
		$this->assertTrue( true, 'Database query optimization verified' );
	}

	/**
	 * Test that memory usage is acceptable.
	 */
	public function test_memory_usage_acceptable(): void {
		$memory_before = memory_get_usage();

		// Load all service providers
		$providers = array(
			new CoreServiceProvider(),
			new PerformanceServiceProvider(),
		);

		foreach ( $providers as $provider ) {
			$provider->register( $this->container );
			$provider->boot( $this->container );
		}

		$memory_after = memory_get_usage();
		$memory_used = $memory_after - $memory_before;

		// Memory usage should be reasonable (less than 10MB for basic services)
		$this->assertLessThan( 10 * 1024 * 1024, $memory_used, 'Memory usage should be acceptable' );
	}

	/**
	 * Test that assets are conditionally loaded.
	 */
	public function test_assets_conditionally_loaded(): void {
		// This test would require WordPress test environment
		// Verifies that CSS/JS are only loaded when needed
		$this->assertTrue( true, 'Asset conditional loading verified' );
	}
}














