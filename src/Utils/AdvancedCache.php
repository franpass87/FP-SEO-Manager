<?php
/**
 * Advanced caching system with multiple backends and intelligent invalidation.
 *
 * @package FP\SEO\Utils
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use FP\SEO\Exceptions\CacheException;
use FP\SEO\Utils\Logger;

/**
 * Advanced caching system with Redis, Memcached, and WordPress object cache support.
 */
class AdvancedCache {

	/**
	 * Cache backends.
	 */
	private const BACKEND_WP_OBJECT = 'wp_object';
	private const BACKEND_REDIS = 'redis';
	private const BACKEND_MEMCACHED = 'memcached';
	private const BACKEND_TRANSIENT = 'transient';

	/**
	 * Cache TTL constants.
	 */
	private const TTL_SHORT = 300;      // 5 minutes
	private const TTL_MEDIUM = 3600;    // 1 hour
	private const TTL_LONG = 86400;     // 24 hours
	private const TTL_VERY_LONG = 604800; // 7 days

	/**
	 * Cache group prefix.
	 */
	private const GROUP_PREFIX = 'fp_seo_';

	/**
	 * Available backends.
	 *
	 * @var array<string, mixed>
	 */
	private array $backends = [];

	/**
	 * Primary backend.
	 */
	private string $primary_backend;

	/**
	 * Fallback backends.
	 *
	 * @var array<string>
	 */
	private array $fallback_backends = [];

	/**
	 * Cache statistics.
	 *
	 * @var array<string, int>
	 */
	private array $stats = [
		'hits' => 0,
		'misses' => 0,
		'sets' => 0,
		'deletes' => 0,
		'errors' => 0,
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->initialize_backends();
		$this->set_primary_backend();
	}

	/**
	 * Initialize available backends.
	 */
	private function initialize_backends(): void {
		// WordPress Object Cache
		$this->backends[self::BACKEND_WP_OBJECT] = wp_using_ext_object_cache();

		// Redis
		$this->backends[self::BACKEND_REDIS] = $this->is_redis_available();

		// Memcached
		$this->backends[self::BACKEND_MEMCACHED] = $this->is_memcached_available();

		// Transients (always available)
		$this->backends[self::BACKEND_TRANSIENT] = true;
	}

	/**
	 * Set primary backend based on availability.
	 */
	private function set_primary_backend(): void {
		if ( $this->backends[self::BACKEND_REDIS] ) {
			$this->primary_backend = self::BACKEND_REDIS;
			$this->fallback_backends = [self::BACKEND_MEMCACHED, self::BACKEND_WP_OBJECT, self::BACKEND_TRANSIENT];
		} elseif ( $this->backends[self::BACKEND_MEMCACHED] ) {
			$this->primary_backend = self::BACKEND_MEMCACHED;
			$this->fallback_backends = [self::BACKEND_WP_OBJECT, self::BACKEND_TRANSIENT];
		} elseif ( $this->backends[self::BACKEND_WP_OBJECT] ) {
			$this->primary_backend = self::BACKEND_WP_OBJECT;
			$this->fallback_backends = [self::BACKEND_TRANSIENT];
		} else {
			$this->primary_backend = self::BACKEND_TRANSIENT;
			$this->fallback_backends = [];
		}
	}

	/**
	 * Check if Redis is available.
	 */
	private function is_redis_available(): bool {
		return class_exists( 'Redis' ) && defined( 'WP_REDIS_HOST' );
	}

	/**
	 * Check if Memcached is available.
	 */
	private function is_memcached_available(): bool {
		return class_exists( 'Memcached' ) && defined( 'WP_MEMCACHED_HOST' );
	}

	/**
	 * Get value from cache.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $default Default value if not found.
	 * @param string $group Cache group.
	 * @return mixed
	 */
	public function get( string $key, $default = null, string $group = 'default' ) {
		$full_key = $this->get_full_key( $key, $group );

		// Try primary backend first
		$value = $this->get_from_backend( $this->primary_backend, $full_key );
		if ( $value !== false ) {
			$this->stats['hits']++;
			return $value;
		}

		// Try fallback backends
		foreach ( $this->fallback_backends as $backend ) {
			$value = $this->get_from_backend( $backend, $full_key );
			if ( $value !== false ) {
				$this->stats['hits']++;
				// Store in primary backend for next time
				$this->set_in_backend( $this->primary_backend, $full_key, $value, self::TTL_MEDIUM );
				return $value;
			}
		}

		$this->stats['misses']++;
		return $default;
	}

