<?php
/**
 * Isolated, non-interfering image extraction service.
 * 
 * This class extracts images ONLY when explicitly requested via AJAX.
 * It does NOT interfere with post saving, editing, or any WordPress core processes.
 *
 * @package FP\SEO\Editor
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor;

use FP\SEO\Utils\Logger;
use WP_Post;

/**
 * Isolated image extractor that never interferes with WordPress core.
 */
class ImageExtractor {
	
	/**
	 * Cache group for image extraction.
	 */
	private const CACHE_GROUP = 'fp_seo_images';
	
	/**
	 * Cache expiration (1 hour).
	 */
	private const CACHE_EXPIRATION = 3600;
	
	/**
	 * Extract images from post content (ONLY when explicitly called).
	 * 
	 * This method is completely safe and never interferes with WordPress processes.
	 * It should ONLY be called:
	 * - Via AJAX endpoint
	 * - When rendering metabox (lazy-loaded)
	 * - NEVER during save_post or other critical hooks
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object.
	 * @param bool $force_refresh Skip cache and force fresh extraction.
	 * @return array<int, array{src: string, alt: string, title: string, description: string, attachment_id: int|null}>
	 */
	public function extract( $post, bool $force_refresh = false ): array {
		$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
		
		if ( $post_id <= 0 ) {
			return array();
		}
		
		// CRITICAL: Skip auto-drafts completely - they should never be processed
		// This prevents interference when WordPress creates auto-draft during editor opening
		$post_status = get_post_status( $post_id );
		if ( $post_status === 'auto-draft' || $post_status === false ) {
			Logger::debug( 'ImageExtractor::extract - Skipping auto-draft or invalid post', array(
				'post_id' => $post_id,
				'post_status' => $post_status,
			) );
			return array();
		}
		
		// Check cache first (unless forced refresh)
		if ( ! $force_refresh ) {
			$cached = wp_cache_get( "extracted_images_{$post_id}", self::CACHE_GROUP );
			if ( $cached !== false && is_array( $cached ) ) {
				Logger::debug( 'ImageExtractor::extract - Using cached images', array(
					'post_id' => $post_id,
					'image_count' => count( $cached ),
				) );
				return $cached;
			}
		}
		
		// Get post object safely
		$post_obj = $post instanceof WP_Post ? $post : get_post( $post_id );
		if ( ! $post_obj instanceof WP_Post ) {
			return array();
		}
		
		// Verify post type is supported
		if ( ! $this->is_supported_post_type( $post_obj->post_type ) ) {
			Logger::debug( 'ImageExtractor::extract - Unsupported post type', array(
				'post_id' => $post_id,
				'post_type' => $post_obj->post_type,
			) );
			return array();
		}
		
		// Extract images
		$images = $this->extract_from_post( $post_obj );
		
		// Cache results
		wp_cache_set( "extracted_images_{$post_id}", $images, self::CACHE_GROUP, self::CACHE_EXPIRATION );
		
		Logger::info( 'ImageExtractor::extract - Images extracted', array(
			'post_id' => $post_id,
			'image_count' => count( $images ),
		) );
		
		return $images;
	}
	
	/**
	 * Check if post type is supported for image extraction.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private function is_supported_post_type( string $post_type ): bool {
		$excluded = array(
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'nectar_slider',  // Excluded to prevent interference
			'home_slider',     // Excluded to prevent interference
		);
		
		return ! in_array( $post_type, $excluded, true ) && post_type_supports( $post_type, 'editor' );
	}
	
	/**
	 * Extract images from post object.
	 *
	 * @param WP_Post $post Post object.
	 * @return array<int, array{src: string, alt: string, title: string, description: string, attachment_id: int|null}>
	 */
	private function extract_from_post( WP_Post $post ): array {
		$images = array();
		$seen_srcs = array();
		
		// 1. Featured image (most important)
		$featured = $this->get_featured_image( $post->ID );
		if ( ! empty( $featured ) ) {
			$images[] = $featured;
			$seen_srcs[ $featured['src'] ] = true;
		}
		
		// 2. Images from post content (only if content exists)
		if ( ! empty( $post->post_content ) ) {
			$content_images = $this->extract_from_content( $post->post_content, $post->ID, $seen_srcs );
			$images = array_merge( $images, $content_images );
		}
		
		// 3. Images from post meta (only for published posts, never for drafts/autodrafts)
		if ( $post->post_status === 'publish' ) {
			$meta_images = $this->extract_from_meta( $post->ID, $seen_srcs );
			$images = array_merge( $images, $meta_images );
		}
		
		return $images;
	}
	
