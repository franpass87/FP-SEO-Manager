<?php
/**
 * Abstract service provider with default implementations.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure;

/**
 * Abstract base class for service providers.
 *
 * Provides default implementations for optional methods.
 */
abstract class AbstractServiceProvider implements ServiceProviderInterface {

	/**
	 * Boot services after all providers have been registered.
	 *
	 * Default implementation does nothing.
	 * Override in subclasses if needed.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Default: no boot actions needed.
	}

	/**
	 * Run activation routines for this provider.
	 *
	 * Default implementation does nothing.
	 * Override in subclasses if needed.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Default: no activation actions needed.
	}

	/**
	 * Run deactivation routines for this provider.
	 *
	 * Default implementation does nothing.
	 * Override in subclasses if needed.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Default: no deactivation actions needed.
	}
}




