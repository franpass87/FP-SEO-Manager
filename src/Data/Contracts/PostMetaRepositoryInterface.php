<?php
/**
 * Post meta repository interface.
 *
 * @package FP\SEO\Data\Contracts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Data\Contracts;

/**
 * Interface for post meta data access.
 */
interface PostMetaRepositoryInterface {
	/**
	 * Get post meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Return single value (default true).
	 * @return mixed Meta value or array of values.
	 */
	public function get( int $post_id, string $key, bool $single = true );

	/**
	 * Update post meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return bool True on success, false on failure.
	 */
	public function update( int $post_id, string $key, $value ): bool;

	/**
	 * Delete post meta value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return bool True on success, false on failure.
	 */
	public function delete( int $post_id, string $key ): bool;

	/**
	 * Get all meta for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Array of meta key => value pairs.
	 */
	public function get_all( int $post_id ): array;

	/**
	 * Get post meta value directly from database (bypasses cache).
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return mixed Meta value or null if not found.
	 */
	public function get_from_db( int $post_id, string $key );

	/**
	 * Get post status directly from database.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null Post status or null if post not found.
	 */
	public function get_post_status( int $post_id ): ?string;
}















