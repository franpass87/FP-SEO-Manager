<?php
/**
 * Tests for Cache utility.
 *
 * @package FP\SEO\Tests
 */

declare(strict_types=1);

namespace FP\SEO\Tests\Utils;

use Brain\Monkey\Functions;
use FP\SEO\Utils\Cache;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Cache class.
 *
 * @covers \FP\SEO\Utils\Cache
 */
class CacheTest extends TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		\Brain\Monkey\setUp();
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		\Brain\Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test get method returns default when cache misses.
	 */
	public function test_get_returns_default_on_miss(): void {
		Functions\expect( 'wp_cache_get' )
			->once()
			->with( 'test_key', 'fp_seo_performance' )
			->andReturn( false );

		$result = Cache::get( 'test_key', 'default_value' );

		$this->assertSame( 'default_value', $result );
	}

	/**
	 * Test get method returns cached value on hit.
	 */
	public function test_get_returns_cached_value(): void {
		Functions\expect( 'wp_cache_get' )
			->once()
			->with( 'test_key', 'fp_seo_performance' )
			->andReturn( 'cached_value' );

		$result = Cache::get( 'test_key', 'default_value' );

		$this->assertSame( 'cached_value', $result );
	}

	/**
	 * Test set method stores value in cache.
	 */
	public function test_set_stores_value(): void {
		Functions\expect( 'wp_cache_set' )
			->once()
			->with( 'test_key', 'test_value', 'fp_seo_performance', 3600 )
			->andReturn( true );

		$result = Cache::set( 'test_key', 'test_value' );

		$this->assertTrue( $result );
	}

	/**
	 * Test delete method removes value from cache.
	 */
	public function test_delete_removes_value(): void {
		Functions\expect( 'wp_cache_delete' )
			->once()
			->with( 'test_key', 'fp_seo_performance' )
			->andReturn( true );

		$result = Cache::delete( 'test_key' );

		$this->assertTrue( $result );
	}

	/**
	 * Test remember method returns cached value if available.
	 */
	public function test_remember_returns_cached_value(): void {
		Functions\expect( 'wp_cache_get' )
			->twice()
			->andReturn( 0, 'cached_value' );

		$callback_called = false;
		$callback        = static function () use ( &$callback_called ) {
			$callback_called = true;
			return 'fresh_value';
		};

		$result = Cache::remember( 'test_key', $callback );

		$this->assertSame( 'cached_value', $result );
		$this->assertFalse( $callback_called );
	}

	/**
	 * Test remember method executes callback on cache miss.
	 */
	public function test_remember_executes_callback_on_miss(): void {
		Functions\expect( 'wp_cache_get' )
			->twice()
			->andReturn( 0, null );

		Functions\expect( 'wp_cache_set' )
			->once()
			->andReturn( true );

		$callback_called = false;
		$callback        = static function () use ( &$callback_called ) {
			$callback_called = true;
			return 'fresh_value';
		};

		$result = Cache::remember( 'test_key', $callback );

		$this->assertSame( 'fresh_value', $result );
		$this->assertTrue( $callback_called );
	}

	/**
	 * Test transient methods.
	 */
	public function test_transient_operations(): void {
		Functions\expect( 'get_transient' )
			->once()
			->with( 'fp_seo_test_key' )
			->andReturn( 'transient_value' );

		Functions\expect( 'set_transient' )
			->once()
			->with( 'fp_seo_test_key', 'new_value', 3600 )
			->andReturn( true );

		Functions\expect( 'delete_transient' )
			->once()
			->with( 'fp_seo_test_key' )
			->andReturn( true );

		$get_result = Cache::get_transient( 'test_key' );
		$this->assertSame( 'transient_value', $get_result );

		$set_result = Cache::set_transient( 'test_key', 'new_value' );
		$this->assertTrue( $set_result );

		$delete_result = Cache::delete_transient( 'test_key' );
		$this->assertTrue( $delete_result );
	}
}
