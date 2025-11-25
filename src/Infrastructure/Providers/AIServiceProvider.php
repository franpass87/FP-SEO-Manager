<?php
/**
 * AI service provider.
 *
 * Registers AI-related services (OpenAI, Embeddings, Content Optimization, etc.).
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
use FP\SEO\Infrastructure\Traits\ServiceRegistrationTrait;
use FP\SEO\Integrations\OpenAiClient;
use FP\SEO\AI\AdvancedContentOptimizer;
use FP\SEO\AI\QAPairExtractor;
use FP\SEO\AI\ConversationalVariants;
use FP\SEO\AI\EmbeddingsGenerator;
use FP\SEO\Integrations\AutoGenerationHook;
use FP\SEO\Automation\AutoSeoOptimizer;

/**
 * AI service provider.
 */
class AIServiceProvider extends AbstractServiceProvider {

	use ServiceBooterTrait;
	use ServiceRegistrationTrait;

	/**
	 * Register AI services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Register simple singletons (no dependencies)
		// Note: GEO AI services (FreshnessSignals, CitationFormatter, etc.)
		// are now registered by GEOServiceProvider for better organization
		$this->register_singletons( $container, array(
			OpenAiClient::class,
			AdvancedContentOptimizer::class,
			QAPairExtractor::class,
			ConversationalVariants::class,
			EmbeddingsGenerator::class,
			AutoGenerationHook::class,
		) );

		// Auto SEO Optimizer - requires OpenAI Client
		$container->singleton( AutoSeoOptimizer::class, function( Container $container ) {
			return new AutoSeoOptimizer( $container->get( OpenAiClient::class ) );
		} );

		// AI AJAX Handler is registered by AISettingsServiceProvider
	}

	/**
	 * Boot AI services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Register Auto-Generation Hook
		$this->boot_service(
			$container,
			AutoGenerationHook::class,
			'warning',
			'Failed to register AutoGenerationHook'
		);

		// Register Auto SEO Optimizer
		$this->boot_service(
			$container,
			AutoSeoOptimizer::class,
			'warning',
			'Failed to register AutoSeoOptimizer'
		);

		// Advanced Content Optimizer will be registered by AdminPagesServiceProvider after Menu
		// AI AJAX Handler will be registered by AISettingsServiceProvider
	}
}
