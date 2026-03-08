<?php
/**
 * Post repository interface.
 *
 * @package FP\SEO\Data\Contracts
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Data\Contracts;

use WP_Post;

/**
 * Interface for post data access.
 */
interface PostRepositoryInterface {
	/**
	 * Get post by ID.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post|null Post object or null if not found.
	 */
	public function get( int $post_id ): ?WP_Post;

	/**
	 * Get post content directly from database (bypasses cache).
	 *
	 * @param int $post_id Post ID.
	 * @return string|null Post content or null if post not found.
	 */
	public function get_content_from_db( int $post_id ): ?string;

	/**
	 * Update post status directly in database.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $status  New post status.
	 * @return bool True on success, false on failure.
	 */
	public function update_status( int $post_id, string $status ): bool;

	/**
	 * Update post in database.
	 *
	 * @param int   $post_id Post ID.
	 * @param array<string, mixed> $data Post data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( int $post_id, array $data ): bool;

	/**
	 * Clear post cache.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function clear_cache( int $post_id ): void;

	/**
	 * Get post type.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null Post type or null if post not found.
	 */
	public function get_post_type( int $post_id ): ?string;
}















