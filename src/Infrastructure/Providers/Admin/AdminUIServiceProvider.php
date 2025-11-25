<?php
/**
 * Admin UI service provider.
 *
 * Registers admin UI components (Notices, Admin Bar Badge).
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers\Admin;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Traits\ServiceBooterTrait;
use FP\SEO\Infrastructure\Traits\HookHelperTrait;
use FP\SEO\Admin\Notices;
use FP\SEO\Admin\AdminBarBadge;

/**
 * Admin UI service provider.
 */
class AdminUIServiceProvider extends AbstractAdminServiceProvider {

	use ServiceBooterTrait;
	use HookHelperTrait;

	/**
	 * Register admin UI services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Admin UI components
		$container->singleton( Notices::class );
		$container->singleton( AdminBarBadge::class );
	}

	/**
	 * Boot admin UI services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function boot_admin( Container $container ): void {

		// Defer to admin_init hook
		$this->defer_to_admin_init( $container, function( Container $container ) {
			$this->boot_service(
				$container,
				Notices::class,
				'warning',
				'Failed to register Notices'
			);

			$this->boot_service(
				$container,
				AdminBarBadge::class,
				'warning',
				'Failed to register AdminBarBadge'
			);
		} );
	}
}
