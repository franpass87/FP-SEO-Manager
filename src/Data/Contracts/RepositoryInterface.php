<?php
/**
 * Repository interface - base contract for all repositories.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Data\Contracts;

/**
 * Base repository interface.
 *
 * All repositories should implement this interface to ensure consistency.
 */
interface RepositoryInterface {

	/**
	 * Get an entity by ID.
	 *
	 * @param int $id Entity ID.
	 * @return object|null Entity instance or null if not found.
	 */
	public function get( int $id ): ?object;

	/**
	 * Check if an entity exists.
	 *
	 * @param int $id Entity ID.
	 * @return bool True if exists, false otherwise.
	 */
	public function exists( int $id ): bool;

	/**
	 * Clear any cached data for an entity.
	 *
	 * @param int $id Entity ID.
	 * @return void
	 */
	public function clear_cache( int $id ): void;
}



