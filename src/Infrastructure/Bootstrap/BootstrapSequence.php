<?php
/**
 * Centralized bootstrap sequence management.
 *
 * Manages the plugin boot sequence with validation, error handling, and logging.
 *
 * @package FP\SEO\Infrastructure\Bootstrap
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Bootstrap;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\ServiceProviderInterface;
use FP\SEO\Infrastructure\ServiceProviderRegistry;
use FP\SEO\Utils\LoggerHelper;
use RuntimeException;

/**
 * Centralized bootstrap sequence manager.
 *
 * Handles the plugin boot sequence with proper error handling and validation.
 */
class BootstrapSequence {

	/**
	 * Hook manager instance.
	 *
	 * @var HookManager
	 */
	private HookManager $hook_manager;

	/**
	 * Dependency resolver instance.
	 *
	 * @var DependencyResolver
	 */
	private DependencyResolver $dependency_resolver;

	/**
	 * Whether bootstrap has started.
	 *
	 * @var bool
	 */
	private bool $started = false;

	/**
	 * Bootstrap errors.
	 *
	 * @var array<string, \Throwable>
	 */
	private array $errors = array();

	/**
	 * Constructor.
	 *
	 * @param HookManager         $hook_manager         Hook manager instance.
	 * @param DependencyResolver  $dependency_resolver  Dependency resolver instance.
	 */
	public function __construct( HookManager $hook_manager, DependencyResolver $dependency_resolver ) {
		$this->hook_manager        = $hook_manager;
		$this->dependency_resolver = $dependency_resolver;
	}

	/**
	 * Execute bootstrap sequence.
	 *
	 * @param Container              $container Container instance.
	 * @param ServiceProviderRegistry $registry  Service provider registry.
	 * @param array<ServiceProviderInterface> $providers Providers to boot.
	 * @return void
	 * @throws RuntimeException If bootstrap fails critically.
	 */
	public function execute(
		Container $container,
		ServiceProviderRegistry $registry,
		array $providers
	): void {
		if ( $this->started ) {
			throw new RuntimeException( 'Bootstrap sequence already started' );
		}

		$this->started = true;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			LoggerHelper::debug( 'BootstrapSequence: Starting bootstrap sequence', array(
				'providers_count' => count( $providers ),
			) );
		}

		// Validate WordPress and PHP versions
		$this->validate_environment();

		// Resolve provider dependencies and order
		try {
			$ordered_providers = $this->dependency_resolver->resolve_provider_order( $providers );
		} catch ( \Throwable $e ) {
			$this->errors['dependency_resolution'] = $e;
			LoggerHelper::error( 'BootstrapSequence: Failed to resolve provider dependencies', array(
				'exception' => $e->getMessage(),
			) );
			// Continue with original order as fallback
			$ordered_providers = $providers;
		}

		// Register providers in order
		$this->register_providers( $registry, $ordered_providers );

		// Boot providers
		$this->boot_providers( $container, $registry );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			LoggerHelper::debug( 'BootstrapSequence: Bootstrap sequence completed', array(
				'errors_count' => count( $this->errors ),
			) );
		}

		// If critical errors occurred, log but don't throw (allow plugin to continue)
		if ( ! empty( $this->errors ) ) {
			LoggerHelper::warning( 'BootstrapSequence: Bootstrap completed with errors', array(
				'errors' => array_keys( $this->errors ),
			) );
		}
	}

	/**
	 * Get hook manager instance.
	 *
	 * @return HookManager
	 */
	public function get_hook_manager(): HookManager {
		return $this->hook_manager;
	}

	/**
	 * Get bootstrap errors.
	 *
	 * @return array<string, \Throwable>
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Check if bootstrap has errors.
	 *
	 * @return bool
	 */
	public function has_errors(): bool {
		return ! empty( $this->errors );
	}

	/**
	 * Validate WordPress and PHP environment.
	 *
	 * @return void
	 * @throws RuntimeException If environment is invalid.
	 */
	private function validate_environment(): void {
		// Validate WordPress version
		global $wp_version;
		$required_wp_version = '6.2';

		if ( version_compare( $wp_version, $required_wp_version, '<' ) ) {
			throw new RuntimeException(
				sprintf(
					'FP SEO Performance requires WordPress %s or higher. You are running WordPress %s.',
					$required_wp_version,
					$wp_version
				)
			);
		}

		// Validate PHP version
		$required_php_version = '8.0';
		if ( version_compare( PHP_VERSION, $required_php_version, '<' ) ) {
			throw new RuntimeException(
				sprintf(
					'FP SEO Performance requires PHP %s or higher. You are running PHP %s.',
					$required_php_version,
					PHP_VERSION
				)
			);
		}
	}

	/**
	 * Register service providers.
	 *
	 * @param ServiceProviderRegistry           $registry  Registry.
	 * @param array<ServiceProviderInterface> $providers Providers.
	 * @return void
	 */
	private function register_providers( ServiceProviderRegistry $registry, array $providers ): void {
		foreach ( $providers as $provider ) {
			try {
				$registry->register( $provider );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					LoggerHelper::debug( 'BootstrapSequence: Provider registered', array(
						'provider' => get_class( $provider ),
					) );
				}
			} catch ( \Throwable $e ) {
				$this->errors[ 'register_' . get_class( $provider ) ] = $e;
				LoggerHelper::error( 'BootstrapSequence: Failed to register provider', array(
					'provider'  => get_class( $provider ),
					'exception' => $e->getMessage(),
				) );
				// Continue with next provider
				continue;
			}
		}
	}

	/**
	 * Boot service providers.
	 *
	 * @param Container              $container Container.
	 * @param ServiceProviderRegistry $registry  Registry.
	 * @return void
	 */
	private function boot_providers( Container $container, ServiceProviderRegistry $registry ): void {
		// The registry already handles boot with error handling
		// We just call it and track any errors
		try {
			$registry->boot();
		} catch ( \Throwable $e ) {
			$this->errors['boot'] = $e;
			LoggerHelper::error( 'BootstrapSequence: Failed to boot providers', array(
				'exception' => $e->getMessage(),
			) );
			// Don't re-throw - allow plugin to continue with partial boot
		}
	}
}















