<?php
/**
 * Extracts images from WPBakery shortcodes.
 *
 * @package FP\SEO\Editor\Services\ImageExtractors
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Editor\Services\ImageExtractors;

use FP\SEO\Utils\Logger;
use function get_post;
use function get_post_meta;
use function preg_match_all;
use function wp_get_attachment_url;

/**
 * Extracts images from WPBakery shortcodes.
 */
class WPBakeryImageExtractor {
	/**
	 * Extract images from WPBakery shortcodes.
	 *
	 * @param string $content Post content.
	 * @param int    $post_id Post ID.
	 * @return array Array of image data.
	 */
	public function extract( string $content, int $post_id ): array {
		$images = array();

		// Extract from vc_single_image shortcode
		if ( preg_match_all( '/\[vc_single_image[^\]]*image="(\d+)"[^\]]*\]/i', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$attachment_id = (int) $match[1];
				$image_data = $this->get_image_data_from_attachment( $attachment_id, $post_id );
				if ( $image_data ) {
					$images[] = $image_data;
				}
			}
		}

		// Extract from vc_gallery shortcode
		if ( preg_match_all( '/\[vc_gallery[^\]]*images="([^"]+)"[^\]]*\]/i', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$image_ids = explode( ',', $match[1] );
				foreach ( $image_ids as $attachment_id ) {
					$attachment_id = (int) trim( $attachment_id );
					$image_data = $this->get_image_data_from_attachment( $attachment_id, $post_id );
					if ( $image_data ) {
						$images[] = $image_data;
					}
				}
			}
		}

		Logger::debug( 'WPBakeryImageExtractor - Extracted images', array(
			'post_id' => $post_id,
			'count' => count( $images ),
		) );

		return $images;
	}

	/**
	 * Get image data from attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $post_id       Post ID.
	 * @return array|null Image data or null.
	 */
	private function get_image_data_from_attachment( int $attachment_id, int $post_id ): ?array {
		if ( ! $attachment_id ) {
			return null;
		}

		$image_url = wp_get_attachment_url( $attachment_id );
		if ( ! $image_url ) {
			return null;
		}

		$attachment = get_post( $attachment_id );
		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ?: '';
		$title = $attachment ? $attachment->post_title : '';
		$description = $attachment ? ( $attachment->post_content ?: $attachment->post_excerpt ?: '' ) : '';

		return array(
			'src'           => $image_url,
			'alt'           => $alt,
			'title'         => $title,
			'description'   => $description,
			'attachment_id' => $attachment_id,
		);
	}
}

