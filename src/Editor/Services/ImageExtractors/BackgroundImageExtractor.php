<?php
/**
 * Extracts images from background-image CSS and data attributes.
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
use function preg_match_all;
use function site_url;
use function strpos;

/**
 * Extracts images from background-image CSS and data attributes.
 */
class BackgroundImageExtractor {
	/**
	 * Extract images from background CSS and data attributes.
	 *
	 * @param \DOMDocument $dom       DOM document.
	 * @param WP_Post      $post      Post object.
	 * @param array        $seen_srcs Seen sources to avoid duplicates.
	 * @return array Array of image data.
	 */
	public function extract( \DOMDocument $dom, WP_Post $post, array &$seen_srcs ): array {
		$images = array();
		$xpath = new \DOMXPath( $dom );

		// Extract from style="background-image: url(...)"
		$images = array_merge( $images, $this->extract_from_style_attribute( $xpath, $post, $seen_srcs ) );

		// Extract from data attributes
		$images = array_merge( $images, $this->extract_from_data_attributes( $xpath, $post, $seen_srcs ) );

		return $images;
	}

	/**
	 * Extract from style attribute.
	 *
	 * @param \DOMXPath $xpath     XPath object.
	 * @param WP_Post   $post      Post object.
	 * @param array     $seen_srcs Seen sources.
	 * @return array Array of image data.
	 */
	private function extract_from_style_attribute( \DOMXPath $xpath, WP_Post $post, array &$seen_srcs ): array {
		$images = array();
		$elements_with_bg = $xpath->query( '//*[@style]' );

		foreach ( $elements_with_bg as $element ) {
			$style = $element->getAttribute( 'style' );
			if ( preg_match_all( '/background-image\s*:\s*url\(["\']?([^"\')]+)["\']?\)/i', $style, $bg_matches, PREG_SET_ORDER ) ) {
				foreach ( $bg_matches as $bg_match ) {
					$bg_url = $bg_match[1];
					$image_data = $this->process_background_url( $bg_url, $post, $seen_srcs );
					if ( $image_data ) {
						$images[] = $image_data;
					}
				}
			}
		}

		return $images;
	}

	/**
	 * Extract from data attributes.
	 *
	 * @param \DOMXPath $xpath     XPath object.
	 * @param WP_Post   $post      Post object.
	 * @param array     $seen_srcs Seen sources.
	 * @return array Array of image data.
	 */
	private function extract_from_data_attributes( \DOMXPath $xpath, WP_Post $post, array &$seen_srcs ): array {
		$images = array();
		$data_image_elements = $xpath->query( '//*[@data-bg-image or @data-image or @data-background-image]' );

		foreach ( $data_image_elements as $element ) {
			$data_attrs = array( 'data-bg-image', 'data-image', 'data-background-image' );
			foreach ( $data_attrs as $attr ) {
				$data_url = $element->getAttribute( $attr );
				if ( ! empty( $data_url ) ) {
					$image_data = $this->process_background_url( $data_url, $post, $seen_srcs );
					if ( $image_data ) {
						$images[] = $image_data;
					}
				}
			}
		}

		return $images;
	}

	/**
	 * Process background URL and create image data.
	 *
	 * @param string  $url       Image URL.
	 * @param WP_Post $post      Post object.
	 * @param array   $seen_srcs Seen sources.
	 * @return array|null Image data or null.
	 */
	private function process_background_url( string $url, WP_Post $post, array &$seen_srcs ): ?array {
		if ( empty( $url ) || isset( $seen_srcs[ $url ] ) ) {
			return null;
		}

		// Normalize URL
		if ( strpos( $url, 'http' ) !== 0 ) {
			if ( strpos( $url, '/' ) === 0 ) {
				$url = site_url( $url );
			} else {
				$url = content_url( $url );
			}
		}

		if ( isset( $seen_srcs[ $url ] ) ) {
			return null;
		}

		$seen_srcs[ $url ] = true;

		// Get attachment ID
		$attachment_id = $this->get_attachment_id_from_url( $url );

		$alt = '';
		$title = '';
		$description = '';

		if ( $attachment_id ) {
			$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
			$attachment = get_post( $attachment_id );
			$title = $attachment ? $attachment->post_title : '';
			$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';
		}

		return array(
			'src'           => $url,
			'alt'           => $alt,
			'title'         => $title,
			'description'   => $description,
			'attachment_id' => $attachment_id,
		);
	}

	/**
	 * Get attachment ID from URL.
	 *
	 * @param string $url Image URL.
	 * @return int|null Attachment ID or null.
	 */
	private function get_attachment_id_from_url( string $url ): ?int {
		global $wpdb;

		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'",
			$url
		) );

		if ( $attachment_id ) {
			return (int) $attachment_id;
		}

		$filename = basename( $url );
		$attachment_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
			'%' . $wpdb->esc_like( $filename )
		) );

		return $attachment_id ? (int) $attachment_id : null;
	}
}

