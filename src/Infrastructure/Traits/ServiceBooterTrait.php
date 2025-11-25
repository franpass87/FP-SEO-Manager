<?php
/**
 * Service booter trait.
 *
 * Provides helper methods for booting services with error handling.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Traits;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Utils\Logger;

/**
 * Trait for service providers to boot services safely.
 */
trait ServiceBooterTrait {

	/**
	 * Boot a service from the container.
	 *
	 * @param Container $container The container instance.
	 * @param string    $service_class The service class name.
	 * @param string    $log_level The log level ('debug', 'warning', 'error').
	 * @param string    $error_message The error message prefix.
	 * @return bool True if service was booted successfully, false otherwise.
	 */
	protected function boot_service(
		Container $container,
		string $service_class,
		string $log_level = 'warning',
		string $error_message = ''
	): bool {
		// Validate log_level to prevent invalid values
		$valid_log_levels = array( 'debug', 'warning', 'error' );
		if ( ! in_array( $log_level, $valid_log_levels, true ) ) {
			$log_level = 'warning'; // Default to warning for invalid values
		}

		try {
			$service = $container->get( $service_class );
			if ( method_exists( $service, 'register' ) ) {
				$service->register();
				return true;
			}
			return false;
		} catch ( \Throwable $e ) {
			$message = $error_message ?: sprintf( 'Failed to register %s', $service_class );
			
			if ( 'error' === $log_level ) {
				Logger::error( $message, array( 'error' => $e->getMessage() ) );
			} elseif ( 'debug' === $log_level ) {
				Logger::debug( $message, array( 'error' => $e->getMessage() ) );
			} else {
				Logger::warning( $message, array( 'error' => $e->getMessage() ) );
			}
			
			return false;
		}
	}

	/**
	 * Boot multiple services from the container.
	 *
	 * @param Container $container The container instance.
	 * @param array<string, array{log_level?: string, error_message?: string}> $services Array of service classes with optional log configuration.
	 * @return void
	 */
	protected function boot_services( Container $container, array $services ): void {
		if ( empty( $services ) ) {
			return; // Silently skip empty arrays
		}

		foreach ( $services as $service_class => $config ) {
			if ( ! is_string( $service_class ) || empty( $service_class ) ) {
				continue; // Skip invalid entries
			}
			if ( ! is_array( $config ) ) {
				continue; // Skip invalid config
			}
			$log_level      = $config['log_level'] ?? 'warning';
			$error_message  = $config['error_message'] ?? '';
			$this->boot_service( $container, $service_class, $log_level, $error_message );
		}
	}
}

