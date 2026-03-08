<?php
/**
 * Abstract repository base class.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Data\Repositories;

use FP\SEO\Data\Contracts\RepositoryInterface;
use wpdb;

/**
 * Abstract base class for repositories.
 *
 * Provides common functionality for all repositories.
 */
abstract class AbstractRepository implements RepositoryInterface {

	/**
	 * WordPress database instance.
	 *
	 * @var wpdb
	 */
	protected wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $wpdb WordPress database instance. If null, uses global $wpdb.
	 */
	public function __construct( ?wpdb $wpdb = null ) {
		$this->wpdb = $wpdb ?? $GLOBALS['wpdb'];
	}

	/**
	 * Check if an entity exists.
	 *
	 * @param int $id Entity ID.
	 * @return bool True if exists, false otherwise.
	 */
	public function exists( int $id ): bool {
		return null !== $this->get( $id );
	}

	/**
	 * Clear any cached data for an entity.
	 *
	 * @param int $id Entity ID.
	 * @return void
	 */
	public function clear_cache( int $id ): void {
		// Default implementation does nothing.
		// Subclasses can override to implement caching.
	}

	/**
	 * Get the database table name.
	 *
	 * @return string Table name with prefix.
	 */
	abstract protected function get_table_name(): string;

	/**
	 * Get the primary key column name.
	 *
	 * @return string Primary key column name.
	 */
	protected function get_primary_key(): string {
		return 'ID';
	}
}



