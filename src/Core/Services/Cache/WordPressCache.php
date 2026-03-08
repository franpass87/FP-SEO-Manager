<?php
/**
 * WordPress cache implementation with multiple backends.
 *
 * @package FP\SEO\Core\Services\Cache
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Core\Services\Cache;

use FP\SEO\Infrastructure\Contracts\CacheInterface;

/**
 * WordPress cache implementation with Redis, Memcached, and WordPress object cache support.
 *
 * Implements CacheInterface and provides advanced caching with multiple backends.
 */
class WordPressCache implements CacheInterface {

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
	 * @param string $key     Cache key.
	 * @param mixed  $default Default value if not found.
	 * @param string $group   Cache group.
	 * @return mixed Cached value or default.
	 * @throws \RuntimeException If cache backend fails critically.
	 * 
	 * @example
	 * $cache = new WordPressCache();
	 * $value = $cache->get('my_key', 'default_value', 'my_group');
	 * if ($value === 'default_value') {
	 *     // Cache miss, generate value
	 *     $value = generate_expensive_value();
	 *     $cache->set('my_key', $value, 3600, 'my_group');
	 * }
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
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to cache.
	 * @param int    $ttl   TTL in seconds.
	 * @param string $group Cache group.
	 * @return bool True on success, false on failure.
	 */
	public function set( string $key, $value, int $ttl = 3600, string $group = 'default' ): bool {
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
	 * @param string $key   Cache key.
	 * @param string $group Cache group.
	 * @return bool True on success, false on failure.
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
	 * @return bool True on success, false on failure.
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
	 * @return array<string, int> Statistics array.
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
	 * @param string $key     Full cache key.
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
		} catch ( \Throwable $e ) {
			$this->stats['errors']++;
			return false;
		}
	}

	/**
	 * Set value in specific backend.
	 *
	 * @param string $backend Backend name.
	 * @param string $key     Full cache key.
	 * @param mixed  $value   Value to cache.
	 * @param int    $ttl     TTL in seconds.
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
		} catch ( \Throwable $e ) {
			$this->stats['errors']++;
			return false;
		}
	}

	/**
	 * Delete value from specific backend.
	 *
	 * @param string $backend Backend name.
	 * @param string $key     Full cache key.
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
		} catch ( \Throwable $e ) {
			$this->stats['errors']++;
			return false;
		}
	}

	/**
	 * Clear group from specific backend.
	 *
	 * @param string $backend Backend name.
	 * @param string $group   Cache group.
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
		} catch ( \Throwable $e ) {
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

			if ( $value === false ) {
				return false;
			}

			try {
				$unserialized = @unserialize( $value, [ 'allowed_classes' => false ] );
				return $unserialized !== false ? $unserialized : false;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO WordPressCache: Redis unserialize error - ' . $e->getMessage() );
			}
			return false;
		}
	} catch ( \Throwable $e ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'FP SEO WordPressCache: Redis connection error - ' . $e->getMessage() );
		}
		return false;
	}
	}

	/**
	 * Set value in Redis.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   TTL.
	 * @return bool
	 */
	private function set_in_redis( string $key, $value, int $ttl ): bool {
		try {
			$redis = new \Redis();
			if ( ! $redis->connect( WP_REDIS_HOST, WP_REDIS_PORT ?? 6379, 2.0 ) ) {
				return false;
			}

			$result = $redis->setex( $key, $ttl, serialize( $value ) );
			$redis->close();
			return (bool) $result;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO WordPressCache: Redis set error - ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Delete value from Redis.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	private function delete_from_redis( string $key ): bool {
		try {
			$redis = new \Redis();
			if ( ! $redis->connect( WP_REDIS_HOST, WP_REDIS_PORT ?? 6379, 2.0 ) ) {
				return false;
			}

			$result = $redis->del( $key );
			$redis->close();
			return $result > 0;
		} catch ( \Throwable $e ) {
			Logger::debug( 'Redis delete error', array( 'error' => $e->getMessage() ) );
			return false;
		}
	}

	/**
	 * Clear group from Redis.
	 *
	 * @param string $group Group.
	 * @return bool
	 */
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
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO WordPressCache: Redis clear group error - ' . $e->getMessage() );
			}
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
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO WordPressCache: Memcached get error - ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Set value in Memcached.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   TTL.
	 * @return bool
	 */
	private function set_in_memcached( string $key, $value, int $ttl ): bool {
		try {
			$memcached = new \Memcached();
			if ( ! $memcached->addServer( WP_MEMCACHED_HOST, WP_MEMCACHED_PORT ?? 11211 ) ) {
				return false;
			}

			return $memcached->set( $key, $value, $ttl );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO WordPressCache: Memcached set error - ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Delete value from Memcached.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	private function delete_from_memcached( string $key ): bool {
		try {
			$memcached = new \Memcached();
			if ( ! $memcached->addServer( WP_MEMCACHED_HOST, WP_MEMCACHED_PORT ?? 11211 ) ) {
				return false;
			}

			return $memcached->delete( $key );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO WordPressCache: Memcached delete error - ' . $e->getMessage() );
			}
			return false;
		}
	}

	/**
	 * Clear group from Memcached.
	 *
	 * @param string $group Group.
	 * @return bool
	 */
	private function clear_group_from_memcached( string $group ): bool {
		// Memcached doesn't support pattern-based deletion
		return true;
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
	public function remember( string $key, callable $callback, int $ttl = 3600, string $group = 'default' ) {
		// Try to get from cache first
		$cached = $this->get( $key, null, $group );
		if ( $cached !== null ) {
			return $cached;
		}

		// Generate value using callback
		$value = $callback();

		// Store in cache
		$this->set( $key, $value, $ttl, $group );

		return $value;
	}
}