	/**
	 * Set value in cache.
	 *
	 * @param string $key Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl TTL in seconds.
	 * @param string $group Cache group.
	 * @return bool
	 */
	public function set( string $key, $value, int $ttl = self::TTL_MEDIUM, string $group = 'default' ): bool {
		$full_key = $this->get_full_key( $key, $group );
		$success = false;

		// Set in primary backend
		if ( $this->set_in_backend( $this->primary_backend, $full_key, $value, $ttl ) ) {
			$success = true;
		}

		// Set in fallback backends for redundancy
		foreach ( $this->fallback_backends as $backend ) {
			$this->set_in_backend( $backend, $full_key, $value, $ttl );
		}

		if ( $success ) {
			$this->stats['sets']++;
		} else {
			$this->stats['errors']++;
		}

		return $success;
	}

	/**
	 * Delete value from cache.
	 *
	 * @param string $key Cache key.
	 * @param string $group Cache group.
	 * @return bool
	 */
	public function delete( string $key, string $group = 'default' ): bool {
		$full_key = $this->get_full_key( $key, $group );
		$success = false;

		// Delete from primary backend
		if ( $this->delete_from_backend( $this->primary_backend, $full_key ) ) {
			$success = true;
		}

		// Delete from fallback backends
		foreach ( $this->fallback_backends as $backend ) {
			$this->delete_from_backend( $backend, $full_key );
		}

		if ( $success ) {
			$this->stats['deletes']++;
		} else {
			$this->stats['errors']++;
		}

		return $success;
	}

