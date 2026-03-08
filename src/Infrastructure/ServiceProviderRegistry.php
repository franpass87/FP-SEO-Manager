<?php
/**
 * Service Provider Registry.
 *
 * Manages service provider registration and boot order.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure;

use FP\SEO\Infrastructure\Helpers\ErrorLoggingHelper;

/**
 * Registry for managing service providers.
 */
class ServiceProviderRegistry {

	/**
	 * Registered service providers.
	 *
	 * @var array<ServiceProviderInterface>
	 */
	private array $providers = array();

	/**
	 * Whether providers have been booted.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * The container instance.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container The container instance.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register a service provider.
	 *
	 * @param ServiceProviderInterface $provider The provider to register.
	 * @return void
	 */
	public function register( ServiceProviderInterface $provider ): void {
		if ( $this->booted ) {
			throw new \RuntimeException( 'Cannot register providers after boot.' );
		}

		try {
			$provider->register( $this->container );
			// Only add to providers array if registration succeeds
			$this->providers[] = $provider;
		} catch ( \Throwable $e ) {
			// Log error but don't add provider to array
			ErrorLoggingHelper::log_provider_error( $provider, 'register', $e );
			// Re-throw to allow caller to handle the error
			throw $e;
		}
	}

	/**
	 * Register multiple service providers with automatic dependency resolution.
	 *
	 * This method resolves dependencies automatically using topological sort.
	 * Providers are registered in the correct order based on their declared dependencies.
	 *
	 * @param array<ServiceProviderInterface> $providers Array of providers to register.
	 * @return void
	 * @throws \RuntimeException If circular dependencies are detected.
	 */
	public function register_with_dependencies( array $providers ): void {
		if ( $this->booted ) {
			throw new \RuntimeException( 'Cannot register providers after boot.' );
		}

		// Resolve dependencies and get ordered list
		$ordered = $this->resolve_dependencies( $providers );

		// Register providers in resolved order
		foreach ( $ordered as $provider ) {
			$this->register( $provider );
		}
	}

	/**
	 * Resolve provider dependencies using topological sort.
	 *
	 * @param array<ServiceProviderInterface> $providers Array of providers.
	 * @return array<ServiceProviderInterface> Providers in dependency order.
	 * @throws \RuntimeException If circular dependencies are detected.
	 */
	private function resolve_dependencies( array $providers ): array {
		// Build dependency map: provider class => array of dependency classes
		$dependency_map = array();
		$provider_map   = array(); // class name => provider instance

		foreach ( $providers as $provider ) {
			$class_name           = get_class( $provider );
			$provider_map[ $class_name ] = $provider;
			$dependency_map[ $class_name ] = $provider->get_dependencies();
		}

		// Topological sort
		$visited = array();
		$visiting = array();
		$result = array();

		foreach ( $provider_map as $class_name => $provider ) {
			if ( ! isset( $visited[ $class_name ] ) ) {
				$this->visit_provider( $class_name, $provider_map, $dependency_map, $visited, $visiting, $result );
			}
		}

		return $result;
	}

	/**
	 * Visit a provider during topological sort (DFS).
	 *
	 * @param string                          $class_name     Provider class name.
	 * @param array<string,ServiceProviderInterface> $provider_map   Map of class names to provider instances.
	 * @param array<string,array<string>>    $dependency_map Map of class names to their dependencies.
	 * @param array<string,bool>            $visited        Visited providers.
	 * @param array<string,bool>            $visiting        Currently visiting providers (for cycle detection).
	 * @param array<ServiceProviderInterface> $result         Result array (passed by reference).
	 * @return void
	 * @throws \RuntimeException If circular dependency is detected.
	 */
	private function visit_provider(
		string $class_name,
		array $provider_map,
		array $dependency_map,
		array &$visited,
		array &$visiting,
		array &$result
	): void {
		// Check for circular dependency
		if ( isset( $visiting[ $class_name ] ) ) {
			throw new \RuntimeException(
				sprintf(
					'Circular dependency detected involving provider: %s',
					$class_name
				)
			);
		}

		// Skip if already visited
		if ( isset( $visited[ $class_name ] ) ) {
			return;
		}

		// Mark as currently visiting
		$visiting[ $class_name ] = true;

		// Visit dependencies first
		$dependencies = $dependency_map[ $class_name ] ?? array();
		foreach ( $dependencies as $dep_class ) {
			if ( isset( $provider_map[ $dep_class ] ) ) {
				$this->visit_provider( $dep_class, $provider_map, $dependency_map, $visited, $visiting, $result );
			}
		}

		// Mark as visited and add to result
		unset( $visiting[ $class_name ] );
		$visited[ $class_name ] = true;
		$result[] = $provider_map[ $class_name ];
	}

	/**
	 * Boot all registered providers.
	 *
	 * Continues even if one provider fails to ensure all providers get a chance to boot.
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		foreach ( $this->providers as $provider ) {
			try {
				$provider->boot( $this->container );
			} catch ( \Throwable $e ) {
				// Log error but continue with other providers
				ErrorLoggingHelper::log_provider_error( $provider, 'boot', $e );
				// Continue with next provider
				continue;
			}
		}

		$this->booted = true;
	}

	/**
	 * Run activation routines for all providers.
	 *
	 * Continues even if one provider fails to ensure all providers get a chance to activate.
	 *
	 * @return void
	 */
	public function activate(): void {
		foreach ( $this->providers as $provider ) {
			try {
				$provider->activate();
			} catch ( \Throwable $e ) {
				// Log error but continue with other providers
				ErrorLoggingHelper::log_provider_error( $provider, 'activate', $e );
				// Continue with next provider
				continue;
			}
		}
	}

	/**
	 * Run deactivation routines for all providers.
	 *
	 * Continues even if one provider fails to ensure all providers get a chance to deactivate.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		foreach ( $this->providers as $provider ) {
			try {
				$provider->deactivate();
			} catch ( \Throwable $e ) {
				// Log error but continue with other providers
				ErrorLoggingHelper::log_provider_error( $provider, 'deactivate', $e );
				// Continue with next provider
				continue;
			}
		}
	}

	/**
	 * Get all registered providers.
	 *
	 * @return array<ServiceProviderInterface>
	 */
	public function get_providers(): array {
		return $this->providers;
	}

	/**
	 * Check if providers have been booted.
	 *
	 * @return bool
	 */
	public function is_booted(): bool {
		return $this->booted;
	}
}

