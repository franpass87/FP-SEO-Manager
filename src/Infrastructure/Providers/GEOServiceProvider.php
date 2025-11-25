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
use FP\SEO\Infrastructure\Traits\ServiceBooterTrait;
use FP\SEO\Infrastructure\Traits\ConditionalServiceTrait;
use FP\SEO\Infrastructure\Traits\HookHelperTrait;
use FP\SEO\Infrastructure\Traits\ServiceRegistrationTrait;
use FP\SEO\GEO\Router;
use FP\SEO\Front\SchemaGeo;
use FP\SEO\Shortcodes\GeoShortcodes;
use FP\SEO\Admin\GeoMetaBox;
use FP\SEO\Admin\GeoSettings;
use FP\SEO\Linking\LinkingAjax;
use FP\SEO\Integrations\AutoIndexing;
use FP\SEO\GEO\FreshnessSignals;
use FP\SEO\GEO\CitationFormatter;
use FP\SEO\GEO\AuthoritySignals;
use FP\SEO\GEO\SemanticChunker;
use FP\SEO\GEO\EntityGraph;
use FP\SEO\GEO\MultiModalOptimizer;
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
	 * Register GEO services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Only register if GEO is enabled
		if ( ! $this->is_geo_enabled() ) {
			return;
		}

		// Frontend GEO services (always register if GEO is enabled)
		$this->register_singletons( $container, array(
			Router::class,
			SchemaGeo::class,
			GeoShortcodes::class,
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
			$this->register_singletons( $container, array(
				GeoMetaBox::class,
				GeoSettings::class,
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
		$this->boot_services_simple( $container, array(
			Router::class,
			SchemaGeo::class,
			GeoShortcodes::class,
			AutoIndexing::class,
		), 'warning', 'Failed to register GEO' );

		// Boot GeoMetaBox (admin only)
		if ( $this->is_admin_context() ) {
			$this->boot_service(
				$container,
				GeoMetaBox::class,
				'warning',
				'Failed to register GeoMetaBox'
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
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && class_exists( '\FP\SEO\Utils\Logger' ) ) {
				\FP\SEO\Utils\Logger::error( 'FP SEO: Failed to flush rewrite rules on GEO activation', array(
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
