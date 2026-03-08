<?php
/**
 * Dependency resolution and validation.
 *
 * Validates service dependencies and detects circular dependencies.
 *
 * @package FP\SEO\Infrastructure\Bootstrap
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Bootstrap;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\ServiceProviderInterface;
use FP\SEO\Utils\LoggerHelper;
use RuntimeException;

/**
 * Dependency resolver for service providers and services.
 *
 * Validates dependencies and detects circular dependencies.
 */
class DependencyResolver {

	/**
	 * Resolved dependencies map.
	 *
	 * @var array<class-string, array<class-string>>
	 */
	private array $dependency_map = array();

	/**
	 * Providers ordered by dependencies.
	 *
	 * @var array<ServiceProviderInterface>
	 */
	private array $ordered_providers = array();

	/**
	 * Resolve and order providers based on dependencies.
	 *
	 * @param array<ServiceProviderInterface> $providers Providers to order.
	 * @return array<ServiceProviderInterface> Ordered providers.
	 * @throws RuntimeException If circular dependency detected.
	 */
	public function resolve_provider_order( array $providers ): array {
		// Build dependency graph
		$this->build_dependency_graph( $providers );

		// Detect circular dependencies
		$this->detect_circular_dependencies();

		// Topological sort to order providers
		$this->ordered_providers = $this->topological_sort( $providers );

		return $this->ordered_providers;
	}

