<?php
/**
 * Multi-Modal Content Optimizer for AI Vision Models
 *
 * Optimizes images and visual content for AI vision models (GPT-4V, Gemini Vision, Claude Vision).
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\GEO;

use WP_Post;

/**
 * Optimizes multi-modal content for AI vision models
 */
class MultiModalOptimizer {

	/**
	 * Meta key for image optimization data
	 */
	private const META_IMAGE_DATA = '_fp_seo_image_optimization';

	/**
	 * Optimize all images in a post for AI vision
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed> Optimization data.
	 */
	public function optimize_images( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array();
		}

		$images = $this->get_post_images( $post );

		if ( empty( $images ) ) {
			return array(
				'total_images'      => 0,
				'optimized_images'  => array(),
				'optimization_score' => 0.0,
			);
		}

		$optimized_images = array();

		foreach ( $images as $image ) {
			$optimized_images[] = $this->optimize_single_image( $image, $post );
		}

		$optimization_data = array(
			'total_images'       => count( $optimized_images ),
			'optimized_images'   => $optimized_images,
			'optimization_score' => $this->calculate_optimization_score( $optimized_images ),
			'summary'            => $this->generate_visual_summary( $optimized_images ),
		);

		// Cache optimization data
		update_post_meta( $post_id, self::META_IMAGE_DATA, $optimization_data );

