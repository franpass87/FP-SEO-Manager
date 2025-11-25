<?php
/**
 * Admin Assets service provider.
 *
 * Registers admin assets (scripts, styles).
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers\Admin;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Traits\ServiceBooterTrait;
use FP\SEO\Utils\Assets;

/**
 * Admin Assets service provider.
 */
class AdminAssetsServiceProvider extends AbstractAdminServiceProvider {

	use ServiceBooterTrait;

	/**
	 * Register admin assets service in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Assets - must be registered BEFORE admin_enqueue_scripts
		$container->singleton( Assets::class );
	}

	/**
	 * Boot admin assets service.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function boot_admin( Container $container ): void {
		// Register Assets first (needed by other admin services)
		$this->boot_service(
			$container,
			Assets::class,
			'warning',
			'Failed to register Assets'
		);
	}
}
