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
use FP\SEO\Infrastructure\Providers\CoreServiceProvider;
use FP\SEO\Infrastructure\Traits\ServiceBooterTrait;
use FP\SEO\Infrastructure\Traits\ServiceRegistrationTrait;
use FP\SEO\Integrations\OpenAiClient;
use FP\SEO\AI\AdvancedContentOptimizer;
use FP\SEO\AI\QAPairExtractor;
use FP\SEO\AI\ConversationalVariants;
use FP\SEO\AI\EmbeddingsGenerator;
use FP\SEO\Integrations\AutoGenerationHook;
use FP\SEO\Automation\AutoSeoOptimizer;
use FP\SEO\GEO\MultiModalOptimizer;
use FP\SEO\GEO\ImageSeoOptimizer;

/**
 * AI service provider.
 */
class AIServiceProvider extends AbstractServiceProvider {

	/**
	 * Get an array of service provider class names that this provider depends on.
	 *
	 * @return array<class-string<\FP\SEO\Infrastructure\ServiceProviderInterface>>
	 */
	public function get_dependencies(): array {
		return array(
			CoreServiceProvider::class,
			GEOServiceProvider::class,
		);
	}

	use ServiceBooterTrait;
	use ServiceRegistrationTrait;

	/**
	 * Register AI services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Register OpenAiClient with dependencies
		$container->singleton( OpenAiClient::class, function( Container $container ) {
			$logger  = $container->get( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class );
			$options = $container->get( \FP\SEO\Infrastructure\Contracts\OptionsInterface::class );
			return new OpenAiClient( $logger, $options );
		} );

		// Register AdvancedContentOptimizer with dependencies
		$container->singleton( AdvancedContentOptimizer::class, function( Container $container ) {
			$openai_client = $container->get( OpenAiClient::class );
			return new AdvancedContentOptimizer( $openai_client );
		} );

		// Register AI classes with OpenAiClient dependency
		$container->singleton( ConversationalVariants::class, function( Container $container ) {
			return new ConversationalVariants( $container->get( OpenAiClient::class ) );
		} );
		$container->singleton( EmbeddingsGenerator::class, function( Container $container ) {
			return new EmbeddingsGenerator( $container->get( OpenAiClient::class ) );
		} );

		// Register QAPairExtractor with dependencies
		$container->singleton( QAPairExtractor::class, function( Container $container ) {
			$openai_client = $container->get( OpenAiClient::class );
			$logger        = $container->get( \FP\SEO\Infrastructure\Contracts\LoggerInterface::class );
			return new QAPairExtractor( $openai_client, $logger );
		} );

		// AutoGenerationHook requires dependencies
		$container->singleton( AutoGenerationHook::class, function( Container $container ) {
			return new AutoGenerationHook(
				$container->get( QAPairExtractor::class ),
				$container->get( MultiModalOptimizer::class ),
				$container->get( ImageSeoOptimizer::class )
			);
		} );

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
