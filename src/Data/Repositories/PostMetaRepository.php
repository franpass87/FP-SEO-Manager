<?php
/**
 * Post meta repository implementation.
 *
 * @package FP\SEO\Data\Repositories
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Data\Repositories;

use FP\SEO\Data\Contracts\PostMetaRepositoryInterface;
use FP\SEO\Data\Repositories\AbstractRepository;
use wpdb;

/**
 * Repository for post meta data access.
 *
 * Provides abstraction over WordPress post meta functions and direct database access.
 */
class PostMetaRepository implements PostMetaRepositoryInterface {

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
	 * Get post meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Return single value (default true).
	 * @return mixed Meta value or array of values.
	 */
	public function get( int $post_id, string $key, bool $single = true ) {
		return get_post_meta( $post_id, $key, $single );
	}

	/**
	 * Update post meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return bool True on success, false on failure.
	 */
	public function update( int $post_id, string $key, $value ): bool {
		return (bool) update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Delete post meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $post_id, string $key ): bool {
		return delete_post_meta( $post_id, $key );
	}

	/**
	 * Get all meta for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Array of meta key => value pairs.
	 */
	public function get_all( int $post_id ): array {
		$meta = get_post_meta( $post_id );
		if ( ! is_array( $meta ) ) {
			return array();
		}

		// Flatten array if single values
		$result = array();
		foreach ( $meta as $key => $value ) {
			$result[ $key ] = is_array( $value ) && count( $value ) === 1 ? $value[0] : $value;
		}

		return $result;
	}

	/**
	 * Get post meta value directly from database (bypasses cache).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return mixed Meta value or null if not found.
	 */
	public function get_from_db( int $post_id, string $key ) {
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT meta_value FROM {$this->wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$post_id,
				$key
			)
		);

		if ( $result === null ) {
			return null;
		}

		return maybe_unserialize( $result );
	}

	/**
	 * Get post status directly from database.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null Post status or null if post not found.
	 */
	public function get_post_status( int $post_id ): ?string {
		$status = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT post_status FROM {$this->wpdb->posts} WHERE ID = %d",
				$post_id
			)
		);

		return $status ? (string) $status : null;
	}
}

