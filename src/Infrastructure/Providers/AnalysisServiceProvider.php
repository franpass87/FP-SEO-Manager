<?php
/**
 * Analysis service provider.
 *
 * Registers SEO analysis services (Analyzer, ScoreEngine, Checks).
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers;

use FP\SEO\Infrastructure\AbstractServiceProvider;
use FP\SEO\Infrastructure\Container;
use FP\SEO\Analysis\Analyzer;
use FP\SEO\Scoring\ScoreEngine;

/**
 * Analysis service provider.
 */
class AnalysisServiceProvider extends AbstractServiceProvider {

	/**
	 * Register analysis services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Score Engine - calculates SEO scores
		$container->singleton( ScoreEngine::class );

		// Analyzer - coordinates SEO checks execution
		$container->singleton( Analyzer::class, function() {
			return new Analyzer();
		} );

		// Tag all check classes for easy resolution
		$container->tag( 'seo_checks',
			\FP\SEO\Analysis\Checks\TitleLengthCheck::class,
			\FP\SEO\Analysis\Checks\MetaDescriptionCheck::class,
			\FP\SEO\Analysis\Checks\H1PresenceCheck::class,
			\FP\SEO\Analysis\Checks\HeadingsStructureCheck::class,
			// ImageAltCheck removed - image optimization features disabled
			\FP\SEO\Analysis\Checks\InternalLinksCheck::class,
			\FP\SEO\Analysis\Checks\OgCardsCheck::class,
			\FP\SEO\Analysis\Checks\TwitterCardsCheck::class,
			\FP\SEO\Analysis\Checks\CanonicalCheck::class,
			\FP\SEO\Analysis\Checks\RobotsIndexabilityCheck::class,
			\FP\SEO\Analysis\Checks\FaqSchemaCheck::class,
			\FP\SEO\Analysis\Checks\HowToSchemaCheck::class,
			\FP\SEO\Analysis\Checks\SchemaPresetsCheck::class,
			\FP\SEO\Analysis\Checks\SearchIntentCheck::class,
			\FP\SEO\Analysis\Checks\AiOptimizedContentCheck::class
		);
	}

	/**
	 * Boot analysis services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Analyzer and ScoreEngine don't need explicit booting,
		// they are used on-demand by other services
	}
}




