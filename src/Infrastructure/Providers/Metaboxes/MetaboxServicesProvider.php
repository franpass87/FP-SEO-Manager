<?php
/**
 * Metabox services provider.
 *
 * Registers refactored metabox services (FieldSaver, Analysis).
 *
 * @package FP\SEO\Infrastructure\Providers\Metaboxes
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers\Metaboxes;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Providers\Admin\AbstractAdminServiceProvider;
use FP\SEO\Editor\Metabox\Contracts\FieldSaverServiceInterface;
use FP\SEO\Editor\Metabox\Services\FieldSaverService;
use FP\SEO\Editor\Metabox\Contracts\AnalysisServiceInterface;
use FP\SEO\Editor\Metabox\Services\AnalysisService;
use FP\SEO\Editor\MetaboxRenderer;
use FP\SEO\Data\Contracts\PostRepositoryInterface;
use FP\SEO\Data\Contracts\PostMetaRepositoryInterface;
use FP\SEO\Infrastructure\Contracts\LoggerInterface;

/**
 * Metabox services provider.
 *
 * Registers all refactored metabox services with proper dependency injection.
 * 
 * NOTE: HomepageProtectionService, MetaboxController, and MetaboxRegistry have been removed.
 * These were workarounds for issues caused by PerformanceOptimizer interfering with
 * WordPress core post meta retrieval. The root cause was fixed by disabling PerformanceOptimizer.
 */
class MetaboxServicesProvider extends AbstractAdminServiceProvider {

	/**
	 * Get an array of service provider class names that this provider depends on.
	 *
	 * @return array<class-string<ServiceProviderInterface>> An array of fully qualified class names.
	 */
	public function get_dependencies(): array {
		return array(
			\FP\SEO\Infrastructure\Providers\CoreServiceProvider::class,
			\FP\SEO\Infrastructure\Providers\DataServiceProvider::class,
		);
	}

	/**
	 * Register metabox services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register FieldSaverService
		$container->singleton( FieldSaverServiceInterface::class, function( Container $container ) {
			return new FieldSaverService(
				$container->get( PostRepositoryInterface::class ),
				$container->get( PostMetaRepositoryInterface::class ),
				$container->get( LoggerInterface::class )
			);
		} );
		$container->singleton( FieldSaverService::class, function( Container $container ) {
			return $container->get( FieldSaverServiceInterface::class );
		} );

		// Register AnalysisService
		$container->singleton( AnalysisServiceInterface::class, function( Container $container ) {
			return new AnalysisService(
				$container->get( PostRepositoryInterface::class ),
				$container->get( PostMetaRepositoryInterface::class ),
				$container->get( LoggerInterface::class )
			);
		} );
		$container->singleton( AnalysisService::class, function( Container $container ) {
			return $container->get( AnalysisServiceInterface::class );
		} );

		// Register MetaboxRenderer
		$container->singleton( MetaboxRenderer::class );
	}

	/**
	 * Boot metabox services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function boot_admin( Container $container ): void {
		// No boot actions needed - services are registered as singletons
	}
}















