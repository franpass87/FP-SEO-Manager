<?php
/**
 * SEO Metadata resolver utility.
 *
 * Centralizes the logic for resolving SEO metadata from posts.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use WP_Post;
use function get_post_meta;
use function is_string;
use function wp_strip_all_tags;

/**
 * Resolves SEO metadata (description, canonical, robots) from post meta.
 */
class MetadataResolver {

	/**
	 * Resolves meta description for a post.
	 *
	 * Falls back to post excerpt if custom meta is not set.
	 *
	 * @param WP_Post|int $post Post object or ID.
	 *
	 * @return string Meta description value.
	 */
	public static function resolve_meta_description( $post ): string {
		$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
		$excerpt = $post instanceof WP_Post ? (string) $post->post_excerpt : '';

		$meta = get_post_meta( $post_id, '_fp_seo_meta_description', true );

		if ( is_string( $meta ) && '' !== $meta ) {
			return $meta;
		}

		return wp_strip_all_tags( $excerpt );
	}

	/**
	 * Resolves canonical URL metadata for a post.
	 *
	 * Returns null if not set.
	 *
	 * @param WP_Post|int $post Post object or ID.
	 *
	 * @return string|null Canonical URL or null if not set.
	 */
	public static function resolve_canonical_url( $post ): ?string {
		$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;

		$canonical = get_post_meta( $post_id, '_fp_seo_meta_canonical', true );

		if ( is_string( $canonical ) && '' !== $canonical ) {
			return $canonical;
		}

		return null;
	}

	/**
	 * Resolves robots directives metadata for a post.
	 *
	 * Returns null if not set.
	 *
	 * @param WP_Post|int $post Post object or ID.
	 *
	 * @return string|null Robots directive or null if not set.
	 */
	public static function resolve_robots( $post ): ?string {
		$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;

		$robots = get_post_meta( $post_id, '_fp_seo_meta_robots', true );

		if ( is_string( $robots ) && '' !== $robots ) {
			return $robots;
		}

		return null;
	}
}