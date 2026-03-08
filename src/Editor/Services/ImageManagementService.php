<?php
/**
 * Service for managing images in post content.
 *
 * @package FP\SEO\Editor\Services
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services;

use FP\SEO\Utils\Logger;
use function esc_url_raw;
use function sanitize_textarea_field;
use function sanitize_text_field;
use function update_post_meta;
use function wp_unslash;
use DOMDocument;
use DOMXPath;

/**
 * Service for managing images in post content.
 */
class ImageManagementService {

	/**
	 * Update images in post content with new attributes.
	 *
	 * @param string $content     Post content.
	 * @param array  $images_data Image data array (src => [alt, title, description]).
	 * @return string Updated content.
	 */
	public function update_images_in_content( string $content, array $images_data ): string {
		if ( empty( $content ) || empty( $images_data ) ) {
			return $content;
		}

		// Load content into DOMDocument
		$dom = new DOMDocument();
		
		// Suppress warnings for malformed HTML
		libxml_use_internal_errors( true );
		
		// Load HTML with UTF-8 encoding
		$html = '<?xml encoding="UTF-8">' . $content;
		$dom->loadHTML( $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		
		// Clear libxml errors
		libxml_clear_errors();
		
		// Find all img tags
		$xpath = new DOMXPath( $dom );
		$img_tags = $xpath->query( '//img' );
		
		if ( ! $img_tags || $img_tags->length === 0 ) {
			return $content;
		}

		$updated = false;

		foreach ( $img_tags as $img ) {
			$src = $img->getAttribute( 'src' );
			
			// Remove query strings for matching
			$src_clean = strtok( $src, '?' );
			
			// Find matching image data
			$image_data = null;
			foreach ( $images_data as $data_src => $data ) {
				$data_src_clean = strtok( $data_src, '?' );
				if ( $src_clean === $data_src_clean || $src === $data_src ) {
					$image_data = $data;
					break;
				}
			}

			if ( $image_data ) {
				// Update alt attribute
				if ( ! empty( $image_data['alt'] ) ) {
					$img->setAttribute( 'alt', $image_data['alt'] );
					$updated = true;
				} elseif ( $img->hasAttribute( 'alt' ) && empty( $image_data['alt'] ) ) {
					// Remove alt if it was cleared
					$img->removeAttribute( 'alt' );
					$updated = true;
				}

				// Update title attribute
				if ( ! empty( $image_data['title'] ) ) {
					$img->setAttribute( 'title', $image_data['title'] );
					$updated = true;
				} elseif ( $img->hasAttribute( 'title' ) && empty( $image_data['title'] ) ) {
					// Remove title if it was cleared
					$img->removeAttribute( 'title' );
					$updated = true;
				}
			}
		}

		if ( ! $updated ) {
			return $content;
		}

		// Get updated HTML
		$updated_content = $dom->saveHTML();
		if ( false === $updated_content ) {
			return $content;
		}

		// Remove XML declaration and DOCTYPE if present
		$patterns     = array( '/^<\?xml[^>]*\?>/i', '/<!DOCTYPE[^>]*>/i' );
		$replacements = array( '', '' );
		$cleaned      = preg_replace( $patterns, $replacements, $updated_content );
		$updated_content = is_string( $cleaned ) ? trim( $cleaned ) : trim( $updated_content );

		return $updated_content;
	}

	/**
	 * Sanitize images data from POST request.
	 *
	 * @param array $images_data Raw images data from POST.
	 * @return array Sanitized images data.
	 */
	public function sanitize_images_data( array $images_data ): array {
		$sanitized = array();
		
		foreach ( $images_data as $src => $data ) {
			if ( ! is_array( $data ) ) {
				continue;
			}

			$sanitized[ esc_url_raw( $src ) ] = array(
				'alt'         => sanitize_text_field( $data['alt'] ?? '' ),
				'title'       => sanitize_text_field( $data['title'] ?? '' ),
				'description' => sanitize_textarea_field( $data['description'] ?? '' ),
			);
		}

		return $sanitized;
	}

	/**
	 * Save images data to post meta.
	 *
	 * @param int   $post_id     Post ID.
	 * @param array $images_data Sanitized images data.
	 * @return void
	 */
	public function save_images_meta( int $post_id, array $images_data ): void {
		update_post_meta( $post_id, '_fp_seo_images_data', $images_data );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			Logger::debug( 'Images data saved', array(
				'post_id'      => $post_id,
				'images_count' => count( $images_data ),
			) );
		}
	}

	/**
	 * Get attachment ID from image URL.
	 *
	 * @param string $url Image URL.
	 * @return int|null Attachment ID or null if not found.
	 */
	public function get_attachment_id_from_url( string $url ): ?int {
		// Remove query strings
		$url = strtok( $url, '?' );
		
		// Try to get attachment ID from URL
		global $wpdb;
		
		// Try full URL match
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
			basename( $url )
		) );
		
		if ( $attachment_id ) {
			return (int) $attachment_id;
		}
		
		// Try GUID match
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid = %s LIMIT 1",
			$url
		) );
		
		if ( $attachment_id ) {
			return (int) $attachment_id;
		}
		
		// Try to extract from URL path
		$upload_dir = wp_upload_dir();
		if ( strpos( $url, $upload_dir['baseurl'] ) !== false ) {
			$relative_path = str_replace( $upload_dir['baseurl'] . '/', '', $url );
			$attachment_id = $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s LIMIT 1",
				$relative_path
			) );
			
			if ( $attachment_id ) {
				return (int) $attachment_id;
			}
		}
		
		return null;
	}
}








