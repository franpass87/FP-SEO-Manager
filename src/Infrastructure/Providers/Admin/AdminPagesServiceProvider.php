<?php
/**
 * Admin Pages service provider.
 *
 * Registers admin pages (Menu, Settings, Bulk Audit, Performance Dashboard).
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers\Admin;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Traits\ServiceBooterTrait;
use FP\SEO\Infrastructure\Traits\ServiceRegistrationTrait;
use FP\SEO\Admin\Menu;
use FP\SEO\Admin\SettingsPage;
use FP\SEO\Admin\BulkAuditPage;
use FP\SEO\Admin\PerformanceDashboard;

/**
 * Admin Pages service provider.
 */
class AdminPagesServiceProvider extends AbstractAdminServiceProvider {

	use ServiceBooterTrait;
	use ServiceRegistrationTrait;

	/**
	 * Register admin pages in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register core admin pages as singletons
		$this->register_singletons( $container, array(
			Menu::class,
			SettingsPage::class,
			BulkAuditPage::class,
		) );

		// Performance Dashboard is registered by PerformanceServiceProvider with dependencies
	}

	/**
	 * Boot admin pages.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function boot_admin( Container $container ): void {

		// Register Menu first (must be early for admin_menu hook)
		$this->boot_service(
			$container,
			Menu::class,
			'error',
			'Failed to register Menu'
		);

		// Register Settings Page
		$this->boot_service(
			$container,
			SettingsPage::class,
			'warning',
			'Failed to register SettingsPage'
		);

		// Register Bulk Audit Page
		$this->boot_service(
			$container,
			BulkAuditPage::class,
			'warning',
			'Failed to register BulkAuditPage'
		);

		// Register Performance Dashboard after Menu
		$this->boot_service(
			$container,
			PerformanceDashboard::class,
			'warning',
			'Failed to register PerformanceDashboard'
		);

		// Advanced Schema Manager is registered by FrontendServiceProvider (used in both frontend and admin)

		// Register Advanced Content Optimizer after Menu
		$this->boot_service(
			$container,
			\FP\SEO\AI\AdvancedContentOptimizer::class,
			'warning',
			'Failed to register AdvancedContentOptimizer'
		);
	}
}
