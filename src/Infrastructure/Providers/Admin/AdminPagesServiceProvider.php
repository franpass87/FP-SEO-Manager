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
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Infrastructure\Contracts\OptionsInterface;
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
	 * Get an array of service provider class names that this provider depends on.
	 *
	 * @return array<class-string<ServiceProviderInterface>> An array of fully qualified class names.
	 */
	public function get_dependencies(): array {
		return array(
			\FP\SEO\Infrastructure\Providers\FrontendServiceProvider::class,
		);
	}

	/**
	 * Register admin pages in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register Menu with HookManager dependency
		$container->singleton( Menu::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new Menu( $hook_manager );
		} );

		// Register SettingsPage with HookManager dependency
		$container->singleton( SettingsPage::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new SettingsPage( $hook_manager );
		} );

		// Register BulkAuditPage with HookManager and Options dependencies
		$container->singleton( BulkAuditPage::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			$options      = $container->get( OptionsInterface::class );
			return new BulkAuditPage( $hook_manager, $options );
		} );

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

		// Register Schema admin menu (Advanced Schema Manager is registered by FrontendServiceProvider)
		try {
			$schema_manager = $container->get( \FP\SEO\Schema\AdvancedSchemaManager::class );
			if ( method_exists( $schema_manager, 'add_schema_menu' ) ) {
				$hook_manager = $container->get( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class );
				$hook_manager->add_action( 'admin_menu', array( $schema_manager, 'add_schema_menu' ) );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO: Failed to register Schema admin menu: ' . $e->getMessage() );
			}
		}

		// Register Social Media admin menu (ImprovedSocialMediaManager is registered by FrontendServiceProvider)
		try {
			$social_manager = $container->get( \FP\SEO\Social\ImprovedSocialMediaManager::class );
			if ( method_exists( $social_manager, 'add_social_menu' ) ) {
				$hook_manager = $container->get( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class );
				$hook_manager->add_action( 'admin_menu', array( $social_manager, 'add_social_menu' ) );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO: Failed to register Social Media admin menu: ' . $e->getMessage() );
			}
		}

		// Register Internal Links admin menu (InternalLinkManager is registered by FrontendServiceProvider)
		try {
			$links_manager = $container->get( \FP\SEO\Links\InternalLinkManager::class );
			if ( method_exists( $links_manager, 'add_links_menu' ) ) {
				$hook_manager = $container->get( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class );
				$hook_manager->add_action( 'admin_menu', array( $links_manager, 'add_links_menu' ) );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO: Failed to register Internal Links admin menu: ' . $e->getMessage() );
			}
		}

		// Register Multiple Keywords admin menu (MultipleKeywordsManager is registered by FrontendServiceProvider)
		try {
			$keywords_manager = $container->get( \FP\SEO\Keywords\MultipleKeywordsManager::class );
			if ( method_exists( $keywords_manager, 'add_keywords_menu' ) ) {
				$hook_manager = $container->get( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class );
				$hook_manager->add_action( 'admin_menu', array( $keywords_manager, 'add_keywords_menu' ) );
			}
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO: Failed to register Multiple Keywords admin menu: ' . $e->getMessage() );
			}
		}

		// Register Advanced Content Optimizer after Menu
		$this->boot_service(
			$container,
			\FP\SEO\AI\AdvancedContentOptimizer::class,
			'warning',
			'Failed to register AdvancedContentOptimizer'
		);

		// Register Advanced Content Optimizer AJAX Handler
		try {
			$optimizer = $container->get( \FP\SEO\AI\AdvancedContentOptimizer::class );
			$hook_manager = $container->get( \FP\SEO\Infrastructure\Contracts\HookManagerInterface::class );
			$ajax_handler = new \FP\SEO\AI\Handlers\AdvancedContentOptimizerAjaxHandler( $optimizer, $hook_manager );
			$ajax_handler->register();
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'FP SEO: Failed to register AdvancedContentOptimizerAjaxHandler: ' . $e->getMessage() );
			}
		}
	}
}
