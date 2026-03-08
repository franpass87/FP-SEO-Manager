<?php
/**
 * Cache Helper for Admin Operations
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Helpers;

/**
 * Helper class for cache operations in admin context
 */
class CacheHelper {

	/**
	 * Clear all caches related to a post's SEO data
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function clear_post_seo_cache( int $post_id ): void {
		// CRITICAL: Cache clearing disabled to prevent interference with featured image (_thumbnail_id)
		// WordPress handles cache management automatically - no manual clearing needed
		// Clearing cache can interfere with WordPress core operations including _thumbnail_id
		// Only clear our specific transient cache, not post_meta cache
		$cache_key = 'fp_seo_schemas_' . $post_id . '_' . get_current_blog_id();
		wp_cache_delete( $cache_key );
	}

	/**
	 * Clear schema-specific cache for a post
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function clear_schema_cache( int $post_id ): void {
		// CRITICAL: Cache clearing disabled to prevent interference with featured image (_thumbnail_id)
		// WordPress handles cache management automatically - no manual clearing needed
		// Clearing cache can interfere with WordPress core operations including _thumbnail_id
		// Only clear our specific transient cache, not post_meta cache
		$cache_key = 'fp_seo_schemas_' . $post_id . '_' . get_current_blog_id();
		wp_cache_delete( $cache_key );
	}
}















