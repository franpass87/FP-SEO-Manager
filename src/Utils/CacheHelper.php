<?php
/**
 * Cache helper for backward compatibility and easy access to CacheInterface.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use FP\SEO\Infrastructure\Contracts\CacheInterface;
use FP\SEO\Infrastructure\Plugin;

/**
 * Helper class to access CacheInterface from container.
 * 
 * This provides a bridge between static Cache:: methods and dependency injection.
 * Use this when you cannot inject CacheInterface directly.
 */
class CacheHelper {

	/**
	 * Get CacheInterface instance from container.
	 *
	 * @return CacheInterface|null Cache instance or null if not available.
	 */
	public static function get_cache(): ?CacheInterface {
		try {
			$container = Plugin::instance()->get_container();
			return $container->get( CacheInterface::class );
		} catch ( \Throwable $e ) {
			// Fallback to static Cache if container not available
			return null;
		}
	}

	/**
	 * Get value from cache using CacheInterface.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value if not found.
	 * @param string $group   Cache group.
	 * @return mixed
	 */
	public static function get( string $key, $default = null, string $group = 'default' ) {
		$cache = self::get_cache();
		if ( $cache ) {
			return $cache->get( $key, $default, $group );
		}
		$found  = false;
		$cached = wp_cache_get( $key, $group, false, $found );
		return $found ? $cached : $default;
	}

	/**
	 * Set value in cache using CacheInterface.
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl   TTL in seconds.
	 * @param string $group Cache group.
	 * @return bool True on success, false on failure.
	 */
	public static function set( string $key, $value, int $ttl = 3600, string $group = 'default' ): bool {
		$cache = self::get_cache();
		if ( $cache ) {
			return $cache->set( $key, $value, $ttl, $group );
		}
		return wp_cache_set( $key, $value, $group, $ttl );
	}

	/**
	 * Delete value from cache using CacheInterface.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return bool True on success, false on failure.
	 */
	public static function delete( string $key, string $group = 'default' ): bool {
		$cache = self::get_cache();
		if ( $cache ) {
			return $cache->delete( $key, $group );
		}
		return wp_cache_delete( $key, $group );
	}

	/**
	 * Alias for delete() - forget a cached value.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return bool True on success, false on failure.
	 */
	public static function forget( string $key, string $group = 'default' ): bool {
		return self::delete( $key, $group );
	}

	/**
	 * Get or set a cached value using a callback.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Callback to generate value on cache miss.
	 * @param int      $ttl      TTL in seconds.
	 * @param string   $group    Cache group.
	 * @return mixed Cached or freshly generated value.
	 */
	public static function remember( string $key, callable $callback, int $ttl = 3600, string $group = 'default' ) {
		$cache = self::get_cache();
		if ( $cache ) {
			return $cache->remember( $key, $callback, $ttl, $group );
		}
		$found  = false;
		$cached = wp_cache_get( $key, $group, false, $found );
		if ( $found ) {
			return $cached;
		}
		$value = $callback();
		wp_cache_set( $key, $value, $group, $ttl );
		return $value;
	}
}


