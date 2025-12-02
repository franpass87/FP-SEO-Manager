<?php
/**
 * Service for extracting images from post content.
 *
 * @package FP\SEO\Editor\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Utils\Logger;
use WP_Post;
use function apply_filters;
use function content_url;
use function do_shortcode;
use function get_post;
use function get_post_meta;
use function site_url;
use function strlen;
use function strpos;
use function substr;
use function substr_count;

/**
 * Service for extracting images from post content.
 */
class ImageExtractionService {
	/**
	 * Extract images from post content.
	 *
	 * @param WP_Post $post Post object.
	 * @return array Array of image data.
	 */
	public function extract_from_content( WP_Post $post ): array {
		// Get content from database to ensure we have the latest version
		$content = $this->get_post_content( $post );
		
		if ( empty( $content ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'ImageExtractionService - Content is empty', array( 'post_id' => $post->ID ) );
			}
			return array();
		}

		$images = array();
		$seen_srcs = array(); // Avoid duplicates

		// Log extraction start
		$this->log_extraction_start( $post, $content );

		// Extract from WPBakery shortcodes
		$images = $this->extract_wpbakery_images( $content, $post->ID, $images, $seen_srcs );

		// Process shortcodes and extract from HTML
		$processed_content = $this->process_shortcodes( $content, $post );
		$content_to_parse = $this->prepare_content_for_parsing( $content, $processed_content, $post );

		// Extract from HTML img tags
		$images = $this->extract_from_html_tags( $content_to_parse, $post, $images, $seen_srcs );

		// Extract from background images and data attributes
		if ( ! empty( $processed_content ) && $processed_content !== $content ) {
			$images = $this->extract_from_background_images( $processed_content, $post, $images, $seen_srcs );
		}

		Logger::info( 'FP SEO: ImageExtractionService - Extraction completed', array(
			'post_id' => $post->ID,
			'total_images' => count( $images ),
		) );

