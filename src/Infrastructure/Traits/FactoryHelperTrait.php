<?php
/**
 * Factory helper trait.
 *
 * Provides helper methods for creating services with dependencies.
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
 * Trait for service providers to create services with dependencies easily.
 */
trait FactoryHelperTrait {

	/**
	 * Get an optional dependency from the container.
	 *
	 * Returns null if the service cannot be resolved instead of throwing.
	 *
	 * @param Container $container The container instance.
	 * @param string    $service_class The service class name.
	 * @param string    $log_message Optional log message if service not available.
	 * @return object|null The service instance or null if unavailable.
	 */
	protected function get_optional_dependency(
		Container $container,
		string $service_class,
		string $log_message = ''
	): ?object {
		try {
			return $container->get( $service_class );
		} catch ( \Throwable $e ) {
			if ( ! empty( $log_message ) ) {
				Logger::debug( $log_message, array( 'error' => $e->getMessage() ) );
			}
			return null;
		}
	}

	/**
	 * Create a singleton factory with dependencies.
	 *
	 * @param Container  $container The container instance.
	 * @param string     $service_class The service class name.
	 * @param array<string> $dependency_classes Array of dependency class names.
	 * @param callable   $factory Optional custom factory function.
	 * @return callable Factory function for container registration.
	 */
	protected function create_factory(
		Container $container,
		string $service_class,
		array $dependency_classes = array(),
		?callable $factory = null
	): callable {
		return function( Container $container ) use ( $service_class, $dependency_classes, $factory ) {
			if ( $factory ) {
				// Use custom factory
				$dependencies = array();
				foreach ( $dependency_classes as $dep_class ) {
					$dependencies[] = $container->get( $dep_class );
				}
				return $factory( ...$dependencies );
			}

			// Default: instantiate with dependencies
			$dependencies = array();
			foreach ( $dependency_classes as $dep_class ) {
				$dependencies[] = $container->get( $dep_class );
			}
			return new $service_class( ...$dependencies );
		};
	}
}

