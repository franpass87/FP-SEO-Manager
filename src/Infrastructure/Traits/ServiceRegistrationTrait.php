<?php
/**
 * Service registration trait.
 *
 * Provides helper methods for batch registration and booting of services.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Traits;

use FP\SEO\Infrastructure\Container;

/**
 * Trait for service providers to register and boot multiple services easily.
 */
trait ServiceRegistrationTrait {

	/**
	 * Register multiple services as singletons.
	 *
	 * @param Container $container The container instance.
	 * @param string[]  $service_classes Array of service class names.
	 * @return void
	 */
	protected function register_singletons( Container $container, array $service_classes ): void {
		if ( empty( $service_classes ) ) {
			return; // Silently skip empty arrays to allow conditional registration
		}

		foreach ( $service_classes as $service_class ) {
			if ( ! is_string( $service_class ) || empty( $service_class ) ) {
				continue; // Skip invalid entries instead of throwing to be more resilient
			}
			$container->singleton( $service_class );
		}
	}

	/**
	 * Register multiple services with custom factories.
	 *
	 * @param Container $container The container instance.
	 * @param array<string, callable> $services Array mapping service class names to factory functions.
	 * @return void
	 */
	protected function register_with_factories( Container $container, array $services ): void {
		if ( empty( $services ) ) {
			return; // Silently skip empty arrays
		}

		foreach ( $services as $service_class => $factory ) {
			if ( ! is_string( $service_class ) || empty( $service_class ) ) {
				continue; // Skip invalid service class names
			}
			if ( ! is_callable( $factory ) ) {
				continue; // Skip invalid factories
			}
			$container->singleton( $service_class, $factory );
		}
	}

	/**
	 * Boot multiple services using ServiceBooterTrait.
	 *
	 * Requires ServiceBooterTrait to be used as well.
	 *
	 * @param Container $container The container instance.
	 * @param array<string, array{log_level?: string, error_message?: string}> $services Array of service classes with optional log configuration.
	 * @return void
	 * @throws \RuntimeException If ServiceBooterTrait is not used.
	 */
	protected function boot_services_batch( Container $container, array $services ): void {
		if ( ! method_exists( $this, 'boot_service' ) ) {
			throw new \RuntimeException( 'ServiceRegistrationTrait::boot_services_batch() requires ServiceBooterTrait' );
		}

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
			$log_level     = $config['log_level'] ?? 'warning';
			$error_message = $config['error_message'] ?? '';
			$this->boot_service( $container, $service_class, $log_level, $error_message );
		}
	}

	/**
	 * Boot multiple services with the same log level and error message pattern.
	 *
	 * Requires ServiceBooterTrait to be used as well.
	 *
	 * @param Container $container The container instance.
	 * @param string[]  $service_classes Array of service class names.
	 * @param string    $log_level Log level (default: 'warning').
	 * @param string    $error_message_prefix Error message prefix (default: 'Failed to register').
	 * @return void
	 * @throws \RuntimeException If ServiceBooterTrait is not used.
	 */
	protected function boot_services_simple( Container $container, array $service_classes, string $log_level = 'warning', string $error_message_prefix = 'Failed to register' ): void {
		if ( ! method_exists( $this, 'boot_service' ) ) {
			throw new \RuntimeException( 'ServiceRegistrationTrait::boot_services_simple() requires ServiceBooterTrait' );
		}

		if ( empty( $service_classes ) ) {
			return; // Silently skip empty arrays
		}

		foreach ( $service_classes as $service_class ) {
			if ( ! is_string( $service_class ) || empty( $service_class ) ) {
				continue; // Skip invalid entries
			}
			$error_message = $error_message_prefix . ' ' . $service_class;
			$this->boot_service( $container, $service_class, $log_level, $error_message );
		}
	}
}
