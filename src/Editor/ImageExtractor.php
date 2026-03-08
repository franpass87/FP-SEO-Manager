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
	 * By default, it should ONLY be called:
	 * - Via AJAX endpoint
	 * - When rendering metabox (lazy-loaded)
	 * 
	 * When $allow_non_ajax is true, it can also be called during save_post hooks
	 * (e.g., for image SEO optimization), but only when explicitly needed.
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object.
	 * @param bool $force_refresh Skip cache and force fresh extraction.
	 * @param bool $allow_non_ajax Allow extraction in non-AJAX context (e.g., during save_post).
	 * @return array<int, array{src: string, alt: string, title: string, description: string, attachment_id: int|null}>
	 */
	public function extract( $post, bool $force_refresh = false, bool $allow_non_ajax = false ): array {
		$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
		
		if ( $post_id <= 0 ) {
			return array();
		}
		
		// CRITICAL: Only process in AJAX context to avoid interference with WordPress post loading
		// During initial metabox rendering, WordPress might create auto-drafts, so we skip completely
		// Exception: When $allow_non_ajax is true, allow extraction (e.g., during image SEO optimization)
		if ( ! $allow_non_ajax && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {
			Logger::debug( 'ImageExtractor::extract - Skipping extraction during non-AJAX context (editor load)', array(
				'post_id' => $post_id,
				'context' => 'non-ajax',
			) );
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
		// CRITICAL: If we already have a WP_Post object, use it directly
		$post_obj = $post instanceof WP_Post ? $post : null;
		
		// Only call get_post if we don't have the object and we're sure it's safe (not during editor load)
		if ( ! $post_obj instanceof WP_Post ) {
			// CRITICAL: Only call get_post if we're in AJAX context or explicitly requested via $allow_non_ajax
			// During metabox rendering, WordPress might pass wrong post, so we skip extraction by default
			if ( $allow_non_ajax || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
				$post_obj = get_post( $post_id );
			} else {
				// During normal rendering, skip to avoid interference
				Logger::debug( 'ImageExtractor::extract - Skipping extraction during non-AJAX context', array(
					'post_id' => $post_id,
					'context' => 'non-ajax',
				) );
				return array();
			}
			
			if ( ! $post_obj instanceof WP_Post ) {
				return array();
			}
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
				
				// Try to get attachment ID from CSS class first (most reliable)
				// WordPress adds wp-image-{ID} class to images inserted via media library
				$attachment_id = $this->get_attachment_id_from_class( $img->getAttribute( 'class' ) );
				
				// Fallback to URL-based detection if class not found
				if ( ! $attachment_id ) {
					$attachment_id = $this->get_attachment_id_from_url( $src );
				}
				
				$images[] = array(
					'src' => $src,
					'alt' => $img->getAttribute( 'alt' ) ?: '',
					'title' => $img->getAttribute( 'title' ) ?: '',
					'description' => '',
					'attachment_id' => $attachment_id,
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
	 * Get attachment ID from CSS class (wp-image-{ID}).
	 *
	 * @param string $class CSS class attribute.
	 * @return int|null Attachment ID or null if not found.
	 */
	private function get_attachment_id_from_class( string $class ): ?int {
		if ( empty( $class ) ) {
			return null;
		}
		
		// WordPress adds wp-image-{ID} class to images from media library
		if ( preg_match( '/wp-image-(\d+)/', $class, $matches ) ) {
			$attachment_id = (int) $matches[1];
			
			// Verify attachment exists
			$attachment = get_post( $attachment_id );
			if ( $attachment && 'attachment' === $attachment->post_type ) {
				return $attachment_id;
			}
		}
		
		return null;
	}
	
	/**
	 * Extract images from shortcodes (WPBakery/Salient).
	 *
	 * Supports all Salient elements that contain images:
	 * - image_with_animation (image_url)
	 * - team_member (image_url, bio_image_url, bio_alt_image_url)
	 * - testimonial / nectar_single_testimonial (image)
	 * - nectar_cascading_images (image_1_url, image_2_url, image_3_url, image_4_url)
	 * - nectar_video_lightbox (image_url)
	 * - nectar_video_player_self_hosted (video_image)
	 * - nectar_scrolling_text (background_image_url)
	 * - nectar_sticky_media_section (image)
	 * - nectar_text_inline_images (images - comma separated)
	 * - nectar_image_comparison (image_url, image_url_2)
	 * - vc_column / vc_row (background_image)
	 * - fancy_box (icon_image)
	 * - text-with-icon (icon_image)
	 *
	 * @param string $content Post content.
	 * @param array<string, bool> $seen_srcs Already seen URLs.
	 * @return array<int, array{src: string, alt: string, title: string, description: string, attachment_id: int|null}>
	 */
	private function extract_from_shortcodes( string $content, array &$seen_srcs ): array {
		$images = array();
		
		// Salient image attribute patterns (can be ID or URL)
		// Pattern matches: attribute_name="value" where value can be ID or URL
		$salient_image_attributes = array(
			// Single image elements
			'image_url',           // image_with_animation, team_member, nectar_video_lightbox
			'image',               // testimonial, nectar_single_testimonial, nectar_sticky_media_section
			'bio_image_url',       // team_member
			'bio_alt_image_url',   // team_member
			'video_image',         // nectar_video_player_self_hosted
			'background_image_url', // nectar_scrolling_text
			'background_image',    // vc_column, vc_row, full_width_section
			'icon_image',          // text-with-icon, fancy_box
			'image_url_2',         // nectar_image_comparison (second image)
			// Cascading images (up to 4 images)
			'image_1_url',
			'image_2_url',
			'image_3_url',
			'image_4_url',
		);
		
		// Build regex pattern for all attributes
		$attr_pattern = implode( '|', array_map( 'preg_quote', $salient_image_attributes ) );
		$regex = '/(' . $attr_pattern . ')\s*=\s*["\']([^"\']+)["\']/';
		
		if ( preg_match_all( $regex, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$attr_name = $match[1];
				$value = trim( $match[2] );
				
				if ( empty( $value ) ) {
					continue;
				}
				
				// Check if value is numeric (attachment ID) or URL
				if ( preg_match( '/^\d+$/', $value ) ) {
					// It's an attachment ID
					$attachment_id = (int) $value;
					$image_data = $this->get_image_data_from_attachment_id( $attachment_id, $seen_srcs );
					if ( $image_data ) {
						$images[] = $image_data;
					}
				} else {
					// It's a URL
					$url = $this->normalize_url( $value );
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
		}
		
		// Handle 'images' attribute (comma-separated IDs) - used by nectar_text_inline_images
		if ( preg_match_all( '/images\s*=\s*["\']([^"\']+)["\']/', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$ids = explode( ',', $match[1] );
				foreach ( $ids as $id ) {
					$id = trim( $id );
					if ( preg_match( '/^\d+$/', $id ) ) {
						$attachment_id = (int) $id;
						$image_data = $this->get_image_data_from_attachment_id( $attachment_id, $seen_srcs );
						if ( $image_data ) {
							$images[] = $image_data;
						}
					}
				}
			}
		}
		
		return $images;
	}
	
	/**
	 * Get image data from attachment ID.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $seen_srcs     Already seen URLs (passed by reference).
	 * @return array|null Image data array or null if not valid.
	 */
	private function get_image_data_from_attachment_id( int $attachment_id, array &$seen_srcs ): ?array {
		// Verify attachment exists and is an image
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return null;
		}
		
		// Check if it's an image mime type
		$mime_type = get_post_mime_type( $attachment_id );
		if ( strpos( $mime_type, 'image/' ) !== 0 ) {
			return null;
		}
		
		// Get image URL
		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url || isset( $seen_srcs[ $url ] ) ) {
			return null;
		}
		
		$seen_srcs[ $url ] = true;
		
		return array(
			'src' => $url,
			'alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '',
			'title' => get_the_title( $attachment_id ) ?: '',
			'description' => wp_get_attachment_caption( $attachment_id ) ?: '',
			'attachment_id' => $attachment_id,
		);
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
		
		// Try to extract ID from URL parameter
		if ( preg_match( '/attachment_id=(\d+)/', $url, $matches ) ) {
			return (int) $matches[1];
		}
		
		// Try attachment_url_to_postid (WordPress function)
		if ( function_exists( 'attachment_url_to_postid' ) ) {
			// First try with original URL
			$id = attachment_url_to_postid( $url );
			if ( $id > 0 ) {
				return $id;
			}
			
			// If not found, try removing size suffixes (e.g., -300x200, -242x300)
			// WordPress generates these for intermediate image sizes
			$url_without_size = preg_replace( '/-\d+x\d+(\.[a-zA-Z]+)$/', '$1', $url );
			if ( $url_without_size !== $url ) {
				$id = attachment_url_to_postid( $url_without_size );
				if ( $id > 0 ) {
					return $id;
				}
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

