<?php
/**
 * Rate limiting system for API calls and user actions.
 *
 * @package FP\SEO\Utils
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use FP\SEO\Exceptions\RateLimitException;

/**
 * Rate limiter with sliding window algorithm.
 */
class RateLimiter {

	/**
	 * Rate limit configurations.
	 */
	private const RATE_LIMITS = [
		'openai_api' => [
			'requests_per_minute' => 60,
			'requests_per_hour' => 1000,
			'requests_per_day' => 10000,
		],
		'gsc_api' => [
			'requests_per_minute' => 30,
			'requests_per_hour' => 500,
			'requests_per_day' => 5000,
		],
		'bulk_audit' => [
			'requests_per_minute' => 10,
			'requests_per_hour' => 100,
			'requests_per_day' => 1000,
		],
		'ai_optimization' => [
			'requests_per_minute' => 20,
			'requests_per_hour' => 200,
			'requests_per_day' => 2000,
		],
		'general' => [
			'requests_per_minute' => 100,
			'requests_per_hour' => 1000,
			'requests_per_day' => 10000,
		],
	];

	/**
	 * Cache instance.
	 */
	private AdvancedCache $cache;

	/**
	 * Constructor.
	 */
	public function __construct( AdvancedCache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Check if request is allowed.
	 *
	 * @param string $identifier Unique identifier (IP, user ID, etc.).
	 * @param string $action Action being performed.
	 * @return bool
	 * @throws RateLimitException If rate limit exceeded.
	 */
	public function is_allowed( string $identifier, string $action = 'general' ): bool {
		$limits = self::RATE_LIMITS[ $action ] ?? self::RATE_LIMITS['general'];
		
		// Check minute limit
		if ( ! $this->check_limit( $identifier, $action, 'minute', $limits['requests_per_minute'] ) ) {
			throw new RateLimitException(
				sprintf(
					__( 'Rate limit exceeded. Maximum %d requests per minute allowed.', 'fp-seo-performance' ),
					$limits['requests_per_minute']
				),
				'minute_limit_exceeded',
				$limits['requests_per_minute']
			);
		}

		// Check hour limit
		if ( ! $this->check_limit( $identifier, $action, 'hour', $limits['requests_per_hour'] ) ) {
			throw new RateLimitException(
				sprintf(
					__( 'Rate limit exceeded. Maximum %d requests per hour allowed.', 'fp-seo-performance' ),
					$limits['requests_per_hour']
				),
				'hour_limit_exceeded',
				$limits['requests_per_hour']
			);
		}

		// Check day limit
		if ( ! $this->check_limit( $identifier, $action, 'day', $limits['requests_per_day'] ) ) {
			throw new RateLimitException(
				sprintf(
					__( 'Rate limit exceeded. Maximum %d requests per day allowed.', 'fp-seo-performance' ),
					$limits['requests_per_day']
				),
				'day_limit_exceeded',
				$limits['requests_per_day']
			);
		}

		// Record the request
		$this->record_request( $identifier, $action );

		return true;
	}

	/**
	 * Check specific time window limit.
	 *
	 * @param string $identifier Unique identifier.
	 * @param string $action Action being performed.
	 * @param string $window Time window (minute, hour, day).
	 * @param int    $limit Maximum requests allowed.
	 * @return bool
	 */
	private function check_limit( string $identifier, string $action, string $window, int $limit ): bool {
		$key = $this->get_window_key( $identifier, $action, $window );
		$requests = $this->cache->get( $key, [], 'rate_limits' );

		// Clean old requests outside the window
		$requests = $this->clean_old_requests( $requests, $window );

		// Check if limit would be exceeded
		return count( $requests ) < $limit;
	}

	/**
	 * Record a request.
	 *
	 * @param string $identifier Unique identifier.
	 * @param string $action Action being performed.
	 */
	private function record_request( string $identifier, string $action ): void {
		$now = time();
		
		// Record for all time windows
		foreach ( [ 'minute', 'hour', 'day' ] as $window ) {
			$key = $this->get_window_key( $identifier, $action, $window );
			$requests = $this->cache->get( $key, [], 'rate_limits' );
			
			// Clean old requests
			$requests = $this->clean_old_requests( $requests, $window );
			
			// Add current request
			$requests[] = $now;
			
			// Store back with appropriate TTL
			$ttl = $this->get_window_ttl( $window );
			$this->cache->set( $key, $requests, $ttl, 'rate_limits' );
		}
	}

	/**
	 * Get cache key for time window.
	 *
	 * @param string $identifier Unique identifier.
	 * @param string $action Action being performed.
	 * @param string $window Time window.
	 * @return string
	 */
	private function get_window_key( string $identifier, string $action, string $window ): string {
		return sprintf( 'rate_limit_%s_%s_%s_%s', $action, $window, md5( $identifier ), date( 'Y-m-d-H' ) );
	}

	/**
	 * Clean old requests outside the time window.
	 *
	 * @param array<int> $requests Array of timestamps.
	 * @param string     $window Time window.
	 * @return array<int>
	 */
	private function clean_old_requests( array $requests, string $window ): array {
		$now = time();
		$window_seconds = $this->get_window_seconds( $window );
		$cutoff = $now - $window_seconds;

		return array_filter( $requests, fn( $timestamp ) => $timestamp > $cutoff );
	}

	/**
	 * Get window TTL in seconds.
	 *
	 * @param string $window Time window.
	 * @return int
	 */
	private function get_window_ttl( string $window ): int {
		return $this->get_window_seconds( $window ) + 60; // Add 1 minute buffer
	}

	/**
	 * Get window duration in seconds.
	 *
	 * @param string $window Time window.
	 * @return int
	 */
	private function get_window_seconds( string $window ): int {
		return match ( $window ) {
			'minute' => 60,
			'hour' => 3600,
			'day' => 86400,
			default => 60,
		};
	}

	/**
	 * Get current rate limit status.
	 *
	 * @param string $identifier Unique identifier.
	 * @param string $action Action being performed.
	 * @return array<string, mixed>
	 */
	public function get_status( string $identifier, string $action = 'general' ): array {
		$limits = self::RATE_LIMITS[ $action ] ?? self::RATE_LIMITS['general'];
		$status = [];

		foreach ( [ 'minute', 'hour', 'day' ] as $window ) {
			$key = $this->get_window_key( $identifier, $action, $window );
			$requests = $this->cache->get( $key, [], 'rate_limits' );
			$requests = $this->clean_old_requests( $requests, $window );
			
			$limit_key = 'requests_per_' . $window;
			$limit = $limits[ $limit_key ];
			
			$status[ $window ] = [
				'current' => count( $requests ),
				'limit' => $limit,
				'remaining' => max( 0, $limit - count( $requests ) ),
				'percentage' => round( ( count( $requests ) / $limit ) * 100, 2 ),
				'reset_time' => $this->get_reset_time( $window ),
			];
		}

		return $status;
	}

	/**
	 * Get reset time for window.
	 *
	 * @param string $window Time window.
	 * @return int
	 */
	private function get_reset_time( string $window ): int {
		$now = time();
		return match ( $window ) {
			'minute' => $now + ( 60 - ( $now % 60 ) ),
			'hour' => $now + ( 3600 - ( $now % 3600 ) ),
			'day' => $now + ( 86400 - ( $now % 86400 ) ),
			default => $now + 60,
		};
	}

	/**
	 * Reset rate limits for identifier.
	 *
	 * @param string $identifier Unique identifier.
	 * @param string $action Action being performed.
	 * @return bool
	 */
	public function reset( string $identifier, string $action = 'general' ): bool {
		$success = true;
		
		foreach ( [ 'minute', 'hour', 'day' ] as $window ) {
			$key = $this->get_window_key( $identifier, $action, $window );
			if ( ! $this->cache->delete( $key, 'rate_limits' ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Get rate limit configuration.
	 *
	 * @param string $action Action name.
	 * @return array<string, int>
	 */
	public function get_limits( string $action = 'general' ): array {
		return self::RATE_LIMITS[ $action ] ?? self::RATE_LIMITS['general'];
	}

	/**
	 * Check if rate limiting is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return ! defined( 'FP_SEO_DISABLE_RATE_LIMITING' ) || ! FP_SEO_DISABLE_RATE_LIMITING;
	}
}
