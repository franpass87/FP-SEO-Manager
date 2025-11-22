<?php
/**
 * Google Site Kit Integration Helper
 *
 * Detects Google Site Kit plugin and extracts GSC/PSI configuration
 * to pre-fill FP SEO Manager settings.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Utils;

use function get_option;
use function is_plugin_active;
use function is_string;

/**
 * Google Site Kit integration utility.
 */
class SiteKitIntegration {

	/**
	 * Check if Google Site Kit is installed and active.
	 *
	 * @return bool
	 */
	public static function is_site_kit_active(): bool {
		// Check if class exists first (fastest)
		if ( class_exists( '\Google\Site_Kit\Plugin' ) ) {
			return true;
		}

		// Check if constants/functions exist
		if ( defined( 'GOOGLESITEKIT_VERSION' ) || function_exists( 'googlesitekit_load_plugin' ) ) {
			return true;
		}

		// Check if plugin is active (requires wp-admin/includes/plugin.php)
		if ( ! function_exists( 'is_plugin_active' ) ) {
			// Try to load plugin.php if we're in admin
			if ( is_admin() && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
		}

		if ( function_exists( 'is_plugin_active' ) ) {
			return is_plugin_active( 'google-site-kit/google-site-kit.php' );
		}

		return false;
	}

	/**
	 * Get GSC site URL from Site Kit if available.
	 *
	 * @return string|null Site URL or null if not available.
	 */
	public static function get_gsc_site_url(): ?string {
		if ( ! self::is_site_kit_active() ) {
			return null;
		}

		// Try to get from Site Kit Search Console module
		$search_console = get_option( 'googlesitekit_search-console_settings', array() );
		
		if ( is_array( $search_console ) && ! empty( $search_console['propertyID'] ) ) {
			$property_id = $search_console['propertyID'];
			
			// Property ID is usually the site URL
			if ( is_string( $property_id ) && filter_var( $property_id, FILTER_VALIDATE_URL ) ) {
				// Ensure trailing slash
				return rtrim( $property_id, '/' ) . '/';
			}
			
			return $property_id;
		}

		// Fallback: try to get from Site Kit general settings
		$settings = get_option( 'googlesitekit_settings', array() );
		if ( is_array( $settings ) && ! empty( $settings['referenceSiteURL'] ) ) {
			$site_url = $settings['referenceSiteURL'];
			if ( is_string( $site_url ) && filter_var( $site_url, FILTER_VALIDATE_URL ) ) {
				return rtrim( $site_url, '/' ) . '/';
			}
		}

		return null;
	}

	/**
	 * Get GSC service account credentials from Site Kit (if available via OAuth).
	 *
	 * Note: Site Kit uses OAuth, not Service Account, so we can't extract
	 * Service Account JSON directly. But we can suggest the Site URL.
	 *
	 * @return array{site_url?: string} Array with site_url if available.
	 */
	public static function get_gsc_credentials(): array {
		$credentials = array();

		if ( ! self::is_site_kit_active() ) {
			return $credentials;
		}

		$site_url = self::get_gsc_site_url();
		if ( $site_url ) {
			$credentials['site_url'] = $site_url;
		}

		// Site Kit uses OAuth, not Service Account, so we can't get JSON
		// But we can suggest using the same site URL
		return $credentials;
	}

	/**
	 * Get PageSpeed Insights API key from Site Kit if available.
	 *
	 * @return string|null API key or null if not available.
	 */
	public static function get_psi_api_key(): ?string {
		if ( ! self::is_site_kit_active() ) {
			return null;
		}

		// Try to get from Site Kit PageSpeed Insights module
		$pagespeed_settings = get_option( 'googlesitekit_pagespeed-insights_settings', array() );
		
		if ( is_array( $pagespeed_settings ) && ! empty( $pagespeed_settings['apiKey'] ) ) {
			$api_key = $pagespeed_settings['apiKey'];
			if ( is_string( $api_key ) && ! empty( trim( $api_key ) ) ) {
				return trim( $api_key );
			}
		}

		// Try alternative option keys
		$alternative_keys = array(
			'googlesitekit_pagespeed_api_key',
			'googlesitekit_psi_api_key',
			'googlesitekit_pagespeed_insights_api_key',
		);

		foreach ( $alternative_keys as $option_key ) {
			$api_key = get_option( $option_key, '' );
			if ( is_string( $api_key ) && ! empty( trim( $api_key ) ) ) {
				return trim( $api_key );
			}
		}

		return null;
	}

	/**
	 * Check if Site Kit Search Console is connected.
	 *
	 * @return bool
	 */
	public static function is_gsc_connected(): bool {
		if ( ! self::is_site_kit_active() ) {
			return false;
		}

		$search_console = get_option( 'googlesitekit_search-console_settings', array() );
		
		if ( is_array( $search_console ) && ! empty( $search_console['propertyID'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if Site Kit PageSpeed Insights is connected.
	 *
	 * @return bool
	 */
	public static function is_psi_connected(): bool {
		if ( ! self::is_site_kit_active() ) {
			return false;
		}

		$pagespeed_settings = get_option( 'googlesitekit_pagespeed-insights_settings', array() );
		
		if ( is_array( $pagespeed_settings ) && ! empty( $pagespeed_settings['apiKey'] ) ) {
			return true;
		}

		return false;
	}
}
