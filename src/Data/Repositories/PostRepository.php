<?php
/**
 * Post repository implementation.
 *
 * @package FP\SEO\Data\Repositories
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Data\Repositories;

use FP\SEO\Data\Contracts\PostRepositoryInterface;
use FP\SEO\Data\Repositories\AbstractRepository;
use WP_Post;
use wpdb;

/**
 * Repository for post data access.
 *
 * Provides abstraction over WordPress post functions and direct database access.
 */
class PostRepository extends AbstractRepository implements PostRepositoryInterface {

	/**
	 * Constructor.
	 *
	 * @param wpdb|null $wpdb WordPress database instance. If null, uses global $wpdb.
	 */
	public function __construct( ?wpdb $wpdb = null ) {
		parent::__construct( $wpdb );
	}

	/**
	 * Get post by ID.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post|null Post object or null if not found.
	 */
	public function get( int $post_id ): ?WP_Post {
		$post = get_post( $post_id );

		return $post instanceof WP_Post ? $post : null;
	}

	/**
	 * Get post content directly from database (bypasses cache).
	 *
	 * @param int $post_id Post ID.
	 * @return string|null Post content or null if post not found.
	 */
	public function get_content_from_db( int $post_id ): ?string {
		$content = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT post_content FROM {$this->wpdb->posts} WHERE ID = %d AND post_status != 'inherit'",
				$post_id
			)
		);

		return $content ? (string) $content : null;
	}

	/**
	 * Update post status directly in database.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $status  New post status.
	 * @return bool True on success, false on failure.
	 */
	public function update_status( int $post_id, string $status ): bool {
		$result = $this->wpdb->update(
			$this->wpdb->posts,
			array( 'post_status' => $status ),
			array( 'ID' => $post_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			$this->clear_cache( $post_id );
			return true;
		}

		return false;
	}

	/**
	 * Update post in database.
	 *
	 * @param int   $post_id Post ID.
	 * @param array<string, mixed> $data Post data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update( int $post_id, array $data ): bool {
		// Validate data
		if ( empty( $data ) ) {
			return false;
		}

		// Build format array for wpdb->update
		$formats = array();
		$allowed_fields = array(
			'post_status' => '%s',
			'post_title'  => '%s',
			'post_content' => '%s',
			'post_excerpt' => '%s',
			'post_name'   => '%s',
		);

		$update_data = array();
		foreach ( $data as $field => $value ) {
			if ( isset( $allowed_fields[ $field ] ) ) {
				$update_data[ $field ] = $value;
				$formats[] = $allowed_fields[ $field ];
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $this->wpdb->update(
			$this->wpdb->posts,
			$update_data,
			array( 'ID' => $post_id ),
			$formats,
			array( '%d' )
		);

		if ( $result !== false ) {
			$this->clear_cache( $post_id );
			return true;
		}

		return false;
	}

	/**
	 * Clear post cache.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function clear_cache( int $post_id ): void {
		clean_post_cache( $post_id );
		wp_cache_delete( $post_id, 'posts' );
	}

	/**
	 * Get the database table name.
	 *
	 * @return string Table name with prefix.
	 */
	protected function get_table_name(): string {
		return $this->wpdb->posts;
	}

	/**
	 * Get post type.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null Post type or null if post not found.
	 */
	public function get_post_type( int $post_id ): ?string {
		$post_type = get_post_type( $post_id );
		return $post_type ? (string) $post_type : null;
	}
}