	/**
	 * Validate that a service can be resolved from container.
	 *
	 * @param Container $container Container instance.
	 * @param string    $service   Service class name.
	 * @return bool True if service can be resolved.
	 */
	public function validate_service_resolution( Container $container, string $service ): bool {
		try {
			// Check if class exists
			if ( ! class_exists( $service ) && ! interface_exists( $service ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					LoggerHelper::debug( 'DependencyResolver: Service class/interface not found', array( 'service' => $service ) );
				}
				return false;
			}

			// Try to get from container (this will throw if not bound and can't be auto-resolved)
			$container->get( $service );
			return true;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				LoggerHelper::debug( 'DependencyResolver: Service cannot be resolved', array(
					'service'   => $service,
					'exception' => $e->getMessage(),
				) );
			}
			return false;
		}
	}

	/**
	 * Get dependency map.
	 *
	 * @return array<class-string, array<class-string>>
	 */
	public function get_dependency_map(): array {
		return $this->dependency_map;
	}

	/**
	 * Build dependency graph from providers.
	 *
	 * @param array<ServiceProviderInterface> $providers Providers.
	 * @return void
	 */
	private function build_dependency_graph( array $providers ): void {
		$this->dependency_map = array();

		foreach ( $providers as $provider ) {
			$provider_class = get_class( $provider );
			$this->dependency_map[ $provider_class ] = $this->get_provider_dependencies( $provider );
		}
	}

	/**
	 * Get dependencies for a provider.
	 *
	 * Uses reflection to detect constructor dependencies.
	 *
	 * @param ServiceProviderInterface $provider Provider.
	 * @return array<class-string> Dependency class names.
	 */
	private function get_provider_dependencies( ServiceProviderInterface $provider ): array {
		$dependencies = array();

		try {
			$reflection = new \ReflectionClass( $provider );
			$constructor = $reflection->getConstructor();

			if ( $constructor ) {
				$parameters = $constructor->getParameters();

				foreach ( $parameters as $parameter ) {
					$type = $parameter->getType();

					if ( $type instanceof \ReflectionNamedType && ! $type->isBuiltin() ) {
						$dependencies[] = $type->getName();
					}
				}
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				LoggerHelper::warning( 'DependencyResolver: Failed to reflect provider dependencies', array(
					'provider'  => get_class( $provider ),
					'exception' => $e->getMessage(),
				) );
			}
		}

		return $dependencies;
	}

	/**
	 * Detect circular dependencies in the graph.
	 *
	 * @return void
	 * @throws RuntimeException If circular dependency detected.
	 */
	private function detect_circular_dependencies(): void {
		$visited = array();
		$recursion_stack = array();

		foreach ( array_keys( $this->dependency_map ) as $node ) {
			if ( ! isset( $visited[ $node ] ) ) {
				$this->dfs_cycle_detection( $node, $visited, $recursion_stack );
			}
		}
	}

	/**
	 * Depth-first search for cycle detection.
	 *
	 * @param string                $node            Current node.
	 * @param array<string, bool>   $visited         Visited nodes.
	 * @param array<string, bool>   $recursion_stack Nodes in current path.
	 * @return void
	 * @throws RuntimeException If cycle detected.
	 */
	private function dfs_cycle_detection( string $node, array &$visited, array &$recursion_stack ): void {
		$visited[ $node ] = true;
		$recursion_stack[ $node ] = true;

		$dependencies = $this->dependency_map[ $node ] ?? array();

		foreach ( $dependencies as $dependency ) {
			// Only check provider dependencies (not service dependencies)
			if ( ! isset( $this->dependency_map[ $dependency ] ) ) {
				continue;
			}

			if ( ! isset( $visited[ $dependency ] ) ) {
				$this->dfs_cycle_detection( $dependency, $visited, $recursion_stack );
			} elseif ( isset( $recursion_stack[ $dependency ] ) && $recursion_stack[ $dependency ] ) {
				// Cycle detected
				unset( $recursion_stack[ $node ] );
				throw new RuntimeException(
					sprintf(
						'Circular dependency detected: %s -> %s',
						$node,
						$dependency
					)
				);
			}
		}

		unset( $recursion_stack[ $node ] );
	}

	/**
	 * Topological sort of providers.
	 *
	 * @param array<ServiceProviderInterface> $providers Providers.
	 * @return array<ServiceProviderInterface> Ordered providers.
	 */
	private function topological_sort( array $providers ): array {
		$sorted = array();
		$visited = array();
		$temp_mark = array();

		// Create provider map for quick lookup
		$provider_map = array();
		foreach ( $providers as $provider ) {
			$provider_map[ get_class( $provider ) ] = $provider;
		}

		// Process each provider
		foreach ( array_keys( $this->dependency_map ) as $provider_class ) {
			if ( ! isset( $visited[ $provider_class ] ) ) {
				$this->visit( $provider_class, $provider_map, $visited, $temp_mark, $sorted );
			}
		}

		// Add providers that weren't in dependency map (no dependencies)
		foreach ( $providers as $provider ) {
			$provider_class = get_class( $provider );
			if ( ! in_array( $provider, $sorted, true ) ) {
				$sorted[] = $provider;
			}
		}

		return $sorted;
	}

	/**
	 * Visit a node in topological sort.
	 *
	 * @param string                              $node        Node to visit.
	 * @param array<string, ServiceProviderInterface> $provider_map Provider map.
	 * @param array<string, bool>                 $visited     Visited nodes.
	 * @param array<string, bool>                 $temp_mark   Temporarily marked nodes.
	 * @param array<ServiceProviderInterface>     $sorted      Sorted result.
	 * @return void
	 * @throws RuntimeException If cycle detected.
	 */
	private function visit(
		string $node,
		array $provider_map,
		array &$visited,
		array &$temp_mark,
		array &$sorted
	): void {
		if ( isset( $temp_mark[ $node ] ) && $temp_mark[ $node ] ) {
			throw new RuntimeException( sprintf( 'Circular dependency detected involving: %s', $node ) );
		}

		if ( isset( $visited[ $node ] ) && $visited[ $node ] ) {
			return;
		}

		$temp_mark[ $node ] = true;

		$dependencies = $this->dependency_map[ $node ] ?? array();
		foreach ( $dependencies as $dependency ) {
			// Only process if it's a provider
			if ( isset( $provider_map[ $dependency ] ) ) {
				$this->visit( $dependency, $provider_map, $visited, $temp_mark, $sorted );
			}
		}

		$temp_mark[ $node ] = false;
		$visited[ $node ] = true;

		if ( isset( $provider_map[ $node ] ) ) {
			$sorted[] = $provider_map[ $node ];
		}
	}
}