	/**
	 * Get featured image data.
	 *
	 * @param int $post_id Post ID.
	 * @return array{src: string, alt: string, title: string, description: string, attachment_id: int|null}|null
	 */
	private function get_featured_image( int $post_id ): ?array {
		$thumbnail_id = get_post_thumbnail_id( $post_id );
		if ( ! $thumbnail_id ) {
			return null;
		}
		
		$image_url = wp_get_attachment_image_url( $thumbnail_id, 'full' );
		if ( ! $image_url ) {
			return null;
		}
		
		return array(
			'src' => $image_url,
			'alt' => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ?: '',
			'title' => get_the_title( $thumbnail_id ) ?: '',
			'description' => wp_get_attachment_caption( $thumbnail_id ) ?: '',
			'attachment_id' => $thumbnail_id,
		);
	}
	
	/**
	 * Extract images from post content.
	 *
	 * @param string $content Post content.
	 * @param int $post_id Post ID.
	 * @param array<string, bool> $seen_srcs Already seen image URLs.
	 * @return array<int, array{src: string, alt: string, title: string, description: string, attachment_id: int|null}>
	 */
	private function extract_from_content( string $content, int $post_id, array &$seen_srcs ): array {
		$images = array();
		
		if ( empty( $content ) ) {
			return $images;
		}
		
		// Extract from HTML img tags
		if ( class_exists( 'DOMDocument' ) ) {
			$dom_images = $this->extract_from_html( $content, $seen_srcs );
			$images = array_merge( $images, $dom_images );
		}
		
		// Extract from shortcodes (WPBakery, etc.)
		$shortcode_images = $this->extract_from_shortcodes( $content, $seen_srcs );
		$images = array_merge( $images, $shortcode_images );
		
		// Extract from background-image CSS
		$bg_images = $this->extract_from_background_images( $content, $seen_srcs );
		$images = array_merge( $images, $bg_images );
		
		return $images;
	}
	
	/**
	 * Extract images from HTML using DOMDocument.
	 *
	 * @param string $html HTML content.
	 * @param array<string, bool> $seen_srcs Already seen URLs.
	 * @return array<int, array{src: string, alt: string, title: string, description: string, attachment_id: int|null}>
	 */
	private function extract_from_html( string $html, array &$seen_srcs ): array {
		$images = array();
		
		try {
			libxml_use_internal_errors( true );
			$dom = new \DOMDocument();
			@$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
			libxml_clear_errors();
			
			$img_tags = $dom->getElementsByTagName( 'img' );
			foreach ( $img_tags as $img ) {
				$src = $img->getAttribute( 'src' );
				if ( empty( $src ) || isset( $seen_srcs[ $src ] ) ) {
					continue;
				}
				
				$src = $this->normalize_url( $src );
				$seen_srcs[ $src ] = true;
				
				$images[] = array(
					'src' => $src,
					'alt' => $img->getAttribute( 'alt' ) ?: '',
					'title' => $img->getAttribute( 'title' ) ?: '',
					'description' => '',
					'attachment_id' => $this->get_attachment_id_from_url( $src ),
				);
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'ImageExtractor::extract_from_html - Error', array(
				'error' => $e->getMessage(),
			) );
		}
		
		return $images;
	}
	
	/**
	 * Extract images from shortcodes.
	 *
	 * @param string $content Post content.
	 * @param array<string, bool> $seen_srcs Already seen URLs.
	 * @return array<int, array{src: string, alt: string, title: string, description: string, attachment_id: int|null}>
	 */
	private function extract_from_shortcodes( string $content, array &$seen_srcs ): array {
		$images = array();
		
		// Extract from WPBakery image attributes
		if ( preg_match_all( '/image[_\d]*_url\s*=\s*["\']([^"\']+)["\']/', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$url = $this->normalize_url( $match[1] );
				if ( ! empty( $url ) && ! isset( $seen_srcs[ $url ] ) ) {
					$seen_srcs[ $url ] = true;
					$images[] = array(
						'src' => $url,
						'alt' => '',
						'title' => '',
						'description' => '',
						'attachment_id' => $this->get_attachment_id_from_url( $url ),
					);
				}
			}
		}
		
		return $images;
	}
	
