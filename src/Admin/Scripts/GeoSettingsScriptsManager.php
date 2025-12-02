<?php
/**
 * Scripts Manager for GEO Settings
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin\Scripts;

use function esc_js;
use function wp_create_nonce;
use function __;

/**
 * Manages JavaScript for GEO Settings
 */
class GeoSettingsScriptsManager {

	/**
	 * Get JavaScript for GEO cache flush functionality
	 *
	 * @return string JavaScript code.
	 */
	public function get_flush_cache_script(): string {
		$nonce = wp_create_nonce( 'fp_seo_geo_flush' );
		$confirm_message = __( 'Flush all GEO caches?', 'fp-seo-performance' );
		
		return "
		function fpseoFlushGeoCache() {
			if (confirm('" . esc_js( $confirm_message ) . "')) {
				jQuery.post(ajaxurl, {
					action: 'fp_seo_geo_flush_cache',
					nonce: '" . esc_js( $nonce ) . "'
				}, function(response) {
					alert(response.data || 'Cache flushed!');
				});
			}
		}
		";
	}
}