		return $images;
	}

	/**
	 * Get post content from database.
	 *
	 * @param WP_Post $post Post object.
	 * @return string Post content.
	 */
	private function get_post_content( WP_Post $post ): string {
		if ( empty( $post->ID ) || $post->ID <= 0 ) {
			return $post->post_content ?? '';
		}

		global $wpdb;
		$db_content = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
			$post->ID
		) );

		if ( ! empty( $db_content ) ) {
			Logger::info( 'FP SEO: ImageExtractionService - Retrieved content from database', array(
				'post_id' => $post->ID,
				'content_length' => strlen( $db_content ),
				'has_wpbakery' => strpos( $db_content, '[vc_' ) !== false,
				'has_img_tags' => strpos( $db_content, '<img' ) !== false,
			) );
			return $db_content;
		}

		Logger::warning( 'FP SEO: ImageExtractionService - Database content is empty, using post object', array(
			'post_id' => $post->ID,
			'post_type' => $post->post_type ?? 'unknown',
			'post_status' => $post->post_status ?? 'unknown',
		) );

		return $post->post_content ?? '';
	}

	/**
	 * Log extraction start.
	 *
	 * @param WP_Post $post    Post object.
	 * @param string  $content Post content.
	 * @return void
	 */
	private function log_extraction_start( WP_Post $post, string $content ): void {
		Logger::info( 'FP SEO: ImageExtractionService - Starting extraction', array(
			'post_id' => $post->ID,
			'content_length' => strlen( $content ),
			'content_preview' => substr( $content, 0, 500 ),
			'has_wpbakery' => strpos( $content, '[vc_' ) !== false,
			'has_img_tags' => strpos( $content, '<img' ) !== false,
			'has_img_shortcode' => strpos( $content, '[img' ) !== false || strpos( $content, '[image' ) !== false,
		) );
	}

	/**
	 * Extract images from WPBakery shortcodes.
	 *
	 * @param string $content   Post content.
	 * @param int    $post_id   Post ID.
	 * @param array  $images    Existing images array.
	 * @param array  $seen_srcs Seen sources to avoid duplicates.
	 * @return array Updated images array.
	 */
	private function extract_wpbakery_images( string $content, int $post_id, array $images, array &$seen_srcs ): array {
		$has_wpbakery = strpos( $content, '[vc_' ) !== false 
			|| strpos( $content, '[vc_row' ) !== false
			|| strpos( $content, '[vc_column' ) !== false
			|| strpos( $content, 'vc_single_image' ) !== false
			|| strpos( $content, 'vc_gallery' ) !== false;

		if ( ! $has_wpbakery ) {
			return $images;
		}

		try {
			$wpbakery_extractor = new WPBakeryImageExtractor();
			$wpbakery_images = $wpbakery_extractor->extract( $content, $post_id );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				Logger::debug( 'ImageExtractionService - WPBakery images found', array(
					'post_id' => $post_id,
					'count' => count( $wpbakery_images ),
				) );
			}

			foreach ( $wpbakery_images as $image ) {
				if ( ! empty( $image['src'] ) && ! isset( $seen_srcs[ $image['src'] ] ) ) {
					$seen_srcs[ $image['src'] ] = true;
					$images[] = $image;
				}
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'FP SEO: Error extracting WPBakery images', array(
				'error' => $e->getMessage(),
				'post_id' => $post_id,
			) );
		}

		return $images;
	}

	/**
	 * Process shortcodes in content.
	 *
	 * @param string  $content Post content.
	 * @param WP_Post $post    Post object.
	 * @return string Processed content.
	 */
	private function process_shortcodes( string $content, WP_Post $post ): string {
		$processed_content = do_shortcode( $content );

		Logger::info( 'FP SEO: ImageExtractionService - After do_shortcode', array(
			'post_id' => $post->ID,
			'original_length' => strlen( $content ),
			'processed_length' => strlen( $processed_content ),
			'has_img_in_processed' => strpos( $processed_content, '<img' ) !== false,
		) );

		// If WPBakery is active, use the_content filter
		$has_wpbakery = strpos( $content, '[vc_' ) !== false;
		if ( $has_wpbakery ) {
			$processed_content = apply_filters( 'the_content', $content );

			Logger::info( 'FP SEO: ImageExtractionService - After the_content filter', array(
				'post_id' => $post->ID,
				'processed_length' => strlen( $processed_content ),
				'has_img_in_processed' => strpos( $processed_content, '<img' ) !== false,
			) );
		}

		return $processed_content;
	}

	/**
	 * Prepare content for parsing.
	 *
	 * @param string  $content           Original content.
	 * @param string  $processed_content Processed content.
	 * @param WP_Post $post              Post object.
	 * @return string Content ready for parsing.
	 */
	private function prepare_content_for_parsing( string $content, string $processed_content, WP_Post $post ): string {
		$content_to_parse = $processed_content;
		if ( $processed_content !== $content ) {
			$content_to_parse = $processed_content . "\n" . $content;
		}

		if ( empty( $content_to_parse ) ) {
			Logger::warning( 'FP SEO: ImageExtractionService - Content to parse is empty', array(
				'post_id' => $post->ID,
				'original_content_length' => strlen( $content ),
				'processed_content_length' => strlen( $processed_content ),
			) );
		}

		Logger::info( 'FP SEO: ImageExtractionService - Content prepared for parsing', array(
			'post_id' => $post->ID,
			'content_to_parse_length' => strlen( $content_to_parse ),
			'has_img_in_content' => strpos( $content_to_parse, '<img' ) !== false,
			'img_count' => substr_count( $content_to_parse, '<img' ),
		) );

		return $content_to_parse;
	}

	/**
	 * Extract images from HTML img tags.
	 *
	 * @param string  $content  Content to parse.
	 * @param WP_Post $post     Post object.
	 * @param array   $images   Existing images array.
	 * @param array   $seen_srcs Seen sources to avoid duplicates.
	 * @return array Updated images array.
	 */
	private function extract_from_html_tags( string $content, WP_Post $post, array $images, array &$seen_srcs ): array {
		try {
			$dom = new \DOMDocument();
			libxml_use_internal_errors( true );
			$dom->loadHTML( '<?xml encoding="UTF-8">' . $content );
			libxml_clear_errors();
		} catch ( \Throwable $e ) {
			Logger::error( 'FP SEO: Error parsing HTML for image extraction', array(
				'error' => $e->getMessage(),
				'post_id' => $post->ID,
			) );
			return $images;
		}

		$img_tags = $dom->getElementsByTagName( 'img' );
		$total_img_tags = $img_tags->length;

		Logger::info( 'FP SEO: ImageExtractionService - Found img tags in DOM', array(
			'post_id' => $post->ID,
			'total_img_tags' => $total_img_tags,
		) );

		$html_extractor = new HtmlImageExtractor();
		foreach ( $img_tags as $index => $img ) {
			try {
				$image_data = $html_extractor->extract_from_tag( $img, $post, $seen_srcs );
				if ( $image_data ) {
					$images[] = $image_data;
				}
			} catch ( \Throwable $e ) {
				Logger::error( 'FP SEO: Error processing image tag', array(
					'error' => $e->getMessage(),
					'post_id' => $post->ID,
					'image_index' => $index,
				) );
			}
		}

		return $images;
	}

	/**
	 * Extract images from background-image CSS and data attributes.
	 *
	 * @param string  $content   Processed content.
	 * @param WP_Post $post      Post object.
	 * @param array   $images    Existing images array.
	 * @param array   $seen_srcs Seen sources to avoid duplicates.
	 * @return array Updated images array.
	 */
	private function extract_from_background_images( string $content, WP_Post $post, array $images, array &$seen_srcs ): array {
		try {
			$dom = new \DOMDocument();
			libxml_use_internal_errors( true );
			$dom->loadHTML( '<?xml encoding="UTF-8">' . $content );
			libxml_clear_errors();

			$bg_extractor = new BackgroundImageExtractor();
			$bg_images = $bg_extractor->extract( $dom, $post, $seen_srcs );

			foreach ( $bg_images as $image ) {
				$images[] = $image;
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'FP SEO: Error extracting background images', array(
				'error' => $e->getMessage(),
				'post_id' => $post->ID,
			) );
		}

		return $images;
	}
}

