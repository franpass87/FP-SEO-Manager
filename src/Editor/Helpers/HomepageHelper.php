<?php
/**
 * Helper class for homepage-related operations.
 *
 * @package FP\SEO\Editor\Helpers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Helpers;

use function get_option;

/**
 * Helper class for homepage-related operations.
 */
class HomepageHelper {
	/**
	 * Cache for page_on_front ID.
	 *
	 * @var int|null
	 */
	private static ?int $page_on_front_id_cache = null;

	/**
	 * Get the homepage ID (page_on_front).
	 *
	 * @return int Homepage ID, or 0 if not set.
	 */
	public static function get_homepage_id(): int {
		if ( null === self::$page_on_front_id_cache ) {
			self::$page_on_front_id_cache = (int) get_option( 'page_on_front', 0 );
		}
		return self::$page_on_front_id_cache;
	}

	/**
	 * Check if a post ID is the homepage.
	 *
	 * @param int $post_id Post ID to check.
	 * @return bool True if the post is the homepage.
	 */
	public static function is_homepage( int $post_id ): bool {
		$homepage_id = self::get_homepage_id();
		return $homepage_id > 0 && $post_id === $homepage_id;
	}

	/**
	 * Check if homepage is configured.
	 *
	 * @return bool True if homepage is configured.
	 */
	public static function is_homepage_configured(): bool {
		return self::get_homepage_id() > 0;
	}

	/**
	 * Clear the cache (useful when homepage changes).
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$page_on_front_id_cache = null;
	}
}


