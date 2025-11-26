<?php
/**
 * Service Provider interface.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure;

/**
 * Interface for service providers.
 */
interface ServiceProviderInterface {

	/**
	 * Register services in the container.
	 *
	 * This method should register all services with the container
	 * but not instantiate them yet.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void;

	/**
	 * Boot services after all providers have been registered.
	 *
	 * This method should initialize services and register WordPress hooks.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void;

	/**
	 * Run activation routines for this provider.
	 *
	 * @return void
	 */
	public function activate(): void;

	/**
	 * Run deactivation routines for this provider.
	 *
	 * @return void
	 */
	public function deactivate(): void;
}




