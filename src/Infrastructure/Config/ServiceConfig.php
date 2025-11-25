<?php
/**
 * Service configuration helper.
 *
 * Centralizes configuration checks for service providers.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Config;

use FP\SEO\Utils\Options;

/**
 * Service configuration helper.
 */
class ServiceConfig {

	/**
	 * Check if GEO feature is enabled.
	 *
	 * @return bool
	 */
	public static function is_geo_enabled(): bool {
		$options = Options::get();
		return (bool) ( $options['geo']['enabled'] ?? false );
	}

	/**
	 * Check if Google Search Console is configured.
	 *
	 * @return bool
	 */
	public static function is_gsc_configured(): bool {
		$options         = Options::get();
		$gsc_credentials = $options['gsc']['service_account_json'] ?? '';
		$gsc_site_url    = $options['gsc']['site_url'] ?? '';

		return ! empty( $gsc_credentials ) && ! empty( $gsc_site_url );
	}

	/**
	 * Get GSC credentials.
	 *
	 * @return array{service_account_json: string, site_url: string}
	 */
	public static function get_gsc_config(): array {
		$options = Options::get();
		return array(
			'service_account_json' => (string) ( $options['gsc']['service_account_json'] ?? '' ),
			'site_url'             => (string) ( $options['gsc']['site_url'] ?? '' ),
		);
	}

	/**
	 * Check if WordPress functions are available.
	 *
	 * @return bool
	 */
	public static function is_wp_available(): bool {
		return function_exists( 'plugin_dir_path' ) && function_exists( 'wp_mkdir_p' );
	}
}

