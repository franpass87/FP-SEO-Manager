<?php
/**
 * State management service for metabox.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Infrastructure\Contracts\LoggerInterface;

/**
 * Manages metabox state and prevents duplicate processing.
 */
class MetaboxStateManager {

	/**
	 * Static tracking to prevent duplicate processing.
	 *
	 * @var array<int, bool>
	 */
	private static array $processed_posts = array();

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @param LoggerInterface $logger Logger instance.
	 */
	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Check if post has already been processed.
	 *
	 * @param int $post_id Post ID.
	 * @return bool True if already processed.
	 */
	public function is_processed( int $post_id ): bool {
		return isset( self::$processed_posts[ $post_id ] );
	}

	/**
	 * Mark post as processed.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function mark_processed( int $post_id ): void {
		self::$processed_posts[ $post_id ] = true;
	}

	/**
	 * Clear processed state for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function clear_processed( int $post_id ): void {
		unset( self::$processed_posts[ $post_id ] );
	}

	/**
	 * Clear all processed states.
	 *
	 * @return void
	 */
	public function clear_all(): void {
		self::$processed_posts = array();
	}

	/**
	 * Get processed posts count.
	 *
	 * @return int Count of processed posts.
	 */
	public function get_processed_count(): int {
		return count( self::$processed_posts );
	}
}




