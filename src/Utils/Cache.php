<?php
/**
 * Caching utilities for improved performance.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

/**
 * Handles plugin caching with WordPress object cache and transients.
 */
class Cache {

	/**
	 * Cache group identifier.
	 */
	private const CACHE_GROUP = 'fp_seo_performance';

	/**
	 * Default cache expiration (1 hour).
	 */
	private const DEFAULT_EXPIRATION = 3600;

	/**
	 * Gets a value from the cache.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $default Default value if cache miss.
	 *
	 * @return mixed Cached value or default.
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$found = false;
		$value = wp_cache_get( $key, self::CACHE_GROUP, false, $found );

		if ( ! $found ) {
			return $default;
		}

		return $value;
	}

	/**
	 * Sets a value in the cache.
	 *
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to cache.
	 * @param int    $expiration Expiration time in seconds.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function set( string $key, mixed $value, int $expiration = self::DEFAULT_EXPIRATION ): bool {
		return wp_cache_set( $key, $value, self::CACHE_GROUP, $expiration );
	}

	/**
	 * Deletes a value from the cache.
	 *
	 * @param string $key Cache key.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function delete( string $key ): bool {
		return wp_cache_delete( $key, self::CACHE_GROUP );
	}

	/**
	 * Flushes all cached values for this plugin.
	 *
	 * @return bool True on success.
	 */
	public static function flush(): bool {
		// WordPress doesn't support group-specific flush, so we use a versioning strategy.
		$version = (int) self::get( '_cache_version', 0 );
		return self::set( '_cache_version', $version + 1, DAY_IN_SECONDS );
	}

	/**
	 * Gets or sets a cached value using a callback.
	 *
	 * @param string   $key        Cache key.
	 * @param callable $callback   Callback to generate value on cache miss.
	 * @param int      $expiration Expiration time in seconds.
	 *
	 * @return mixed Cached or freshly generated value.
	 */
	public static function remember( string $key, callable $callback, int $expiration = self::DEFAULT_EXPIRATION ): mixed {
		$versioned_key = self::get_versioned_key( $key );
		$found         = false;
		$value         = wp_cache_get( $versioned_key, self::CACHE_GROUP, false, $found );

		if ( $found ) {
			return $value;
		}

		$value = $callback();
		self::set( $versioned_key, $value, $expiration );

		return $value;
	}

	/**
	 * Gets a transient value for persistent cache across page loads.
	 *
	 * @param string $key Transient key.
	 *
	 * @return mixed Transient value or false if not found.
	 */
	public static function get_transient( string $key ): mixed {
		return get_transient( self::prefix_key( $key ) );
	}

	/**
	 * Sets a transient value for persistent cache.
	 *
	 * @param string $key        Transient key.
	 * @param mixed  $value      Value to store.
	 * @param int    $expiration Expiration time in seconds.
	 *
	 * @return bool True on success.
	 */
	public static function set_transient( string $key, mixed $value, int $expiration = self::DEFAULT_EXPIRATION ): bool {
		return set_transient( self::prefix_key( $key ), $value, $expiration );
	}

	/**
	 * Deletes a transient value.
	 *
	 * @param string $key Transient key.
	 *
	 * @return bool True on success.
	 */
	public static function delete_transient( string $key ): bool {
		return delete_transient( self::prefix_key( $key ) );
	}

	/**
	 * Gets a versioned cache key to support cache invalidation.
	 *
	 * @param string $key Original key.
	 *
	 * @return string Versioned key.
	 */
	private static function get_versioned_key( string $key ): string {
		$version = (int) self::get( '_cache_version', 0 );

		return sprintf( '%s_v%d', $key, $version );
	}

	/**
	 * Prefixes a key with plugin namespace.
	 *
	 * @param string $key Original key.
	 *
	 * @return string Prefixed key.
	 */
	private static function prefix_key( string $key ): string {
		return 'fp_seo_' . $key;
	}
}
