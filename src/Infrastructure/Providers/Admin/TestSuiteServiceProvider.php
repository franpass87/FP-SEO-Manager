<?php
/**
 * Test Suite service provider.
 *
 * Registers test suite pages (only for admins).
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
use FP\SEO\Infrastructure\Traits\ServiceRegistrationTrait;
use FP\SEO\Admin\TestSuitePage;
use FP\SEO\Admin\TestSuiteAjax;

/**
 * Test Suite service provider.
 */
class TestSuiteServiceProvider extends AbstractAdminServiceProvider {

	use ServiceBooterTrait;
	use HookHelperTrait;
	use ServiceRegistrationTrait;

	/**
	 * Register test suite services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register test suite services as singletons
		$this->register_singletons( $container, array(
			TestSuitePage::class,
			TestSuiteAjax::class,
		) );
	}

	/**
	 * Boot test suite services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function boot_admin( Container $container ): void {
		// Defer to admin_init hook with capability check
		$this->boot_on_admin_init_with_capability( $container, function( Container $container ) {
			$this->boot_services_simple( $container, array(
				TestSuitePage::class,
				TestSuiteAjax::class,
			), 'warning', 'Failed to register' );
		} );
	}
}
