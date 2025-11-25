<?php
/**
 * Conditional service trait.
 *
 * Provides helper methods for conditional service registration and booting.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Traits;

/**
 * Trait for service providers with conditional loading logic.
 */
trait ConditionalServiceTrait {

	/**
	 * Check if we're in admin context.
	 *
	 * Uses multiple checks to be reliable even during plugins_loaded hook.
	 *
	 * @return bool
	 */
	protected function is_admin_context(): bool {
		// Primary check: is_admin() function (most reliable in most contexts)
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			return true;
		}

		// Check WP_ADMIN constant (fast check, no sanitization needed)
		if ( defined( 'WP_ADMIN' ) && WP_ADMIN ) {
			return true;
		}

		// Get REQUEST_URI once and sanitize it for all checks that need it
		$request_uri = null;
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			// Validate WordPress sanitization functions are available
			if ( function_exists( 'sanitize_text_field' ) && function_exists( 'wp_unslash' ) ) {
				$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
			} else {
				// Fallback: basic sanitization if WordPress functions not available
				// Use FILTER_SANITIZE_FULL_SPECIAL_CHARS (PHP 8.1+ compatible)
				$request_uri = filter_var( $_SERVER['REQUEST_URI'], FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH );
				if ( false === $request_uri ) {
					$request_uri = (string) $_SERVER['REQUEST_URI'];
				}
			}
			
			// Check REQUEST_URI for admin paths
			if ( ! empty( $request_uri ) && strpos( $request_uri, '/wp-admin/' ) !== false ) {
				return true;
			}
		}

		// Check if we're in AJAX admin request
		// WordPress AJAX requests are typically admin requests when DOING_AJAX is true
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			// Additional check: verify we're in admin-ajax.php context
			// This helps distinguish admin AJAX from frontend AJAX
			if ( null !== $request_uri && strpos( $request_uri, '/wp-admin/admin-ajax.php' ) !== false ) {
				return true;
			}
			// Fallback: if action exists and we're in AJAX, assume admin context
			// (most AJAX requests in WordPress are admin-related)
			// Note: We only check for existence, value is not used, so no sanitization needed
			if ( isset( $_REQUEST['action'] ) && ! empty( $_REQUEST['action'] ) ) {
				return true;
			}
		}

		// Check if we're in REST API admin request
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && null !== $request_uri && ! empty( $request_uri ) ) {
			// REST API for admin endpoints
			// Use strict !== false check since strpos can return 0 (valid position)
			if ( strpos( $request_uri, '/wp-json/wp/v2/' ) !== false || strpos( $request_uri, '/wp-json/' ) !== false ) {
				// Additional check: if user is logged in, likely admin request
				if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if GEO is enabled.
	 *
	 * @return bool
	 */
	protected function is_geo_enabled(): bool {
		$config_class = \FP\SEO\Infrastructure\Config\ServiceConfig::class;
		return $config_class::is_geo_enabled();
	}

	/**
	 * Check if GSC is configured.
	 *
	 * @return bool
	 */
	protected function is_gsc_configured(): bool {
		$config_class = \FP\SEO\Infrastructure\Config\ServiceConfig::class;
		return $config_class::is_gsc_configured();
	}

	/**
	 * Check if WordPress functions are available.
	 *
	 * @return bool
	 */
	protected function is_wp_available(): bool {
		$config_class = \FP\SEO\Infrastructure\Config\ServiceConfig::class;
		return $config_class::is_wp_available();
	}

	/**
	 * Check if current user can manage options.
	 *
	 * @return bool Returns false if WordPress functions are not available.
	 */
	protected function can_manage_options(): bool {
		if ( ! function_exists( 'current_user_can' ) ) {
			return false;
		}
		return current_user_can( 'manage_options' );
	}
}