		return $optimization_data;
	}

	/**
	 * Get all images from post content
	 *
	 * @param WP_Post $post Post object.
	 * @return array<int, array<string, mixed>> Images.
	 */
	private function get_post_images( WP_Post $post ): array {
		$content = $post->post_content;
		$images  = array();

		// Extract img tags
		preg_match_all( '/<img[^>]+>/i', $content, $img_tags );

		if ( empty( $img_tags[0] ) ) {
			return $images;
		}

		foreach ( $img_tags[0] as $img_tag ) {
			$image_data = $this->parse_img_tag( $img_tag );

			if ( ! empty( $image_data['url'] ) ) {
				$images[] = $image_data;
			}
		}

		// Add featured image
		$featured_image_id = get_post_thumbnail_id( $post->ID );

		if ( $featured_image_id ) {
			$featured_data = $this->get_attachment_data( $featured_image_id );

			if ( ! empty( $featured_data ) ) {
				$featured_data['is_featured'] = true;
				array_unshift( $images, $featured_data ); // Featured image first
			}
		}

		return $images;
	}

	/**
	 * Parse img tag to extract data
	 *
	 * @param string $img_tag IMG HTML tag.
	 * @return array<string, mixed> Image data.
	 */
	private function parse_img_tag( string $img_tag ): array {
		$data = array();

		// Extract src
		if ( preg_match( '/src=["\']([^"\']+)["\']/i', $img_tag, $matches ) ) {
			$data['url'] = esc_url_raw( $matches[1] );
		}

		// Extract alt
		if ( preg_match( '/alt=["\']([^"\']+)["\']/i', $img_tag, $matches ) ) {
			$data['alt'] = sanitize_text_field( $matches[1] );
		} else {
			$data['alt'] = '';
		}

		// Extract title
		if ( preg_match( '/title=["\']([^"\']+)["\']/i', $img_tag, $matches ) ) {
			$data['title'] = sanitize_text_field( $matches[1] );
		} else {
			$data['title'] = '';
		}

		// Extract width/height
		if ( preg_match( '/width=["\'](\d+)["\']/i', $img_tag, $matches ) ) {
			$data['width'] = (int) $matches[1];
		}

		if ( preg_match( '/height=["\'](\d+)["\']/i', $img_tag, $matches ) ) {
			$data['height'] = (int) $matches[1];
		}

		// Extract class
		if ( preg_match( '/class=["\']([^"\']+)["\']/i', $img_tag, $matches ) ) {
			$data['classes'] = sanitize_text_field( $matches[1] );
		}

		$data['is_featured'] = false;

		return $data;
	}

	/**
	 * Get attachment data from media library
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array<string, mixed> Attachment data.
	 */
	private function get_attachment_data( int $attachment_id ): array {
		$url = wp_get_attachment_url( $attachment_id );

		if ( ! $url ) {
			return array();
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$alt      = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$caption  = wp_get_attachment_caption( $attachment_id );

		return array(
			'url'         => esc_url_raw( $url ),
			'alt'         => sanitize_text_field( $alt ),
			'caption'     => sanitize_text_field( $caption ),
			'title'       => get_the_title( $attachment_id ),
			'width'       => (int) ( $metadata['width'] ?? 0 ),
			'height'      => (int) ( $metadata['height'] ?? 0 ),
			'filesize'    => (int) ( $metadata['filesize'] ?? 0 ),
			'mime_type'   => get_post_mime_type( $attachment_id ),
			'is_featured' => false,
		);
	}

	/**
	 * Optimize single image for AI vision
	 *
	 * @param array<string, mixed> $image Image data.
	 * @param WP_Post              $post  Post object.
	 * @return array<string, mixed> Optimized image data.
	 */
	private function optimize_single_image( array $image, WP_Post $post ): array {
		return array(
			'url'                   => $image['url'] ?? '',
			'alt'                   => $image['alt'] ?? '',
			'caption'               => $image['caption'] ?? '',
			'title'                 => $image['title'] ?? '',
			'semantic_description'  => $this->generate_semantic_description( $image, $post ),
			'contains'              => $this->detect_image_content_types( $image ),
			'context'               => $this->extract_image_context( $image, $post ),
			'related_text'          => $this->find_related_text( $image, $post ),
			'ocr_text'              => $this->extract_text_from_image_context( $image, $post ),
			'ai_vision_tags'        => $this->generate_ai_tags( $image ),
			'accessibility_score'   => $this->calculate_accessibility_score( $image ),
			'relevance_score'       => $this->calculate_relevance_score( $image, $post ),
			'dimensions'            => array(
				'width'  => $image['width'] ?? 0,
				'height' => $image['height'] ?? 0,
			),
			'is_featured'           => $image['is_featured'] ?? false,
		);
	}

	/**
	 * Generate semantic description for image
	 *
	 * @param array<string, mixed> $image Image data.
	 * @param WP_Post              $post  Post object.
	 * @return string Semantic description.
	 */
	private function generate_semantic_description( array $image, WP_Post $post ): string {
		$parts = array();

		// Use alt text as base
		if ( ! empty( $image['alt'] ) ) {
			$parts[] = $image['alt'];
		}

		// Add caption if different from alt
		if ( ! empty( $image['caption'] ) && $image['caption'] !== $image['alt'] ) {
			$parts[] = $image['caption'];
		}

		// Add context from surrounding content
		$context = $this->extract_image_context( $image, $post );
		if ( ! empty( $context ) && ! in_array( $context, $parts, true ) ) {
			$parts[] = 'Context: ' . $context;
		}

		// If no description available, generate from filename
		if ( empty( $parts ) && ! empty( $image['url'] ) ) {
			$filename = basename( $image['url'] );
			$name     = pathinfo( $filename, PATHINFO_FILENAME );
			$readable = str_replace( array( '-', '_' ), ' ', $name );
			$parts[]  = 'Image: ' . $readable;
		}

		return implode( '. ', $parts );
	}

	/**
	 * Detect image content types (chart, screenshot, photo, diagram, etc.)
	 *
	 * @param array<string, mixed> $image Image data.
	 * @return array<int, string> Content types.
	 */
	private function detect_image_content_types( array $image ): array {
		$types = array();

		$alt_and_caption = strtolower( ( $image['alt'] ?? '' ) . ' ' . ( $image['caption'] ?? '' ) );

		// Detect from alt/caption keywords
		$type_keywords = array(
			'screenshot' => array( 'screenshot', 'schermata', 'capture' ),
			'chart'      => array( 'chart', 'grafico', 'graph', 'diagram' ),
			'photo'      => array( 'photo', 'foto', 'image', 'picture' ),
			'logo'       => array( 'logo', 'brand', 'marchio' ),
			'icon'       => array( 'icon', 'icona' ),
			'infographic' => array( 'infographic', 'infografica' ),
			'diagram'    => array( 'diagram', 'diagramma', 'schema' ),
			'ui'         => array( 'interface', 'ui', 'dashboard', 'panel' ),
		);

		foreach ( $type_keywords as $type => $keywords ) {
			foreach ( $keywords as $keyword ) {
				if ( strpos( $alt_and_caption, $keyword ) !== false ) {
					$types[] = $type;
					break;
				}
			}
		}

		// Detect from filename
		if ( ! empty( $image['url'] ) ) {
			$filename_lower = strtolower( basename( $image['url'] ) );

			if ( strpos( $filename_lower, 'screenshot' ) !== false || strpos( $filename_lower, 'screen' ) !== false ) {
				if ( ! in_array( 'screenshot', $types, true ) ) {
					$types[] = 'screenshot';
				}
			}

			if ( strpos( $filename_lower, 'logo' ) !== false ) {
				if ( ! in_array( 'logo', $types, true ) ) {
					$types[] = 'logo';
				}
			}
		}

		// Default to 'image' if no type detected
		if ( empty( $types ) ) {
			$types[] = 'image';
		}

		return array_unique( $types );
	}

	/**
	 * Extract context where image appears in content
	 *
	 * @param array<string, mixed> $image Image data.
	 * @param WP_Post              $post  Post object.
	 * @return string Context description.
	 */
	private function extract_image_context( array $image, WP_Post $post ): string {
		if ( ! empty( $image['is_featured'] ) ) {
			return 'Featured image for article: ' . $post->post_title;
		}

		$content = $post->post_content;
		$img_url = $image['url'] ?? '';

		if ( empty( $img_url ) || empty( $content ) ) {
			return '';
		}

		// Find image position in content
		$pos = strpos( $content, $img_url );

		if ( false === $pos ) {
			return '';
		}

		// Look for nearest heading before image
		$content_before = substr( $content, 0, $pos );
		$headings       = array();

		preg_match_all( '/<h[2-6][^>]*>(.*?)<\/h[2-6]>/is', $content_before, $heading_matches );

		if ( ! empty( $heading_matches[1] ) ) {
			$last_heading = end( $heading_matches[1] );
			return 'Section: ' . wp_strip_all_tags( $last_heading );
		}

		return '';
	}

	/**
	 * Find text related to image (surrounding paragraphs)
	 *
	 * @param array<string, mixed> $image Image data.
	 * @param WP_Post              $post  Post object.
	 * @return string Related text.
	 */
	private function find_related_text( array $image, WP_Post $post ): string {
		$content = $post->post_content;
		$img_url = $image['url'] ?? '';

		if ( empty( $img_url ) || empty( $content ) ) {
			return '';
		}

		$pos = strpos( $content, $img_url );

		if ( false === $pos ) {
			return '';
		}

		// Extract paragraph before and after image
		$before = substr( $content, max( 0, $pos - 500 ), 500 );
		$after  = substr( $content, $pos, 500 );

		// Find last complete sentence before
		preg_match( '/([^.!?]+[.!?])\s*$/', $before, $before_matches );
		$text_before = isset( $before_matches[1] ) ? wp_strip_all_tags( $before_matches[1] ) : '';

		// Find first complete sentence after
		preg_match( '/^\s*([^.!?]+[.!?])/', $after, $after_matches );
		$text_after = isset( $after_matches[1] ) ? wp_strip_all_tags( $after_matches[1] ) : '';

		$related = array_filter( array( $text_before, $text_after ) );

		return implode( ' ', $related );
	}

	/**
	 * Extract text that might appear in the image (from alt/caption)
	 *
	 * @param array<string, mixed> $image Image data.
	 * @param WP_Post              $post  Post object.
	 * @return string Potential OCR text.
	 */
	private function extract_text_from_image_context( array $image, WP_Post $post ): string {
		// For screenshots and UI images, alt text often contains visible text
		$types = $this->detect_image_content_types( $image );

		if ( in_array( 'screenshot', $types, true ) || in_array( 'ui', $types, true ) || in_array( 'chart', $types, true ) ) {
			// Extract quoted text or numbers from alt/caption
			$alt_caption = ( $image['alt'] ?? '' ) . ' ' . ( $image['caption'] ?? '' );

			// Extract quoted strings
			preg_match_all( '/"([^"]+)"/', $alt_caption, $quoted );

			// Extract numbers (metrics, percentages, etc.)
			preg_match_all( '/\b\d+[%]?\b/', $alt_caption, $numbers );

			$ocr_text = array_merge( $quoted[1] ?? array(), $numbers[0] ?? array() );

			return implode( ', ', $ocr_text );
		}

		return '';
	}

	/**
	 * Generate AI-specific tags for image
	 *
	 * @param array<string, mixed> $image Image data.
	 * @return array<int, string> AI tags.
	 */
	private function generate_ai_tags( array $image ): array {
		$tags = array();

		// Extract keywords from alt and caption
		$text  = strtolower( ( $image['alt'] ?? '' ) . ' ' . ( $image['caption'] ?? '' ) );
		$words = str_word_count( $text, 1 );

		// Filter stopwords
		$stopwords = array( 'il', 'lo', 'la', 'i', 'gli', 'le', 'di', 'a', 'da', 'in', 'con', 'su', 'per', 'the', 'a', 'an', 'of', 'to', 'in', 'for' );
		$words     = array_diff( $words, $stopwords );

		// Filter short words
		$words = array_filter( $words, function ( $word ) {
			return strlen( $word ) > 3;
		} );

		// Get content types as tags
		$types = $this->detect_image_content_types( $image );
		$tags  = array_merge( $tags, $types );

		// Add relevant keywords
		$tags = array_merge( $tags, array_slice( array_values( $words ), 0, 5 ) );

		return array_unique( $tags );
	}

	/**
	 * Calculate accessibility score for image
	 *
	 * @param array<string, mixed> $image Image data.
	 * @return float Accessibility score (0-1).
	 */
	private function calculate_accessibility_score( array $image ): float {
		$score = 0.0;

		// Has alt text
		if ( ! empty( $image['alt'] ) ) {
			$score += 0.5;

			// Alt text quality
			$alt_length = strlen( $image['alt'] );
			if ( $alt_length > 10 && $alt_length < 125 ) {
				$score += 0.2; // Good length
			}
		}

		// Has caption
		if ( ! empty( $image['caption'] ) ) {
			$score += 0.2;
		}

		// Has title
		if ( ! empty( $image['title'] ) ) {
			$score += 0.1;
		}

		return min( 1.0, $score );
	}

	/**
	 * Calculate relevance score (how relevant is image to content)
	 *
	 * @param array<string, mixed> $image Image data.
	 * @param WP_Post              $post  Post object.
	 * @return float Relevance score (0-1).
	 */
	private function calculate_relevance_score( array $image, WP_Post $post ): float {
		$score = 0.5; // Base score

		// Featured image = highly relevant
		if ( ! empty( $image['is_featured'] ) ) {
			return 1.0;
		}

		// Has descriptive alt text
		if ( ! empty( $image['alt'] ) && strlen( $image['alt'] ) > 20 ) {
			$score += 0.2;
		}

		// Alt/caption contains title keywords
		$title_words = array_map( 'strtolower', str_word_count( $post->post_title, 1 ) );
		$image_text  = strtolower( ( $image['alt'] ?? '' ) . ' ' . ( $image['caption'] ?? '' ) );

		$keyword_matches = 0;
		foreach ( $title_words as $word ) {
			if ( strlen( $word ) > 4 && strpos( $image_text, $word ) !== false ) {
				$keyword_matches++;
			}
		}

		if ( $keyword_matches > 0 ) {
			$score += min( 0.3, $keyword_matches * 0.1 );
		}

		return min( 1.0, $score );
	}

	/**
	 * Calculate overall optimization score
	 *
	 * @param array<int, array<string, mixed>> $optimized_images Optimized images.
	 * @return float Overall score (0-1).
	 */
	private function calculate_optimization_score( array $optimized_images ): float {
		if ( empty( $optimized_images ) ) {
			return 0.0;
		}

		$total_accessibility = 0.0;
		$total_relevance     = 0.0;

		foreach ( $optimized_images as $image ) {
			$total_accessibility += $image['accessibility_score'] ?? 0.0;
			$total_relevance     += $image['relevance_score'] ?? 0.0;
		}

		$avg_accessibility = $total_accessibility / count( $optimized_images );
		$avg_relevance     = $total_relevance / count( $optimized_images );

		// Weighted average
		return ( $avg_accessibility * 0.6 ) + ( $avg_relevance * 0.4 );
	}

	/**
	 * Generate visual content summary
	 *
	 * @param array<int, array<string, mixed>> $optimized_images Optimized images.
	 * @return array<string, mixed> Visual summary.
	 */
	private function generate_visual_summary( array $optimized_images ): array {
		$content_types = array();
		$total_tags    = array();

		foreach ( $optimized_images as $image ) {
			$content_types = array_merge( $content_types, $image['contains'] ?? array() );
			$total_tags    = array_merge( $total_tags, $image['ai_vision_tags'] ?? array() );
		}

		$content_type_counts = array_count_values( $content_types );
		$tag_counts          = array_count_values( $total_tags );

		arsort( $content_type_counts );
		arsort( $tag_counts );

		return array(
			'content_types'       => array_keys( array_slice( $content_type_counts, 0, 5 ) ),
			'top_tags'            => array_keys( array_slice( $tag_counts, 0, 10 ) ),
			'has_featured_image'  => $this->has_featured_image( $optimized_images ),
			'images_with_alt'     => $this->count_images_with_alt( $optimized_images ),
			'avg_accessibility'   => $this->calculate_optimization_score( $optimized_images ),
		);
	}

	/**
	 * Check if has featured image
	 *
	 * @param array<int, array<string, mixed>> $images Images.
	 * @return bool True if has featured image.
	 */
	private function has_featured_image( array $images ): bool {
		foreach ( $images as $image ) {
			if ( ! empty( $image['is_featured'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Count images with alt text
	 *
	 * @param array<int, array<string, mixed>> $images Images.
	 * @return int Count.
	 */
	private function count_images_with_alt( array $images ): int {
		$count = 0;

		foreach ( $images as $image ) {
			if ( ! empty( $image['alt'] ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Get cached optimization data
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>|null Optimization data or null.
	 */
	public function get_optimization_data( int $post_id ): ?array {
		$data = get_post_meta( $post_id, self::META_IMAGE_DATA, true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Clear optimization cache
	 *
	 * @param int $post_id Post ID.
	 * @return bool Success.
	 */
	public function clear_cache( int $post_id ): bool {
		return delete_post_meta( $post_id, self::META_IMAGE_DATA );
	}
}


