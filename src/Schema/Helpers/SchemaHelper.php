<?php
/**
 * Schema helper functions.
 *
 * @package FP\SEO\Schema\Helpers
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Schema\Helpers;

use function get_theme_mod;
use function wp_get_attachment_url;

/**
 * Schema helper functions.
 */
class SchemaHelper {
	/**
	 * Get custom logo URL.
	 *
	 * @return string Logo URL or empty string if not set.
	 */
	public static function get_custom_logo_url(): string {
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		
		if ( ! $custom_logo_id ) {
			return '';
		}
		
		// Usa wp_get_attachment_url invece di wp_get_attachment_image_url per evitare interferenze
		$logo_url = wp_get_attachment_url( $custom_logo_id );
		
		return $logo_url ? (string) $logo_url : '';
	}
}