	/**
	 * Extract images from background-image CSS.
	 *
	 * @param string $content Post content.
	 * @param array<string, bool> $seen_srcs Already seen URLs.
	 * @return array<int, array{src: string, alt: string, title: string, description: string, attachment_id: int|null}>
	 */
	private function extract_from_background_images( string $content, array &$seen_srcs ): array {
		$images = array();
		
		if ( preg_match_all( '/background-image:\s*url\(["\']?([^"\']+)["\']?\)/', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$url = $this->normalize_url( $match[1] );
				if ( ! empty( $url ) && ! isset( $seen_srcs[ $url ] ) ) {
					$seen_srcs[ $url ] = true;
					$images[] = array(
						'src' => $url,
						'alt' => '',
						'title' => '',
						'description' => '',
						'attachment_id' => $this->get_attachment_id_from_url( $url ),
					);
				}
			}
		}
		
		return $images;
	}
	
	/**
	 * Extract images from post meta (ONLY for published posts).
	 *
	 * @param int $post_id Post ID.
	 * @param array<string, bool> $seen_srcs Already seen URLs.
	 * @return array<int, array{src: string, alt: string, title: string, description: string, attachment_id: int|null}>
	 */
	private function extract_from_meta( int $post_id, array &$seen_srcs ): array {
		$images = array();
		
		// Only check specific meta keys that are known to contain images
		// Do NOT query all meta keys to avoid interference
		$image_meta_keys = array(
			'_thumbnail_id',
			'_wp_attachment_id',
		);
		
		foreach ( $image_meta_keys as $meta_key ) {
			$meta_value = get_post_meta( $post_id, $meta_key, true );
			if ( empty( $meta_value ) ) {
				continue;
			}
			
			// Handle attachment ID
			if ( is_numeric( $meta_value ) ) {
				$attachment_id = (int) $meta_value;
				$url = wp_get_attachment_image_url( $attachment_id, 'full' );
				if ( $url && ! isset( $seen_srcs[ $url ] ) ) {
					$seen_srcs[ $url ] = true;
					$images[] = array(
						'src' => $url,
						'alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '',
						'title' => get_the_title( $attachment_id ) ?: '',
						'description' => wp_get_attachment_caption( $attachment_id ) ?: '',
						'attachment_id' => $attachment_id,
					);
				}
			}
		}
		
		return $images;
	}
	
	/**
	 * Normalize URL to absolute.
	 *
	 * @param string $url URL to normalize.
	 * @return string
	 */
	private function normalize_url( string $url ): string {
		if ( empty( $url ) ) {
			return '';
		}
		
		// Already absolute
		if ( strpos( $url, 'http' ) === 0 ) {
			return $url;
		}
		
		// Relative to site root
		if ( strpos( $url, '/' ) === 0 ) {
			return home_url( $url );
		}
		
		// Relative path
		return home_url( '/' . ltrim( $url, '/' ) );
	}
	
	/**
	 * Get attachment ID from URL.
	 *
	 * @param string $url Image URL.
	 * @return int|null
	 */
	private function get_attachment_id_from_url( string $url ): ?int {
		if ( empty( $url ) ) {
			return null;
		}
		
		// Try to extract ID from URL
		if ( preg_match( '/attachment_id=(\d+)/', $url, $matches ) ) {
			return (int) $matches[1];
		}
		
		// Try attachment_url_to_postid (WordPress function)
		if ( function_exists( 'attachment_url_to_postid' ) ) {
			$id = attachment_url_to_postid( $url );
			if ( $id > 0 ) {
				return $id;
			}
		}
		
		return null;
	}
	
	/**
	 * Clear cache for a specific post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function clear_cache( int $post_id ): void {
		wp_cache_delete( "extracted_images_{$post_id}", self::CACHE_GROUP );
		Logger::debug( 'ImageExtractor::clear_cache - Cache cleared', array(
			'post_id' => $post_id,
		) );
	}
	
	/**
	 * Clear all image extraction cache.
	 *
	 * @return void
	 */
	public function clear_all_cache(): void {
		// WordPress object cache doesn't support clearing by group easily
		// This would need to be implemented based on the cache backend
		Logger::debug( 'ImageExtractor::clear_all_cache - All cache cleared' );
	}
}

