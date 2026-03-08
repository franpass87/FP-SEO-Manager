<?php
/**
 * GEO service provider.
 *
 * Registers Generative Engine Optimization services (conditional loading).
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers;

use FP\SEO\Infrastructure\AbstractServiceProvider;
use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Infrastructure\Traits\ServiceBooterTrait;
use FP\SEO\Infrastructure\Traits\ConditionalServiceTrait;
use FP\SEO\Infrastructure\Traits\HookHelperTrait;
use FP\SEO\Infrastructure\Traits\ServiceRegistrationTrait;
use FP\SEO\GEO\Router;
use FP\SEO\Frontend\Renderers\SchemaGeoRenderer;
// GeoShortcodes is registered in FrontendServiceProvider
use FP\SEO\Admin\GeoMetabox;
use FP\SEO\Admin\GeoSettings;
use FP\SEO\Links\Handlers\LinkingAjax;
use FP\SEO\Integrations\AutoIndexing;
use FP\SEO\GEO\FreshnessSignals;
use FP\SEO\GEO\CitationFormatter;
use FP\SEO\GEO\AuthoritySignals;
use FP\SEO\GEO\SemanticChunker;
use FP\SEO\GEO\EntityGraph;
use FP\SEO\GEO\MultiModalOptimizer;
use FP\SEO\GEO\ImageSeoOptimizer;
use FP\SEO\GEO\TrainingDatasetFormatter;
use FP\SEO\Utils\Logger;

/**
 * GEO service provider.
 *
 * Conditionally loads GEO services only if enabled in settings.
 */
class GEOServiceProvider extends AbstractServiceProvider {

	use ServiceBooterTrait;
	use ConditionalServiceTrait;
	use HookHelperTrait;
	use ServiceRegistrationTrait;

	/**
	 * Get an array of service provider class names that this provider depends on.
	 *
	 * @return array<class-string<ServiceProviderInterface>> An array of fully qualified class names.
	 */
	public function get_dependencies(): array {
		return array(
			CoreServiceProvider::class,
			FrontendServiceProvider::class,
		);
	}

	/**
	 * Register GEO services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Register ImageSeoOptimizer with dependencies (always available, not just when GEO is enabled)
		$container->singleton( ImageSeoOptimizer::class, function( Container $container ) {
			$logger = $container->get( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class );
			$image_extractor = new \FP\SEO\Editor\ImageExtractor();
			$image_manager = new \FP\SEO\Editor\Services\ImageManagementService();
			return new ImageSeoOptimizer( $logger, $image_extractor, $image_manager );
		} );
		
		// Alias for backward compatibility with string-based access
		$container->bind( 'image_seo_optimizer', function( Container $container ) {
			return $container->get( ImageSeoOptimizer::class );
		} );

		// Only register GEO-specific services if GEO is enabled
		if ( ! $this->is_geo_enabled() ) {
			return;
		}

		// Frontend GEO services (always register if GEO is enabled)
		// Register SchemaGeoRenderer with HookManager dependency
		$container->singleton( SchemaGeoRenderer::class, function( Container $container ) {
			$hook_manager = $container->get( HookManagerInterface::class );
			return new SchemaGeoRenderer( $hook_manager );
		} );

		// Note: GeoShortcodes is registered in FrontendServiceProvider with proper dependencies
		$this->register_singletons( $container, array(
			Router::class,
			AutoIndexing::class, // Frontend + backend
		) );

		// GEO AI services (moved from AIServiceProvider for better organization)
		$this->register_singletons( $container, array(
			FreshnessSignals::class,
			CitationFormatter::class,
			AuthoritySignals::class,
			SemanticChunker::class,
			EntityGraph::class,
			MultiModalOptimizer::class,
			TrainingDatasetFormatter::class,
		) );

		// Admin GEO services (only in admin context)
		if ( $this->is_admin_context() ) {
			// Register GeoMetabox with HookManager dependency
			$container->singleton( GeoMetabox::class, function( Container $container ) {
				$hook_manager = $container->get( HookManagerInterface::class );
				return new GeoMetabox( $hook_manager );
			} );

			// Register GeoSettings with HookManager dependency
			$container->singleton( GeoSettings::class, function( Container $container ) {
				$hook_manager = $container->get( HookManagerInterface::class );
				return new GeoSettings( $hook_manager );
			} );

			$this->register_singletons( $container, array(
				LinkingAjax::class,
			) );
		}
	}

	/**
	 * Boot GEO services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Only boot if GEO is enabled
		if ( ! $this->is_geo_enabled() ) {
			return;
		}

		// Boot frontend GEO services
		// Note: GeoShortcodes is booted in FrontendServiceProvider
		$this->boot_service( $container, SchemaGeoRenderer::class, 'warning', 'Failed to register SchemaGeoRenderer' );
		$this->boot_services_simple( $container, array(
			Router::class,
			AutoIndexing::class,
		), 'warning', 'Failed to register GEO' );

		// Boot GeoMetabox (admin only)
		if ( $this->is_admin_context() ) {
			$this->boot_service(
				$container,
				GeoMetabox::class,
				'warning',
				'Failed to register GeoMetabox'
			);
		}

		// Defer GEO admin services to admin_init
		if ( $this->is_admin_context() ) {
			$this->defer_to_admin_init( $container, function( Container $container ) {
				$this->boot_geo_admin_services( $container );
			}, 20 );
		}
	}

	/**
	 * Boot GEO admin services on admin_init.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	private function boot_geo_admin_services( Container $container ): void {
		if ( ! $this->is_admin_context() ) {
			return;
		}

		// Boot GEO admin services
		$this->boot_services_simple( $container, array(
			GeoSettings::class,
			LinkingAjax::class,
		), 'warning', 'Failed to register GEO admin' );
	}

	/**
	 * Run activation routines for GEO services.
	 *
	 * @return void
	 */
	public function activate(): void {
		if ( ! $this->is_geo_enabled() ) {
			return;
		}

		// Flush rewrite rules on activation
		// La classe è nel namespace corretto, l'autoloader la caricherà automaticamente
		try {
			$router = new \FP\SEO\GEO\Router();
			$router->add_rewrite_rules();
			flush_rewrite_rules();
		} catch ( \Throwable $e ) {
			// Log errore ma non bloccare l'attivazione
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				\FP\SEO\Utils\LoggerHelper::error( 'FP SEO: Failed to flush rewrite rules on GEO activation', array(
					'error' => $e->getMessage(),
				) );
			}
		}
	}

	/**
	 * Run deactivation routines for GEO services.
	 *
	 * @return void
	 */
	public function deactivate(): void {
		// Flush rewrite rules on deactivation
		flush_rewrite_rules();
	}
}
