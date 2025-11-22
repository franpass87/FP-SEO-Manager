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
use function get_post_field;
use function is_string;
use function strip_shortcodes;
use function wp_strip_all_tags;
use function wp_trim_words;

/**
 * Resolves SEO metadata (description, canonical, robots) from post meta.
 */
class MetadataResolver {

	/**
	 * Resolves SEO title for a post.
	 *
	 * Falls back to content text (without shortcodes) if custom meta is not set.
	 *
	 * @param WP_Post|int $post Post object or ID.
	 *
	 * @return string SEO title value.
	 */
	public static function resolve_seo_title( $post ): string {
		$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
		$content = $post instanceof WP_Post ? (string) $post->post_content : (string) get_post_field( 'post_content', $post_id );

		$meta = get_post_meta( $post_id, '_fp_seo_title', true );

		if ( is_string( $meta ) && '' !== trim( $meta ) ) {
			// Decode HTML entities to prevent double encoding
			return wp_specialchars_decode( $meta, ENT_QUOTES );
		}

		// If no meta title, extract from content (without shortcodes and HTML)
		if ( '' !== trim( $content ) ) {
			// Remove shortcodes and HTML tags
			$content_without_shortcodes = strip_shortcodes( $content );
			$content_text = wp_strip_all_tags( $content_without_shortcodes );
			
			// Try to extract H1 first
			if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $content_without_shortcodes, $matches ) ) {
				$h1_text = wp_strip_all_tags( $matches[1] );
				if ( '' !== trim( $h1_text ) ) {
					return trim( $h1_text );
				}
			}
			
			// If no H1, use first words of content (max 60 chars for title)
			$title = wp_trim_words( $content_text, 10, '' );
			if ( mb_strlen( $title ) > 60 && function_exists( 'mb_substr' ) ) {
				$title = mb_substr( $title, 0, 57 ) . '...';
			} elseif ( strlen( $title ) > 60 ) {
				$title = substr( $title, 0, 57 ) . '...';
			}
			
			if ( '' !== trim( $title ) ) {
				return trim( $title );
			}
		}

		// Final fallback to post title
		$post_obj = $post instanceof WP_Post ? $post : get_post( $post_id );
		return $post_obj ? (string) $post_obj->post_title : '';
	}

	/**
	 * Resolves meta description for a post.
	 *
	 * Falls back to content text (without shortcodes) if custom meta is not set.
	 *
	 * @param WP_Post|int $post Post object or ID.
	 *
	 * @return string Meta description value.
	 */
	public static function resolve_meta_description( $post ): string {
		$post_id = $post instanceof WP_Post ? (int) $post->ID : (int) $post;
		$excerpt = $post instanceof WP_Post ? (string) $post->post_excerpt : '';
		$content = $post instanceof WP_Post ? (string) $post->post_content : (string) get_post_field( 'post_content', $post_id );

		$meta = get_post_meta( $post_id, '_fp_seo_meta_description', true );

		if ( is_string( $meta ) && '' !== trim( $meta ) ) {
			// Decode HTML entities to prevent double encoding
			return wp_specialchars_decode( $meta, ENT_QUOTES );
		}

		// Always use content without shortcodes when meta is not set
		if ( '' !== trim( $content ) ) {
			// Remove shortcodes and HTML tags, then trim to 30 words
			$content_without_shortcodes = strip_shortcodes( $content );
			$description = wp_trim_words( wp_strip_all_tags( $content_without_shortcodes ), 30, '' );
			
			if ( '' !== trim( $description ) ) {
				return trim( $description );
			}
		}

		// Fallback to excerpt if content is empty
		if ( '' !== trim( $excerpt ) ) {
			return trim( wp_strip_all_tags( $excerpt ) );
		}

		return '';
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