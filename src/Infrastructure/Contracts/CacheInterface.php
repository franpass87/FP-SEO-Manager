<?php
/**
 * Cache interface.
 *
 * @package FP\SEO\Infrastructure\Contracts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Contracts;

/**
 * Interface for cache operations.
 */
interface CacheInterface {
	/**
	 * Get value from cache.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value if not found.
	 * @param string $group   Cache group.
	 * @return mixed
	 */
	public function get( string $key, $default = null, string $group = 'default' );

	/**
	 * Set value in cache.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl   TTL in seconds.
	 * @param string $group Cache group.
	 * @return bool True on success, false on failure.
	 */
	public function set( string $key, $value, int $ttl = 3600, string $group = 'default' ): bool;

	/**
	 * Delete value from cache.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return bool True on success, false on failure.
	 */
	public function delete( string $key, string $group = 'default' ): bool;

	/**
	 * Clear all cache for a group.
	 *
	 * @param string $group Cache group.
	 * @return bool True on success, false on failure.
	 */
	public function clear_group( string $group = 'default' ): bool;

	/**
	 * Get cache statistics.
	 *
	 * @return array<string, int> Statistics array.
	 */
	public function get_stats(): array;

	/**
	 * Get or set a cached value using a callback.
	 *
	 * @param string   $key        Cache key.
	 * @param callable $callback   Callback to generate value on cache miss.
	 * @param int      $ttl        TTL in seconds.
	 * @param string   $group      Cache group.
	 * @return mixed Cached or freshly generated value.
	 */
	public function remember( string $key, callable $callback, int $ttl = 3600, string $group = 'default' );
}















