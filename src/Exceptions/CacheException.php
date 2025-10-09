<?php
/**
 * Cache-specific exceptions.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Exceptions;

/**
 * Exception thrown during cache operations.
 */
class CacheException extends PluginException {

	/**
	 * Creates an exception for cache write failure.
	 *
	 * @param string $key Cache key that failed.
	 *
	 * @return self
	 */
	public static function write_failed( string $key ): self {
		return new self( sprintf( 'Failed to write cache for key: %s', $key ) );
	}

	/**
	 * Creates an exception for cache invalidation failure.
	 *
	 * @param string $pattern Pattern or key that failed to invalidate.
	 *
	 * @return self
	 */
	public static function invalidation_failed( string $pattern ): self {
		return new self( sprintf( 'Failed to invalidate cache for: %s', $pattern ) );
	}
}
