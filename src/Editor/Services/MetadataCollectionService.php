<?php
/**
 * Service for collecting SEO metadata from posts.
 *
 * @package FP\SEO\Editor\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Editor\Metabox;
use FP\SEO\Utils\MetadataResolver;
use WP_Post;
use function get_post_meta;
use function maybe_unserialize;

/**
 * Service for collecting SEO metadata from posts.
 */
class MetadataCollectionService {

	/**
	 * Collect SEO metadata for a post.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Array with keys: meta_description, canonical, robots, focus_keyword, secondary_keywords, seo_title.
	 */
	public function collect_metadata( WP_Post $post ): array {
		// Get SEO metadata using MetadataResolver (same pattern as BulkAuditPage)
		$meta_description = MetadataResolver::resolve_meta_description( $post );
		$canonical = MetadataResolver::resolve_canonical_url( $post );
		$robots = MetadataResolver::resolve_robots( $post );
		$focus_keyword = get_post_meta( $post->ID, Metabox::META_FOCUS_KEYWORD, true );
		$secondary_keywords = get_post_meta( $post->ID, Metabox::META_SECONDARY_KEYWORDS, true );
		
		// Fallback: query diretta al database se get_post_meta restituisce vuoto
		if ( empty( $focus_keyword ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$post->ID,
				Metabox::META_FOCUS_KEYWORD
			) );
			if ( $db_value !== null ) {
				$focus_keyword = $db_value;
			}
		}
		
		if ( empty( $secondary_keywords ) ) {
			global $wpdb;
			$db_value = $wpdb->get_var( $wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$post->ID,
				Metabox::META_SECONDARY_KEYWORDS
			) );
			if ( $db_value !== null ) {
				$unserialized = maybe_unserialize( $db_value );
				$secondary_keywords = is_array( $unserialized ) ? $unserialized : array();
			}
		}
		
		if ( ! is_array( $secondary_keywords ) ) {
			$secondary_keywords = array();
		}
		
		// Get SEO title, fallback to post title
		$seo_title = MetadataResolver::resolve_seo_title( $post->ID );
		if ( ! $seo_title ) {
			$seo_title = $post->post_title;
		}

		return array(
			'meta_description' => (string) $meta_description,
			'canonical' => $canonical,
			'robots' => $robots,
			'focus_keyword' => is_string( $focus_keyword ) ? $focus_keyword : '',
			'secondary_keywords' => $secondary_keywords,
			'seo_title' => (string) $seo_title,
		);
	}
}








