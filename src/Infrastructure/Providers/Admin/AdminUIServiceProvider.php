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
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Admin\Notices;
use FP\SEO\Admin\AdminBarBadge;

/**
 * Admin UI service provider.
 */
class AdminUIServiceProvider extends AbstractAdminServiceProvider {

	use ServiceBooterTrait;
	use HookHelperTrait;

	/**
	 * Get an array of service provider class names that this provider depends on.
	 *
	 * @return array<class-string<ServiceProviderInterface>> An array of fully qualified class names.
	 */
	public function get_dependencies(): array {
		return array(
			\FP\SEO\Infrastructure\Providers\CoreServiceProvider::class,
		);
	}

	/**
	 * Register admin UI services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register Notices with HookManager dependency
		$container->singleton( Notices::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new Notices( $hook_manager );
		} );

		// Register AdminBarBadge with HookManager dependency
		$container->singleton( AdminBarBadge::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new AdminBarBadge( $hook_manager );
		} );
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