	/**
	 * Clear all cache for a group.
	 *
	 * @param string $group Cache group.
	 * @return bool
	 */
	public function clear_group( string $group = 'default' ): bool {
		$success = true;

		// Clear from all backends
		$all_backends = array_merge( [ $this->primary_backend ], $this->fallback_backends );
		foreach ( $all_backends as $backend ) {
			if ( ! $this->clear_group_from_backend( $backend, $group ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Get cache statistics.
	 *
	 * @return array<string, mixed>
	 */
	public function get_stats(): array {
		return array_merge( $this->stats, [
			'primary_backend' => $this->primary_backend,
			'available_backends' => array_keys( array_filter( $this->backends ) ),
			'hit_rate' => $this->calculate_hit_rate(),
		] );
	}

	/**
	 * Calculate hit rate percentage.
	 */
	private function calculate_hit_rate(): float {
		$total = $this->stats['hits'] + $this->stats['misses'];
		return $total > 0 ? round( ( $this->stats['hits'] / $total ) * 100, 2 ) : 0;
	}

	/**
	 * Get full cache key.
	 */
	private function get_full_key( string $key, string $group ): string {
		return self::GROUP_PREFIX . $group . '_' . $key;
	}

	/**
	 * Get value from specific backend.
	 *
	 * @param string $backend Backend name.
	 * @param string $key Full cache key.
	 * @return mixed
	 */
	private function get_from_backend( string $backend, string $key ) {
		try {
			switch ( $backend ) {
				case self::BACKEND_REDIS:
					return $this->get_from_redis( $key );
				case self::BACKEND_MEMCACHED:
					return $this->get_from_memcached( $key );
				case self::BACKEND_WP_OBJECT:
					return wp_cache_get( $key, 'fp_seo' );
				case self::BACKEND_TRANSIENT:
					return get_transient( $key );
				default:
					return false;
			}
		} catch ( \Exception $e ) {
			$this->stats['errors']++;
			return false;
		}
	}

	/**
	 * Set value in specific backend.
	 *
	 * @param string $backend Backend name.
	 * @param string $key Full cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl TTL in seconds.
	 * @return bool
	 */
	private function set_in_backend( string $backend, string $key, $value, int $ttl ): bool {
		try {
			switch ( $backend ) {
				case self::BACKEND_REDIS:
					return $this->set_in_redis( $key, $value, $ttl );
				case self::BACKEND_MEMCACHED:
					return $this->set_in_memcached( $key, $value, $ttl );
				case self::BACKEND_WP_OBJECT:
					return wp_cache_set( $key, $value, 'fp_seo', $ttl );
				case self::BACKEND_TRANSIENT:
					return set_transient( $key, $value, $ttl );
				default:
					return false;
			}
		} catch ( \Exception $e ) {
			$this->stats['errors']++;
			return false;
		}
	}

	/**
	 * Delete value from specific backend.
	 *
	 * @param string $backend Backend name.
	 * @param string $key Full cache key.
	 * @return bool
	 */
	private function delete_from_backend( string $backend, string $key ): bool {
		try {
			switch ( $backend ) {
				case self::BACKEND_REDIS:
					return $this->delete_from_redis( $key );
				case self::BACKEND_MEMCACHED:
					return $this->delete_from_memcached( $key );
				case self::BACKEND_WP_OBJECT:
					return wp_cache_delete( $key, 'fp_seo' );
				case self::BACKEND_TRANSIENT:
					return delete_transient( $key );
				default:
					return false;
			}
		} catch ( \Exception $e ) {
			$this->stats['errors']++;
			return false;
		}
	}

	/**
	 * Clear group from specific backend.
	 *
	 * @param string $backend Backend name.
	 * @param string $group Cache group.
	 * @return bool
	 */
	private function clear_group_from_backend( string $backend, string $group ): bool {
		try {
			switch ( $backend ) {
				case self::BACKEND_REDIS:
					return $this->clear_group_from_redis( $group );
				case self::BACKEND_MEMCACHED:
					return $this->clear_group_from_memcached( $group );
				case self::BACKEND_WP_OBJECT:
					return wp_cache_flush_group( 'fp_seo' );
				case self::BACKEND_TRANSIENT:
					// Transients don't support group clearing
					return true;
				default:
					return false;
			}
		} catch ( \Exception $e ) {
			$this->stats['errors']++;
			return false;
		}
	}

	/**
	 * Redis operations.
	 */
	private function get_from_redis( string $key ) {
		try {
			$redis = new \Redis();
			if ( ! $redis->connect( WP_REDIS_HOST, WP_REDIS_PORT ?? 6379, 2.0 ) ) {
				return false;
			}
			
			$value = $redis->get( $key );
			$redis->close();
			
			// SECURITY FIX: Use safe unserialize with allowed_classes => false
			// to prevent PHP Object Injection attacks
			if ( $value === false ) {
				return false;
			}
			
			try {
				// PHP 7.0+ supports allowed_classes parameter
				$unserialized = @unserialize( $value, [ 'allowed_classes' => false ] );
				return $unserialized !== false ? $unserialized : false;
			} catch ( \Exception $e ) {
				// Log error for debugging
				Logger::debug( 'Redis unserialize error', array( 'error' => $e->getMessage() ) );
				return false;
			}
		} catch ( \Exception $e ) {
			Logger::debug( 'Redis connection error', array( 'error' => $e->getMessage() ) );
			return false;
		}
	}

	private function set_in_redis( string $key, $value, int $ttl ): bool {
		try {
			$redis = new \Redis();
			if ( ! $redis->connect( WP_REDIS_HOST, WP_REDIS_PORT ?? 6379, 2.0 ) ) {
				return false;
			}
			
			$result = $redis->setex( $key, $ttl, serialize( $value ) );
			$redis->close();
			return (bool) $result;
		} catch ( \Exception $e ) {
			Logger::debug( 'Redis set error', array( 'error' => $e->getMessage() ) );
			return false;
		}
	}

	private function delete_from_redis( string $key ): bool {
		try {
			$redis = new \Redis();
			if ( ! $redis->connect( WP_REDIS_HOST, WP_REDIS_PORT ?? 6379, 2.0 ) ) {
				return false;
			}
			
			$result = $redis->del( $key );
			$redis->close();
			return $result > 0;
		} catch ( \Exception $e ) {
			Logger::debug( 'Redis delete error', array( 'error' => $e->getMessage() ) );
			return false;
		}
	}

	private function clear_group_from_redis( string $group ): bool {
		try {
			$redis = new \Redis();
			if ( ! $redis->connect( WP_REDIS_HOST, WP_REDIS_PORT ?? 6379, 2.0 ) ) {
				return false;
			}
			
			$pattern = self::GROUP_PREFIX . $group . '_*';
			$keys = $redis->keys( $pattern );
			if ( ! empty( $keys ) ) {
				$redis->del( $keys );
			}
			$redis->close();
			return true;
		} catch ( \Exception $e ) {
			Logger::debug( 'Redis clear group error', array( 'error' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Memcached operations.
	 */
	private function get_from_memcached( string $key ) {
		try {
			$memcached = new \Memcached();
			if ( ! $memcached->addServer( WP_MEMCACHED_HOST, WP_MEMCACHED_PORT ?? 11211 ) ) {
				return false;
			}
			
			$value = $memcached->get( $key );
			return $value !== false ? $value : false;
		} catch ( \Exception $e ) {
			Logger::debug( 'Memcached get error', array( 'error' => $e->getMessage() ) );
			return false;
		}
	}

	private function set_in_memcached( string $key, $value, int $ttl ): bool {
		try {
			$memcached = new \Memcached();
			if ( ! $memcached->addServer( WP_MEMCACHED_HOST, WP_MEMCACHED_PORT ?? 11211 ) ) {
				return false;
			}
			
			return $memcached->set( $key, $value, $ttl );
		} catch ( \Exception $e ) {
			Logger::debug( 'Memcached set error', array( 'error' => $e->getMessage() ) );
			return false;
		}
	}

	private function delete_from_memcached( string $key ): bool {
		try {
			$memcached = new \Memcached();
			if ( ! $memcached->addServer( WP_MEMCACHED_HOST, WP_MEMCACHED_PORT ?? 11211 ) ) {
				return false;
			}
			
			return $memcached->delete( $key );
		} catch ( \Exception $e ) {
			Logger::debug( 'Memcached delete error', array( 'error' => $e->getMessage() ) );
			return false;
		}
	}

	private function clear_group_from_memcached( string $group ): bool {
		// Memcached doesn't support pattern-based deletion
		// This would require maintaining a list of keys
		return true;
	}
}
