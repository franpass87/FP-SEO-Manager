<?php
/**
 * WP-CLI service provider.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers;

use FP\SEO\Infrastructure\AbstractServiceProvider;
use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Traits\ConditionalServiceTrait;
use FP\SEO\CLI\Commands\AnalysisCommand;
use FP\SEO\CLI\Commands\CacheCommand;
use FP\SEO\Analysis\Analyzer;
use FP\SEO\Data\Contracts\PostRepositoryInterface;
use FP\SEO\Infrastructure\Contracts\CacheInterface;

/**
 * WP-CLI service provider.
 *
 * Registers WP-CLI commands.
 */
class CLIServiceProvider extends AbstractServiceProvider {

	use ConditionalServiceTrait;

	/**
	 * Get an array of service provider class names that this provider depends on.
	 *
	 * @return array<class-string<ServiceProviderInterface>> An array of fully qualified class names.
	 */
	public function get_dependencies(): array {
		return array(
			CoreServiceProvider::class,
			AnalysisServiceProvider::class,
			DataServiceProvider::class,
		);
	}

	/**
	 * Register CLI services in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function register( Container $container ): void {
		// Only register if WP-CLI is available
		if ( ! $this->is_cli_context() ) {
			return;
		}

		// Register AnalysisCommand
		$container->singleton( AnalysisCommand::class, function( Container $container ) {
			return new AnalysisCommand(
				$container->get( Analyzer::class ),
				$container->get( PostRepositoryInterface::class )
			);
		} );

		// Register CacheCommand
		$container->singleton( CacheCommand::class, function( Container $container ) {
			return new CacheCommand(
				$container->get( CacheInterface::class )
			);
		} );
	}

	/**
	 * Boot CLI services.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	public function boot( Container $container ): void {
		// Only boot if WP-CLI is available
		if ( ! $this->is_cli_context() ) {
			return;
		}

		// Register WP-CLI commands
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			try {
				$analysis_command = $container->get( AnalysisCommand::class );
				\WP_CLI::add_command( 'fp-seo analysis', $analysis_command );
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'FP SEO: Failed to register AnalysisCommand: ' . $e->getMessage() );
				}
			}

			try {
				$cache_command = $container->get( CacheCommand::class );
				\WP_CLI::add_command( 'fp-seo cache', $cache_command );
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'FP SEO: Failed to register CacheCommand: ' . $e->getMessage() );
				}
			}
		}
	}

	/**
	 * Check if running in CLI context.
	 *
	 * @return bool True if CLI.
	 */
	private function is_cli_context(): bool {
		return defined( 'WP_CLI' ) && WP_CLI;
	}
}



