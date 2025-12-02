<?php
/**
 * Extracts images from HTML img tags.
 *
 * @package FP\SEO\Editor\Services\ImageExtractors
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services\ImageExtractors;

use WP_Post;
use function content_url;
use function get_post;
use function get_post_meta;
use function site_url;
use function strpos;

/**
 * Extracts images from HTML img tags.
 */
class HtmlImageExtractor {
	/**
	 * Extract image data from img tag.
	 *
	 * @param \DOMElement $img       Image element.
	 * @param WP_Post     $post      Post object.
	 * @param array       $seen_srcs Seen sources to avoid duplicates.
	 * @return array|null Image data or null if skipped.
	 */
	public function extract_from_tag( \DOMElement $img, WP_Post $post, array &$seen_srcs ): ?array {
		$src = $img->getAttribute( 'src' );

		if ( empty( $src ) ) {
			return null;
		}

		// Normalize URL
		$original_src = $src;
		$normalized_src = $this->normalize_url( $src );

		// Check duplicates
		if ( isset( $seen_srcs[ $src ] ) || isset( $seen_srcs[ $normalized_src ] ) ) {
			return null;
		}

		$src = $normalized_src;
		$seen_srcs[ $src ] = true;
		$seen_srcs[ $original_src ] = true;

		// Get attachment ID
		$attachment_id = $this->get_attachment_id_from_url( $src );
		if ( ! $attachment_id && $original_src !== $src ) {
			$attachment_id = $this->get_attachment_id_from_url( $original_src );
		}

		// Get attributes
		$alt = $img->getAttribute( 'alt' ) ?: '';
		$title = $img->getAttribute( 'title' ) ?: '';
		$description = '';

		// Get data from attachment
		if ( $attachment_id ) {
			$attachment = get_post( $attachment_id );
			if ( $attachment ) {
				$description = $attachment->post_content ?: $attachment->post_excerpt ?: '';
				if ( empty( $alt ) ) {
					$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
				}
			}
		}

		// Get saved custom data
		$saved_images = get_post_meta( $post->ID, '_fp_seo_images_data', true );
		if ( is_array( $saved_images ) && isset( $saved_images[ $src ] ) ) {
			$saved = $saved_images[ $src ];
			$alt = $saved['alt'] ?? $alt;
			$title = $saved['title'] ?? $title;
			$description = $saved['description'] ?? $description;
		}

		return array(
			'src'           => $src,
			'alt'           => $alt,
			'title'         => $title,
			'description'   => $description,
			'attachment_id' => $attachment_id,
		);
	}

	/**
	 * Normalize URL (convert relative to absolute).
	 *
	 * @param string $url URL to normalize.
	 * @return string Normalized URL.
	 */
	private function normalize_url( string $url ): string {
		if ( strpos( $url, 'http' ) === 0 ) {
			return $url;
		}

		if ( strpos( $url, '/' ) === 0 ) {
			return site_url( $url );
		}

		return content_url( $url );
	}

	/**
	 * Get attachment ID from URL.
	 *
	 * @param string $url Image URL.
	 * @return int|null Attachment ID or null.
	 */
	private function get_attachment_id_from_url( string $url ): ?int {
		global $wpdb;

		// Try direct match first
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'",
			$url
		) );

		if ( $attachment_id ) {
			return (int) $attachment_id;
		}

		// Try matching by filename
		$filename = basename( $url );
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
			'%' . $wpdb->esc_like( $filename )
		) );

		return $attachment_id ? (int) $attachment_id : null;
	}
}

