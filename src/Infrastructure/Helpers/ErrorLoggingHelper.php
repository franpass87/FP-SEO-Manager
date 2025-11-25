<?php
/**
 * Error logging helper.
 *
 * Provides centralized error logging functionality for service providers.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Helpers;

/**
 * Helper class for error logging in service providers.
 */
class ErrorLoggingHelper {

	/**
	 * Log an error for a service provider.
	 *
	 * @param object|string $provider The provider instance or class name.
	 * @param string        $action The action that failed (e.g., 'register', 'boot', 'activate').
	 * @param \Throwable    $exception The exception that occurred.
	 * @return void
	 */
	public static function log_provider_error( $provider, string $action, \Throwable $exception ): void {
		if ( ! class_exists( '\FP\SEO\Utils\Logger' ) ) {
			return;
		}

		// Validate action is not empty
		if ( empty( $action ) ) {
			$action = 'process';
		}

		$provider_class = self::get_provider_class_name( $provider );
		
		// Get exception message with fallback
		$error_message = $exception->getMessage();
		if ( ! is_string( $error_message ) || empty( $error_message ) ) {
			$error_message = 'Unknown error';
		}
		
		\FP\SEO\Utils\Logger::error(
			sprintf( 'Failed to %s provider %s', $action, $provider_class ),
			array( 'error' => $error_message )
		);
	}

	/**
	 * Get provider class name with fallback for edge cases.
	 *
	 * @param object|string $provider The provider instance or class name.
	 * @return string The provider class name or fallback identifier.
	 */
	public static function get_provider_class_name( $provider ): string {
		if ( is_string( $provider ) ) {
			// Validate string is not empty
			if ( empty( $provider ) ) {
				return 'unknown';
			}
			return $provider;
		}

		if ( ! is_object( $provider ) ) {
			return 'unknown';
		}

		$class_name = get_class( $provider );
		if ( empty( $class_name ) || ! is_string( $class_name ) ) {
			return 'anonymous-class';
		}

		return $class_name;
	}
}

