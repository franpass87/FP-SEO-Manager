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

