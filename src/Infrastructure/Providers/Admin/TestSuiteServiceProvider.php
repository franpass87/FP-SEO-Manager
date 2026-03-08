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
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
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
	 * Register test suite services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register TestSuiteAjax with HookManager dependency
		$container->singleton( TestSuiteAjax::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new TestSuiteAjax( $hook_manager );
		} );

		// Register TestSuitePage with HookManager dependency
		$container->singleton( TestSuitePage::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new TestSuitePage( $hook_manager );
		} );
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
