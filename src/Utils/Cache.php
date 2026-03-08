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

use FP\SEO\Utils\PerformanceConfig;

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
	 * @deprecated 0.9.0 Use injected CacheInterface instead. This static method will be removed in a future version.
	 * @param string $key Cache key.
	 * @param mixed  $default Default value if cache miss.
	 *
	 * @return mixed Cached value or default.
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		_deprecated_function( __METHOD__, '0.9.0', 'CacheInterface::get() via dependency injection' );
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
	 * @deprecated 0.9.0 Use injected CacheInterface instead. This static method will be removed in a future version.
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to cache.
	 * @param int    $expiration Expiration time in seconds.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function set( string $key, mixed $value, int $expiration = self::DEFAULT_EXPIRATION ): bool {
		_deprecated_function( __METHOD__, '0.9.0', 'CacheInterface::set() via dependency injection' );
		return wp_cache_set( $key, $value, self::CACHE_GROUP, $expiration );
	}

	/**
	 * Deletes a value from the cache.
	 *
	 * @deprecated 0.9.0 Use injected CacheInterface instead. This static method will be removed in a future version.
	 * @param string $key Cache key.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function delete( string $key ): bool {
		_deprecated_function( __METHOD__, '0.9.0', 'CacheInterface::delete() via dependency injection' );
		return wp_cache_delete( $key, self::CACHE_GROUP );
	}

	/**
	 * Flushes all cached values for this plugin.
	 *
	 * @deprecated 0.9.0 Use injected CacheInterface instead. This static method will be removed in a future version.
	 * @return bool True on success.
	 */
	public static function flush(): bool {
		_deprecated_function( __METHOD__, '0.9.0', 'CacheInterface::flush() via dependency injection' );
		// WordPress doesn't support group-specific flush, so we use a versioning strategy.
		$version = (int) self::get( '_cache_version', 0 );
		return self::set( '_cache_version', $version + 1, DAY_IN_SECONDS );
	}

	/**
	 * Gets or sets a cached value using a callback.
	 *
	 * @deprecated 0.9.0 Use injected CacheInterface instead. This static method will be removed in a future version.
	 * @param string   $key        Cache key.
	 * @param callable $callback   Callback to generate value on cache miss.
	 * @param int      $expiration Expiration time in seconds.
	 *
	 * @return mixed Cached or freshly generated value.
	 */
	public static function remember( string $key, callable $callback, int $expiration = self::DEFAULT_EXPIRATION ): mixed {
		_deprecated_function( __METHOD__, '0.9.0', 'CacheInterface::remember() via dependency injection' );
		// Check if caching is enabled
		if ( ! PerformanceConfig::is_feature_enabled( 'cache' ) ) {
			return $callback();
		}

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
	 * Gets or sets a cached value with fallback to transient for long-term storage.
	 *
	 * @param string   $key        Cache key.
	 * @param callable $callback   Callback to generate value on cache miss.
	 * @param int      $expiration Expiration time in seconds.
	 *
	 * @return mixed Cached or freshly generated value.
	 */
	public static function remember_with_fallback( string $key, callable $callback, int $expiration = self::DEFAULT_EXPIRATION ): mixed {
		// Try object cache first
		$versioned_key = self::get_versioned_key( $key );
		$found         = false;
		$value         = wp_cache_get( $versioned_key, self::CACHE_GROUP, false, $found );

		if ( $found ) {
			return $value;
		}

		// Try transient as fallback
		$transient_value = self::get_transient( $key );
		if ( false !== $transient_value ) {
			// Store in object cache for faster access
			self::set( $versioned_key, $transient_value, $expiration );
			return $transient_value;
		}

		// Generate new value
		$value = $callback();
		
		// Store in both caches
		self::set( $versioned_key, $value, $expiration );
		self::set_transient( $key, $value, $expiration );

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
